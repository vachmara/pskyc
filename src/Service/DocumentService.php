<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Service\EncryptionService;

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
     * @var EncryptionService
     */
    private $encryptionService;

    /**
     * @var VerificationRepository
     */
    private $verificationRepository;

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
        'application/pdf'
    ];

    /**
     * DocumentService constructor
     * 
     * @param DocumentRepository $documentRepository Repository for document data operations
     * @param EncryptionService $encryptionService Service for file encryption/decryption
     * @param VerificationRepository $verificationRepository Repository for verification data operations
     */
    public function __construct(
        DocumentRepository $documentRepository,
        EncryptionService $encryptionService,
        VerificationRepository $verificationRepository
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
     * @return array Result array with success status and document ID or error message
     */
    public function uploadDocument(int $verificationId, array $fileData, string $documentType): array
    {
        try {
            // Validate file
            $validationResult = $this->validateUploadedFile($fileData);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message']
                ];
            }

            // Verify verification exists
            $verification = $this->verificationRepository->findById($verificationId);
            if (!$verification) {
                return [
                    'success' => false,
                    'message' => 'Invalid verification ID'
                ];
            }

            // Generate unique filename
            $extension = $this->getFileExtension($fileData['name']);
            $filename = uniqid('kyc_') . '_' . time() . '.' . $extension;
            $uploadPath = $this->getUploadDirectory() . '/' . $filename;

            // Encrypt and save file
            $encryptionResult = $this->encryptionService->encryptFile($fileData['tmp_name'], $uploadPath);

            // Save document metadata to database
            $documentData = [
                'verification_id' => $verificationId,
                'type' => $documentType,
                'filename' => $fileData['name'],
                'filesize' => $fileData['size'],
                'mime' => $this->getMimeType($fileData['tmp_name']),
                'sha256' => $encryptionResult['sha256'],
                'iv' => $encryptionResult['iv'],
                'encrypted' => 1,
                'expires_at' => $this->calculateExpiryDate()
            ];

            $documentId = $this->documentRepository->create($documentData);

            return [
                'success' => true,
                'document_id' => $documentId,
                'filename' => $filename
            ];

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Document upload error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'success' => false,
                'message' => 'Failed to upload document'
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
     * @return array Result array with success status and file data or error message
     */
    public function getDocument(int $documentId, bool $verifyIntegrity = true): array
    {
        try {
            $document = $this->documentRepository->findById($documentId);
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found'
                ];
            }

            $filePath = $this->getUploadDirectory() . '/' . $this->generateStoredFilename($document);
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'Document file not found on disk'
                ];
            }

            $decryptedData = $this->encryptionService->decryptFile($filePath, $document['iv']);

            if ($verifyIntegrity && !$this->encryptionService->verifyIntegrity($decryptedData, $document['sha256'])) {
                return [
                    'success' => false,
                    'message' => 'Document integrity verification failed'
                ];
            }

            return [
                'success' => true,
                'data' => $decryptedData,
                'filename' => $document['filename'],
                'mime' => $document['mime'],
                'size' => $document['filesize']
            ];

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Document retrieval error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'success' => false,
                'message' => 'Failed to retrieve document'
            ];
        }
    }

    /**
     * Delete a document and its file
     * 
     * Removes the document record and securely deletes the encrypted file
     * 
     * @param int $documentId The document ID to delete
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
            PrestaShopLogger::addLog('Document deletion error: ' . $e->getMessage(), 3, null, 'Pskyc');
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
                    $deletedCount++;
                }
            }

            return $deletedCount;

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Expired document cleanup error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return 0;
        }
    }

    /**
     * Validate uploaded file
     * 
     * Checks file size, type, and upload errors
     * 
     * @param array $fileData Uploaded file data from $_FILES
     * @return array Validation result with 'valid' boolean and 'message' string
     */
    private function validateUploadedFile(array $fileData): array
    {
        // Check for upload errors
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => 'File upload failed'
            ];
        }

        // Check file size
        if ($fileData['size'] > self::MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'message' => 'File size exceeds 10MB limit'
            ];
        }

        // Check MIME type
        $mimeType = $this->getMimeType($fileData['tmp_name']);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'message' => 'File type not allowed. Only JPG, PNG, and PDF files are accepted'
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
     * @return string The MIME type
     */
    private function getMimeType(string $filePath): string
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
     * @return string The file extension (without dot)
     */
    private function getFileExtension(string $filename): string
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
    private function getUploadDirectory(): string
    {
        return _PS_MODULE_DIR_ . 'pskyc/secure_upload';
    }

    /**
     * Generate stored filename
     * 
     * Creates the filename used to store the document on disk
     * 
     * @param array $document Document record from database
     * @return string The generated filename
     */
    private function generateStoredFilename(array $document): string
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
        $retentionDays = (int) Configuration::get('PSKYC_RETENTION_DAYS', 365);
        return date('Y-m-d H:i:s', strtotime('+' . $retentionDays . ' days'));
    }
}