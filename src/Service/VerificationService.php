<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShopLogger;
use Configuration; 
/**
 * Class VerificationService
 * 
 * Business logic service for managing KYC verifications
 * Orchestrates the complete verification workflow from submission to approval/rejection
 */
class VerificationService
{
    /**
     * @var VerificationRepository
     */
    private $verificationRepository;

    /**
     * @var DocumentRepository
     */
    private $documentRepository;

    /**
     * @var LogRepository
     */
    private $logRepository;

    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * VerificationService constructor
     * 
     * @param VerificationRepository $verificationRepository Repository for verification data operations
     * @param DocumentRepository $documentRepository Repository for document data operations
     * @param LogRepository $logRepository Repository for audit log operations
     * @param DocumentService $documentService Service for document management
     * @param NotificationService $notificationService Service for sending notifications
     */
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
     * Create a new verification request
     * 
     * Initiates a new KYC verification process for a customer
     * 
     * @param int $customerId The customer ID
     * @param array $options Additional options for the verification
     * @return array Result array with success status and verification ID or error message
     */
    public function createVerification(int $customerId, array $options = []): array
    {
        try {
            // Check if customer already has an active verification
            $existingVerificationArray = $this->verificationRepository->findActiveByCustomerId($customerId);
            if (!empty($existingVerificationArray)) {
                $existingVerification = $existingVerificationArray[0]; // Get the first active verification
                return [
                    'success' => false,
                    'message' => 'Customer already has an active verification request',
                    'verification_id' => $existingVerification['id_kyc_verification']
                ];
            }
            
            $verificationId = $this->verificationRepository->create($customerId, 'pending', $options['customer_note'] ?? null);

            if ($verificationId) {
                // Log the creation
                $this->logAction($verificationId, $customerId, null, 'verification_created', 'New verification request created');

                return [
                    'success' => true,
                    'verification_id' => $verificationId
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to create verification request'
            ];

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Verification creation error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'success' => false,
                'message' => 'System error occurred while creating verification'
            ];
        }
    }

    /**
     * Update verification status
     * 
     * Changes the status of a verification and handles related actions
     * 
     * @param int $verificationId The verification ID
     * @param string $newStatus The new status
     * @param int|null $employeeId The admin employee ID making the change
     * @param string|null $adminNote Optional note from admin
     * @return array Result array with success status and message
     */
    public function updateStatus(int $verificationId, string $newStatus, ?int $employeeId = null, ?string $adminNote = null): array
    {
        try {
            $verification = $this->verificationRepository->findById($verificationId);
            if (!$verification) {
                return [
                    'success' => false,
                    'message' => 'Verification not found'
                ];
            }

            $previousStatus = $verification['status'];

            // Validate status transition
            if (!$this->isValidStatusTransition($previousStatus, $newStatus)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition from ' . $previousStatus . ' to ' . $newStatus
                ];
            }

            // Update verification record
            $updateData = [
                'status' => $newStatus,
                'admin_note' => $adminNote
            ];

            // Set validation date for approved status
            if ($newStatus === 'approved') {
                $updateData['date_validated'] = date('Y-m-d H:i:s');
                $updateData['date_expiry'] = $this->calculateExpiryDate();
            }

            $updated = $this->verificationRepository->update($verificationId, $updateData);

            if ($updated) {
                // Log the status change
                $logMessage = "Status changed from {$previousStatus} to {$newStatus}";
                if ($adminNote) {
                    $logMessage .= " - Note: {$adminNote}";
                }

                $this->logAction(
                    $verificationId,
                    $verification['id_customer'],
                    $employeeId,
                    'status_changed',
                    $logMessage
                );

                // Send notification to customer
                $customer = $this->getCustomerData($verification['id_customer']);
                if ($customer) {
                    $this->notificationService->sendStatusChangeNotification(
                        array_merge($verification, $updateData),
                        $customer,
                        $previousStatus
                    );
                }

                return [
                    'success' => true,
                    'message' => 'Verification status updated successfully'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to update verification status'
            ];

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Status update error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'success' => false,
                'message' => 'System error occurred while updating status'
            ];
        }
    }

    /**
     * Get verification with documents
     * 
     * Retrieves a verification record along with all associated documents
     * 
     * @param int $verificationId The verification ID
     * @return array|null Verification data with documents or null if not found
     */
    public function getVerificationWithDocuments(int $verificationId): ?array
    {
        try {
            $verification = $this->verificationRepository->findById($verificationId);
            if (!$verification) {
                return null;
            }

            $documents = $this->documentRepository->findByVerificationId($verificationId);
            $verification['documents'] = $documents;

            return $verification;

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get verification error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return null;
        }
    }

    /**
     * Get most recent verification for a customer
     * 
     * @param int $customerId The customer ID
     * @return array Array of verification records
     */
    public function getMostRecentVerification(int $customerId): array
    {
        try {
            return $this->verificationRepository->findByCustomerId(customerId: $customerId) ?: [];
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get customer verifications error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [];
        }
    }

    /**
     * Check if customer needs KYC verification
     * 
     * Determines if a customer needs to complete KYC verification
     * based on their order history and product categories
     * 
     * @param int $customerId The customer ID
     * @return array Result with 'required' boolean and 'reason' string
     */
    public function isKycRequired(int $customerId): array
    {
        try {
            // Check if customer already has approved verification
            $activeVerification = $this->verificationRepository->findActiveByCustomerId($customerId);
            if ($activeVerification && $activeVerification['status'] === 'approved') {
                // Check if verification is still valid (not expired)
                if (!$this->isVerificationExpired($activeVerification)) {
                    return [
                        'required' => false,
                        'reason' => 'Customer has valid KYC verification'
                    ];
                }
            }

            // Check if customer has ordered products that require KYC
            $requiresKyc = $this->checkOrderHistory($customerId);
            if ($requiresKyc) {
                return [
                    'required' => true,
                    'reason' => 'Customer has ordered products that require KYC verification'
                ];
            }

            return [
                'required' => false,
                'reason' => 'KYC verification not required for current customer activity'
            ];

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('KYC requirement check error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'required' => false,
                'reason' => 'Error checking KYC requirement'
            ];
        }
    }

    /**
     * Process pending verifications cleanup
     * 
     * Handles cleanup of old pending verifications and expired documents
     * 
     * @return array Cleanup statistics
     */
    public function processCleanup(): array
    {
        try {
            $stats = [
                'expired_verifications' => 0,
                'deleted_documents' => 0,
                'old_pending_verifications' => 0
            ];

            // Mark expired verifications
            $expiredCount = $this->verificationRepository->markExpiredVerifications();
            $stats['expired_verifications'] = $expiredCount;

            // Clean up old pending verifications (older than 30 days)
            $oldPendingCount = $this->verificationRepository->cleanupOldPendingVerifications();
            $stats['old_pending_verifications'] = $oldPendingCount;

            // Clean up expired documents
            $deletedDocsCount = $this->documentService->cleanupExpiredDocuments();
            $stats['deleted_documents'] = $deletedDocsCount;

            return $stats;

        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Cleanup process error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'expired_verifications' => 0,
                'deleted_documents' => 0,
                'old_pending_verifications' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Log an action in the audit trail
     * 
     * Records an action in the KYC log for audit purposes
     * 
     * @param int $verificationId The verification ID
     * @param int|null $customerId The customer ID (if customer action)
     * @param int|null $employeeId The employee ID (if admin action)
     * @param string $action The action performed
     * @param string $message Descriptive message about the action
     * @return void
     */
    private function logAction(int $verificationId, ?int $customerId, ?int $employeeId, string $action, string $message): void
    {
        try {
            $this->logRepository->createLog(
                $verificationId,
                $employeeId,
                $customerId,
                $action,
                $message,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            );
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Log action error: ' . $e->getMessage(), 3, null, 'Pskyc');
        }
    }

    /**
     * Validate status transition
     * 
     * Checks if a status transition is valid according to business rules
     * 
     * @param string $fromStatus Current status
     * @param string $toStatus Target status
     * @return bool True if transition is valid, false otherwise
     */
    private function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $validTransitions = [
            'pending' => ['under_review', 'rejected', 'approved'],
            'under_review' => ['approved', 'rejected', 'pending'],
            'rejected' => ['under_review', 'pending'],
            'approved' => ['expired'],
            'expired' => ['pending']
        ];

        return isset($validTransitions[$fromStatus]) && 
               in_array($toStatus, $validTransitions[$fromStatus]);
    }

    /**
     * Check if verification is expired
     * 
     * Determines if a verification has passed its expiry date
     * 
     * @param array $verification Verification record
     * @return bool True if expired, false otherwise
     */
    private function isVerificationExpired(array $verification): bool
    {
        if (empty($verification['date_expiry'])) {
            return false;
        }

        return strtotime($verification['date_expiry']) < time();
    }

    /**
     * Calculate expiry date for approved verification
     * 
     * Returns the date when an approved verification should expire
     * 
     * @return string Expiry date in MySQL datetime format
     */
    private function calculateExpiryDate(): string
    {
        $validityDays = (int) Configuration::get('PSKYC_VALIDITY_DAYS', 365);
        return date('Y-m-d H:i:s', strtotime('+' . $validityDays . ' days'));
    }
}