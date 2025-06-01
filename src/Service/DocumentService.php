<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */

namespace PrestaShop\Module\Pskyc\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;

/**
 * Class DocumentService
 *
 * Business logic service for handling KYC document operations
 * Manages document upload, encryption, validation, and retrieval
 */
class DocumentService
{
    /**
     * @var DocumentRepository
     */
    private $documentRepository;

    /**
     * @var VerificationRepository
     */
    private $verificationRepository;

    /**
     * @var EncryptionService
     */
    private $encryptionService;

    /**
     * Maximum allowed file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Allowed MIME types for document uploads
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * DocumentService constructor
     *
     * @param DocumentRepository $documentRepository Repository for document data operations
     * @param VerificationRepository $verificationRepository Repository for verification data operations
     * @param EncryptionService $encryptionService Service for file encryption/decryption
     */
    public function __construct(
        DocumentRepository $documentRepository,
        VerificationRepository $verificationRepository,
        EncryptionService $encryptionService,
    ) {
        $this->documentRepository = $documentRepository;
        $this->encryptionService = $encryptionService;
        $this->verificationRepository = $verificationRepository;
    }

    /**
     * Upload and process a document
     *
     * Validates, encrypts, and stores a document file with metadata
     *
     * @param int $verificationId The verification ID to associate the document with
     * @param array $fileData Uploaded file data from $_FILES
     * @param string $documentType Type of document (e.g., 'passport', 'utility_bill')
     * @param string|null $side Side of the document ('front' or 'back'), or null for single-sided documents
     *
     * @return array Result array with success status and document ID or error message
     */
    public function uploadDocument(int $verificationId, array $fileData, string $documentType, ?string $side = null): array
    {
        try {
            // Validate file
            $validationResult = $this->validateUploadedFile($fileData);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                ];
            }

            // Verify verification exists
            $verification = $this->verificationRepository->findById($verificationId);
            if (!$verification) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification ID',
                ];
            }

            // Check if document type requires both sides and validate completeness
            if ($this->requiresBothSides($documentType)) {
                $validationResult = $this->validateDocumentSideRequirement($verificationId, $documentType, $side);
                if (!$validationResult['valid']) {
                    return [
                        'success' => false,
                        'message' => $validationResult['message'],
                    ];
                }
            }

            // Encrypt and save file (use a temp name, will rename after DB insert)
            $extension = $this->getFileExtension($fileData['name']);
            $tempStoredFilename = 'doc_tmp_' . uniqid() . '.' . $extension;
            $uploadPath = $this->getUploadDirectory() . '/' . $tempStoredFilename;

            $encryptionResult = $this->encryptionService->encryptFile($fileData['tmp_name'], $uploadPath);

            // Save document metadata to database (original filename)
            $documentData = [
                'verification_id' => $verificationId,
                'type' => $documentType,
                'side' => $side,
                'filename' => $fileData['name'],
                'filesize' => $fileData['size'],
                'mime' => $this->getMimeType($fileData['tmp_name']),
                'sha256' => $encryptionResult['sha256'],
                'iv' => $encryptionResult['iv'],
                'encrypted' => 1,
                'expires_at' => $this->calculateExpiryDate(),
            ];

            $documentId = $this->documentRepository->create($documentData);

            // Now generate the final stored filename
            $finalDocument = [
                'id_kyc_document' => $documentId,
                'filename' => $fileData['name'],
            ];
            $storedFilename = $this->generateStoredFilename($finalDocument);
            $finalPath = $this->getUploadDirectory() . '/' . $storedFilename;

            // Rename the temp file to the final stored filename
            rename($uploadPath, $finalPath);

            return [
                'success' => true,
                'document_id' => $documentId,
                'filename' => $storedFilename,
                'side' => $side,
            ];
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document upload error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return [
                'success' => false,
                'message' => 'Failed to upload document',
            ];
        }
    }

    /**
     * Retrieve and decrypt a document
     *
     * Fetches a document from storage, decrypts it, and returns the content
     *
     * @param int $documentId The document ID to retrieve
     * @param bool $verifyIntegrity Whether to verify file integrity (default: true)
     *
     * @return array Result array with success status and file data or error message
     */
    public function getDocument(int $documentId, bool $verifyIntegrity = true): array
    {
        try {
            $document = $this->documentRepository->findById($documentId);
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found',
                ];
            }

            $filePath = $this->getUploadDirectory() . '/' . $this->generateStoredFilename($document);
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'Document file not found on disk',
                ];
            }

            $decryptedData = $this->encryptionService->decryptFile($filePath, $document['iv']);

            if ($verifyIntegrity && !$this->encryptionService->verifyIntegrity($decryptedData, $document['sha256'])) {
                return [
                    'success' => false,
                    'message' => 'Document integrity verification failed',
                ];
            }

            return [
                'success' => true,
                'data' => $decryptedData,
                'filename' => $document['filename'],
                'mime' => $document['mime'],
                'size' => $document['filesize'],
            ];
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document retrieval error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return [
                'success' => false,
                'message' => 'Failed to retrieve document',
            ];
        }
    }

    /**
     * Delete a document and its file
     *
     * Removes the document record and securely deletes the encrypted file
     *
     * @param int $documentId The document ID to delete
     *
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteDocument(int $documentId): bool
    {
        try {
            $document = $this->documentRepository->findById($documentId);
            if (!$document) {
                return false;
            }

            $filePath = $this->getUploadDirectory() . '/' . $this->generateStoredFilename($document);

            // Delete from database first
            $dbDeleted = $this->documentRepository->delete($documentId);

            // Then securely delete the file
            if (file_exists($filePath)) {
                $this->encryptionService->secureDelete($filePath);
            }

            return $dbDeleted;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document deletion error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Clean up expired documents
     *
     * Finds and deletes documents that have passed their expiration date
     *
     * @return int Number of documents deleted
     */
    public function cleanupExpiredDocuments(): int
    {
        try {
            $expiredDocuments = $this->documentRepository->findExpiredDocuments();
            $deletedCount = 0;

            foreach ($expiredDocuments as $document) {
                if ($this->deleteDocument($document['id_kyc_document'])) {
                    ++$deletedCount;
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Expired document cleanup error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return 0;
        }
    }

    /**
     * Validate uploaded file
     *
     * Checks file size, type, and upload errors
     *
     * @param array $fileData Uploaded file data from $_FILES
     *
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    private function validateUploadedFile(array $fileData): array
    {
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => 'File upload failed',
            ];
        }

        // Check file size
        if ($fileData['size'] > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'message' => 'File size exceeds 10MB limit',
            ];
        }

        // Check MIME type
        $mimeType = $this->getMimeType($fileData['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'message' => 'File type not allowed. Only JPG, PNG, and PDF files are accepted',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get file MIME type
     *
     * Determines the MIME type of a file using finfo
     *
     * @param string $filePath Path to the file
     *
     * @return string The MIME type
     */
    protected function getMimeType(string $filePath): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Get file extension from filename
     *
     * @param string $filename The filename
     *
     * @return string The file extension (without dot)
     */
    protected function getFileExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Get upload directory path
     *
     * Returns the directory where encrypted documents are stored
     *
     * @return string The upload directory path
     */
    protected function getUploadDirectory(): string
    {
        return _PS_MODULE_DIR_ . 'pskyc/secure_upload';
    }

    /**
     * Generate stored filename
     *
     * Creates the filename used to store the document on disk
     *
     * @param array $document Document record from database
     *
     * @return string The generated filename
     */
    protected function generateStoredFilename(array $document): string
    {
        return 'doc_' . $document['id_kyc_document'] . '_' . hash('md5', $document['filename']);
    }

    /**
     * Calculate expiry date for documents
     *
     * Returns the date when documents should be automatically deleted
     *
     * @return string Expiry date in MySQL datetime format
     */
    private function calculateExpiryDate(): string
    {
        $retentionDays = (int) \Configuration::get('PSKYC_RETENTION_DAYS', 365);

        return date('Y-m-d H:i:s', strtotime('+' . $retentionDays . ' days'));
    }

    /**
     * Check if a document type requires both front and back sides
     *
     * @param string $documentType The document type to check
     *
     * @return bool True if the document requires both sides
     */
    public function requiresBothSides(string $documentType): bool
    {
        $twoSidedDocuments = [
            'drivers_license',
            'national_id',
            'residence_permit',
            'id_card',
        ];

        return in_array($documentType, $twoSidedDocuments);
    }

    /**
     * Get the required sides for a document type
     *
     * @param string $documentType The document type
     *
     * @return array Array of required sides
     */
    public function getRequiredSides(string $documentType): array
    {
        if ($this->requiresBothSides($documentType)) {
            return ['front', 'back'];
        }

        return []; // Single-sided documents don't specify sides
    }

    /**
     * Validate document side requirement
     *
     * Checks if the required sides are properly specified and not duplicated
     *
     * @param int $verificationId The verification ID
     * @param string $documentType The document type
     * @param string|null $side The side being uploaded
     *
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    private function validateDocumentSideRequirement(int $verificationId, string $documentType, ?string $side): array
    {
        // For two-sided documents, side must be specified
        if ($side === null) {
            return [
                'valid' => false,
                'message' => 'Document side (front/back) must be specified for ' . $documentType,
            ];
        }

        // Validate side value
        if (!in_array($side, ['front', 'back'])) {
            return [
                'valid' => false,
                'message' => 'Invalid document side. Must be "front" or "back"',
            ];
        }

        // Check if this side already exists for this document type
        $existingDocuments = $this->documentRepository->findByVerificationIdAndType($verificationId, $documentType);
        foreach ($existingDocuments as $doc) {
            if ($doc['side'] === $side) {
                return [
                    'valid' => false,
                    'message' => 'The ' . $side . ' side of this document has already been uploaded',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Check if verification has complete documents
     *
     * Validates that all required document types and sides have been uploaded
     *
     * @param int $verificationId The verification ID to check
     *
     * @return array Result with 'complete' boolean and details about missing documents
     */
    public function checkDocumentCompleteness(int $verificationId): array
    {
        try {
            $documents = $this->documentRepository->findByVerificationId($verificationId);
            $documentsByType = [];

            // Group documents by type
            foreach ($documents as $doc) {
                $type = $doc['type'];
                if (!isset($documentsByType[$type])) {
                    $documentsByType[$type] = [];
                }
                $documentsByType[$type][] = $doc;
            }

            $missing = [];
            $requiredTypes = ['identity', 'address']; // Configure as needed

            foreach ($requiredTypes as $category) {
                // This would need to be configured based on your requirements
                // For now, we'll check that at least one identity and one address document exists
                $hasDocument = false;

                foreach ($documentsByType as $type => $docs) {
                    if ($this->getDocumentCategory($type) === $category) {
                        // Check if all required sides are present for two-sided documents
                        if ($this->requiresBothSides($type)) {
                            $sides = array_column($docs, 'side');
                            if (in_array('front', $sides) && in_array('back', $sides)) {
                                $hasDocument = true;
                                break;
                            }
                        } else {
                            $hasDocument = true;
                            break;
                        }
                    }
                }

                if (!$hasDocument) {
                    $missing[] = $category;
                }
            }

            return [
                'complete' => empty($missing),
                'missing_categories' => $missing,
                'documents_by_type' => $documentsByType,
            ];
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document completeness check error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return [
                'complete' => false,
                'missing_categories' => ['unknown'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the category for a document type
     *
     * @param string $documentType The document type
     *
     * @return string The category ('identity' or 'address')
     */
    private function getDocumentCategory(string $documentType): string
    {
        $identityDocuments = ['passport', 'drivers_license', 'national_id', 'residence_permit'];
        $addressDocuments = ['utility_bill', 'bank_statement', 'rental_agreement', 'tax_document', 'insurance_statement', 'government_letter'];

        if (in_array($documentType, $identityDocuments)) {
            return 'identity';
        } elseif (in_array($documentType, $addressDocuments)) {
            return 'address';
        }

        return 'unknown';
    }

    /**
     * Replace an existing document with a new upload (for re-upload by customer)
     *
     * @param int $documentId The document ID to replace
     * @param array $file The new uploaded file (from $_FILES)
     *
     * @return array Result array with success status and message
     */
    public function replaceDocument(int $documentId, array $file): array
    {
        try {
            $document = $this->documentRepository->findById($documentId);
            if (!$document) {
                return ['success' => false, 'message' => 'Document not found'];
            }
            // Remove old file
            $oldFilePath = $this->getUploadDirectory() . '/' . $this->generateStoredFilename($document);
            if (file_exists($oldFilePath)) {
                $this->encryptionService->secureDelete($oldFilePath);
            }
            // Update filename in DB first (so generateStoredFilename will match new file)
            $updateData = [
                'filename' => $file['name'],
                'filesize' => $file['size'],
                'mime' => $this->getMimeType($file['tmp_name']),
                'date_uploaded' => date('Y-m-d H:i:s'),
                'status' => 'pending',
                'admin_note' => null,
            ];
            $this->documentRepository->updateDocumentFields($documentId, $updateData);
            // Encrypt and save new file with correct name
            $newDocument = $this->documentRepository->findById($documentId);
            $newFilePath = $this->getUploadDirectory() . '/' . $this->generateStoredFilename($newDocument);
            $encryptionResult = $this->encryptionService->encryptFile($file['tmp_name'], $newFilePath);
            // Update encryption fields
            $this->documentRepository->updateDocumentFields($documentId, [
                'sha256' => $encryptionResult['sha256'],
                'iv' => $encryptionResult['iv'],
                'encrypted' => 1,
            ]);
            // Also update the parent verification status to pending
            $this->verificationRepository->updateStatus($document['id_kyc_verification'], 'pending');

            return ['success' => true];
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document re-upload error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return ['success' => false, 'message' => 'Failed to replace document'];
        }
    }

    /**
     * Delete all documents for a verification
     *
     * @param int $verificationId The verification ID to delete documents for
     *
     * @return int Number of documents deleted
     */
    public function deleteByVerificationId(int $verificationId): int
    {
        try {
            $documents = $this->documentRepository->findByVerificationId($verificationId);
            $deletedCount = 0;

            foreach ($documents as $doc) {
                if ($this->deleteDocument($doc['id_kyc_document'])) {
                    ++$deletedCount;
                }
            }

            return $deletedCount;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Document deletion by verification error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return 0;
        }
    }
}
