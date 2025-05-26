<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;

class VerificationService
{
    private $verificationRepository;
    private $documentRepository;
    private $logRepository;
    private $documentService;
    private $notificationService;

    public function __construct(
        VerificationRepository $verificationRepository,
        DocumentRepository $documentRepository,
        LogRepository $logRepository,
        DocumentService $documentService,
        NotificationService $notificationService
    ) {
        $this->verificationRepository = $verificationRepository;
        $this->documentRepository = $documentRepository;
        $this->logRepository = $logRepository;
        $this->documentService = $documentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new verification with documents
     */
    public function createVerification($customerId, array $documents, array $metadata = [])
    {
        // Business logic: Create verification
        $verificationId = $this->verificationRepository->createVerification($customerId);
        
        if (!$verificationId) {
            throw new \RuntimeException('Failed to create verification');
        }

        // Process each document using DocumentService
        $uploadedDocuments = [];
        foreach ($documents as $type => $documentData) {
            $documentId = $this->documentService->uploadDocument(
                $verificationId,
                $documentData['file'],
                $type,
                $documentData['category'] ?? 'general'
            );
            
            if ($documentId) {
                $uploadedDocuments[] = $documentId;
            }
        }

        // Log the action
        $this->logRepository->createLog(
            $verificationId,
            null, // employee_id
            $customerId,
            'verification_created',
            'Customer created new verification request',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        // Send notification
        $this->notificationService->sendVerificationSubmitted($customerId, $verificationId);
        $this->notificationService->sendAdminNotification($verificationId, $customerId);

        return [
            'verification_id' => $verificationId,
            'documents' => $uploadedDocuments,
            'success' => true
        ];
    }

    /**
     * Get customer's latest verification with documents
     */
    public function getCustomerVerification($customerId)
    {
        $verification = $this->verificationRepository->getLatestByCustomerId($customerId);
        
        if (!$verification) {
            return null;
        }

        $documents = $this->documentService->getDocumentsByVerification($verification['id']);
        
        return [
            'verification' => $verification,
            'documents' => $documents
        ];
    }

    /**
     * Update verification status
     */
    public function updateVerificationStatus($verificationId, $status, $adminNote = null, $employeeId = null)
    {
        $result = $this->verificationRepository->updateVerificationStatus($verificationId, $status, $adminNote);
        
        if ($result) {
            // Log the status change
            $this->logRepository->createLog(
                $verificationId,
                $employeeId,
                null, // customer_id not needed here
                'verification_status_updated',
                'Verification status updated to ' . $status . ($adminNote ? ' with note: ' . $adminNote : ''),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            // Send notification to customer
            $verification = $this->verificationRepository->findVerificationById($verificationId);
            if ($verification) {
                $this->notificationService->sendStatusUpdate(
                    $verification['id_customer'],
                    $status,
                    $adminNote
                );
            }
        }

        return $result;
    }

    /**
     * Get verification by ID
     */
    public function getVerificationById($verificationId)
    {
        $verification = $this->verificationRepository->findVerificationById($verificationId);
        
        if (!$verification) {
            return null;
        }

        $documents = $this->documentService->getDocumentsByVerification($verificationId);
        
        return [
            'verification' => $verification,
            'documents' => $documents
        ];
    }

    /**
     * Delete verification and all associated documents
     */
    public function deleteVerification($verificationId, $employeeId = null)
    {
        // Get documents first
        $documents = $this->documentService->getDocumentsByVerification($verificationId);
        
        // Delete all documents (files and records)
        foreach ($documents as $document) {
            $this->documentService->deleteDocument($document['id_kyc_document']);
        }

        // Log the deletion
        if ($employeeId) {
            $this->logRepository->createLog(
                $verificationId,
                $employeeId,
                null,
                'verification_deleted',
                'Verification and all documents deleted by admin',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );
        }

        // Delete verification record
        return $this->verificationRepository->delete($verificationId);
    }

    /**
     * Check if customer has pending verification
     */
    public function hasActivePendingVerification($customerId)
    {
        $verification = $this->verificationRepository->getLatestByCustomerId($customerId);
        
        return $verification && in_array($verification['status'], ['pending', 'under_review']);
    }

    /**
     * Get all verifications for admin with pagination
     */
    public function getAllVerifications($offset = 0, $limit = 20, $filters = [])
    {
        return $this->verificationRepository->findAllWithPagination($offset, $limit, $filters);
    }
}