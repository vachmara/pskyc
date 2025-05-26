<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Service\EncryptionService;

class DocumentService
{
    private $documentRepository;
    private $verificationRepository;
    private $encryptionService;
    private $uploadDir;

    public function __construct(
        DocumentRepository $documentRepository,
        EncryptionService $encryptionService,
        VerificationRepository $verificationRepository
    ) {
        $this->documentRepository = $documentRepository;
        $this->encryptionService = $encryptionService;
        $this->verificationRepository = $verificationRepository;
        $this->uploadDir = _PS_MODULE_DIR_ . 'pskyc/secure_upload/';
    }

    /**
     * Upload and encrypt document
     */
    public function uploadDocument($verificationId, $uploadedFile, $documentType, $category = 'general')
    {
        // Validate file
        $this->validateFile($uploadedFile);

        // Read file content
        $content = file_get_contents($uploadedFile['tmp_name']);
        $hash = $this->encryptionService->sha256($content);

        // Generate encryption keys
        $key = $this->encryptionService->generateKey();
        $iv = $this->encryptionService->generateIv();

        // Create unique filename
        $filename = $this->generateSecureFilename($uploadedFile['name']);
        $filepath = $this->uploadDir . $filename;

        // Encrypt and save
        if (!$this->encryptionService->saveEncrypted($filepath, $content, $key, $iv)) {
            throw new \RuntimeException('Failed to save encrypted file');
        }

        // Store document record in database
        $documentId = $this->documentRepository->create([
            'id_kyc_verification' => $verificationId,
            'document_type' => $documentType,
            'category' => $category,
            'filename' => $filename,
            'original_name' => $uploadedFile['name'],
            'filesize' => $uploadedFile['size'],
            'mime_type' => $uploadedFile['type'],
            'sha256_hash' => $hash,
            'encryption_key' => $key,
            'iv' => $iv,
            'status' => 'uploaded',
            'date_uploaded' => date('Y-m-d H:i:s')
        ]);

        return $documentId;
    }

    /**
     * Retrieve and decrypt document
     */
    public function getDecryptedDocument($documentId)
    {
        $document = $this->documentRepository->findDocumentById($documentId);
        if (!$document) {
            throw new \RuntimeException('Document not found');
        }

        $filepath = $this->uploadDir . $document['filename'];
        $content = $this->encryptionService->readDecrypted(
            $filepath,
            $document['encryption_key'],
            $document['iv']
        );

        return [
            'content' => $content,
            'filename' => $document['original_name'],
            'mime_type' => $document['mime_type']
        ];
    }

    /**
     * Update document status (approved/rejected)
     */
    public function updateDocumentStatus($documentId, $status, $reviewNote = null, $employeeId = null)
    {
        return $this->documentRepository->updateStatus($documentId, $status, $reviewNote, $employeeId);
    }

    /**
     * Get documents by verification ID
     */
    public function getDocumentsByVerification($verificationId)
    {
        return $this->documentRepository->findDocumentsByVerificationId($verificationId);
    }

    /**
     * Delete document (both file and database record)
     */
    public function deleteDocument($documentId)
    {
        $document = $this->documentRepository->findDocumentById($documentId);
        if ($document) {
            // Delete encrypted file
            $filepath = $this->uploadDir . $document['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Delete database record
            return $this->documentRepository->delete($documentId);
        }

        return false;
    }

    private function validateFile($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('File upload failed');
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $maxSize) {
            throw new \InvalidArgumentException('File size must be less than 10MB');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes)) {
            throw new \InvalidArgumentException('Only JPG, PNG, and PDF files are allowed');
        }
    }

    private function generateSecureFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}