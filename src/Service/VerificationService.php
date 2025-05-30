<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Repository\VerificationRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;
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
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * VerificationService constructor
     * 
     * @param VerificationRepository $verificationRepository Repository for verification data operations
     * @param DocumentRepository $documentRepository Repository for document data operations
     * @param LogRepository $logRepository Repository for audit log operations
     * @param DocumentService $documentService Service for document management
     * @param NotificationService $notificationService Service for sending notifications
     * @param CustomerRepository $customerRepository Repository for customer data operations
     */
    public function __construct(
        VerificationRepository $verificationRepository,
        DocumentRepository $documentRepository,
        LogRepository $logRepository,
        DocumentService $documentService,
        NotificationService $notificationService,
        CustomerRepository $customerRepository
    ) {
        $this->verificationRepository = $verificationRepository;
        $this->documentRepository = $documentRepository;
        $this->logRepository = $logRepository;
        $this->documentService = $documentService;
        $this->notificationService = $notificationService;
        $this->$customerRepository = $customerRepository;
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
     * Get verifications with pagination and filtering
     * 
     * @param array $filters Filters to apply (e.g., status, customer ID)
     * @param int $limit Number of records per page
     * @param int $offset Offset for pagination
     * @return array Array of verifications with total count
     */
    public function getVerifications(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        try {
            $verifications = $this->verificationRepository->findAll($filters, $limit, $offset);
            $totalCount = $this->verificationRepository->countAll($filters);

            // Check if any verification is expired
            foreach ($verifications as &$verification) {
                $verification['is_expired'] = $this->isVerificationExpired($verification);
            }

            return [
                'verifications' => $verifications,
                'total_count' => $totalCount
            ];
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get verifications error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [
                'verifications' => [],
                'total_count' => 0
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
     * Get status counts for all verifications
     * 
     * @return array Associative array of status counts
     */
    public function getStatusCounts(): array
    {
        try {
            return $this->verificationRepository->getStatusCounts();
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get status counts error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [];
        }
    }

    
    /**
     * Get all Verifications by customer ID
     * 
     * @param int $customerId The customer ID
     * @return array|null Returns an array of verifications or null if none found
     */
    public function getVerificationsByCustomerId(int $customerId): ?array
    {
        try {
            $verifications = $this->verificationRepository->findAllByCustomerId($customerId);
            if (empty($verifications)) {
                return null;
            }
            // Check if any verification is expired
            foreach ($verifications as &$verification) {
                $verification['is_expired'] = $this->isVerificationExpired($verification);
            }
            return $verifications;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get verifications by customer ID error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return null;
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
     * Update admin note for a verification
     * 
     * @param int $verificationId The verification ID
     * @param string $note The admin note to set
     * @return bool True on success, false on failure
     */
    public function updateAdminNote(int $verificationId, string $note): bool
    {
        try {
            $result = $this->verificationRepository->updateNote($verificationId, $note);
            if ($result) {
                // Log the note update
                $verification = $this->verificationRepository->findById($verificationId);
                $this->logAction(
                    $verificationId,
                    null,
                    null,
                    'admin_note_updated',
                    'Admin note updated: ' . $note
                );
            }
            return $result;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Update admin note error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return false;
        }
    }

    /**
     * Update verification status
     * 
     * @param int $verificationId The verification ID
     * @param string $newStatus The new status to set
     * @param string|null $note Optional admin note for the status change
     * @return bool True on success, false on failure
     */
    public function updateStatus(int $verificationId, string $newStatus, ?string $note = null): bool
    {
        try {
            // Get current verification data
            $verification = $this->verificationRepository->findById($verificationId);
            if (!$verification) {
                return false; // Verification not found
            }

            $previousStatus = $verification['status'];

            // Update status in the repository
            $result = $this->verificationRepository->updateStatus($verificationId, $newStatus, $note);

            if ($result) {
                // Log the status change
                $this->logAction(
                    $verificationId,
                    null,
                    null,
                    'status_updated',
                    'Verification status updated from ' . $previousStatus . ' to ' . $newStatus
                );

                // Get updated verification and customer data for notifications
                $updatedVerification = $this->verificationRepository->findById($verificationId);
                if ($updatedVerification) {
                    // Get customer data using CustomerRepository
                    
                    $customerData = $this->customerRepository->getCustomerData($updatedVerification['id_customer']);
                    
                    if ($customerData) {
                        // Send status change notification to customer
                        $this->notificationService->sendStatusChangeNotification(
                            $updatedVerification,
                            $customerData,
                            $previousStatus
                        );
                    }
                }

                // If approved, set expiry date
                if ($newStatus === 'approved') {
                    $expiryDate = $this->calculateExpiryDate();
                    $this->verificationRepository->updateExpiryDate($verificationId, $expiryDate);
                }
            }

            return $result;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Update status error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return false;
        }
    }

    /**
     * Calculate expiry date for approved verification
     * 
     * Returns the date when an approved verification should expire
     * 
     * @return string|null Returns the expiry date in 'Y-m-d H:i:s' format or null if no expiry
     */
    private function calculateExpiryDate(): string|null
    {
        $validityDays = (int) Configuration::get('PSKYC_RETENTION_DAYS');
        if ($validityDays <= 0) {
            // If 0 or negative, set expiry to NULL (no expiry)
            return null;
        }
        $now = new \DateTimeImmutable('now');
        $expiry = $now->add(new \DateInterval('P' . $validityDays . 'D'));
        return $expiry->format('Y-m-d H:i:s');
    }

    /**
     * Delete all the verifications and associated documents for a customer
     * 
     * @param int $customerId The customer ID
     * @return bool True on success, false on failure
     */
    public function deleteVerificationsByCustomerId(int $customerId): bool
    {
        try {
            // Get all verifications for the customer
            $verifications = $this->verificationRepository->findAllByCustomerId($customerId);
            if (empty($verifications)) {
                return true; // Nothing to delete
            }

            foreach ($verifications as $verification) {
                // Delete associated documents
                $this->documentRepository->deleteByVerificationId($verification['id_kyc_verification']);
                // Delete the verification itself
                $this->verificationRepository->delete($verification['id_kyc_verification']);
                // Log the deletion
                $this->logAction(
                    $verification['id_kyc_verification'],
                    $customerId,
                    null,
                    'verification_deleted',
                    'Verification deleted for customer ID: ' . $customerId
                );
            }

            return true;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Delete verifications error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return false;
        }
    }

    /**
     * Get GDPR data concerning a customer's verifications and documents
     * 
     * @param int $customerId The customer ID
     * @return array|null Returns an array with verification and document data or null if not found
     */
    public function getGdprData(int $customerId): ?array
    {
        try {
            // Get all verifications for the customer
            $verifications = $this->verificationRepository->findAllByCustomerId($customerId);
            if (empty($verifications)) {
                return null; // No verifications found
            }

            $gdprData = [];
            foreach ($verifications as $verification) {
                $documents = $this->documentRepository->findByVerificationId($verification['id_kyc_verification']);
                $gdprData[] = [
                    'verification' => $verification,
                    'documents' => $documents
                ];
            }

            return $gdprData;
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('Get GDPR data error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return null;
        }
    }
}