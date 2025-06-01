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
use PrestaShop\Module\Pskyc\Repository\CustomerRepository;
use PrestaShop\Module\Pskyc\Repository\DocumentRepository;
use PrestaShop\Module\Pskyc\Repository\LogRepository;
use PrestaShop\Module\Pskyc\Repository\VerificationRepository;

/**
 * MaintenanceService
 *
 * Handles all maintenance tasks for the KYC module:
 * - Send expiry warning emails
 * - Update expired verifications
 * - Cleanup expired documents and files
 * - Cleanup old logs and temporary files
 */
class MaintenanceService
{
    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * @var VerificationRepository
     */
    private $verificationRepository;

    /**
     * @var DocumentRepository
     */
    private $documentRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var LogRepository
     */
    private $logRepository;

    /**
     * @var string
     */
    private $uploadDir;

    public function __construct(
        DocumentService $documentService,
        NotificationService $notificationService,
        VerificationRepository $verificationRepository,
        DocumentRepository $documentRepository,
        CustomerRepository $customerRepository,
        LogRepository $logRepository,
        ?string $uploadDir,
    ) {
        $this->documentService = $documentService;
        $this->notificationService = $notificationService;
        $this->verificationRepository = $verificationRepository;
        $this->documentRepository = $documentRepository;
        $this->customerRepository = $customerRepository;
        $this->logRepository = $logRepository;
        $this->uploadDir = $uploadDir ?: _PS_MODULE_DIR_ . 'pskyc/secure_upload';
    }

    /**
     * Run all daily maintenance tasks
     *
     * @return array Results of all maintenance tasks
     */
    public function runDailyMaintenance(): array
    {
        $startTime = microtime(true);
        $results = [
            'start_time' => date('Y-m-d H:i:s'),
            'tasks' => [],
            'total_execution_time' => 0,
            'success' => true,
            'errors' => [],
        ];

        try {
            // Task 1: Send expiry warnings
            $results['tasks']['expiry_warnings'] = $this->sendExpiryWarnings();

            // Task 2: Update expired verifications
            $results['tasks']['expired_verifications'] = $this->updateExpiredVerifications();

            // Task 3: Cleanup expired documents and files
            $results['tasks']['cleanup_documents'] = $this->cleanupExpiredDocuments();

            // Task 4: Cleanup old logs
            $results['tasks']['cleanup_logs'] = $this->cleanupOldLogs();

            // Task 5: Cleanup temporary files
            $results['tasks']['cleanup_temp_files'] = $this->cleanupTempFiles();
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            \PrestaShopLogger::addLog('KYC Maintenance Error: ' . $e->getMessage(), 3, null, 'Pskyc');
        }

        $results['total_execution_time'] = round(microtime(true) - $startTime, 2);
        $results['end_time'] = date('Y-m-d H:i:s');

        return $results;
    }

    /**
     * Send expiry warning emails to customers
     *
     * @param int|null $warningDays Number of days before expiry to send warning (defaults to config)
     *
     * @return array Results of the warning email task
     */
    public function sendExpiryWarnings(?int $warningDays): array
    {
        $warningDays = $warningDays ?? (int) \Configuration::get('PSKYC_EXPIRY_WARNING_DAYS', 30);
        $results = [
            'warnings_sent' => 0,
            'errors' => [],
            'customers_notified' => [],
        ];

        try {
            $expiringVerifications = $this->verificationRepository->findExpiringVerifications($warningDays);

            foreach ($expiringVerifications as $verification) {
                try {
                    $customer = $this->getCustomerData($verification['id_customer']);
                    if (!$customer) {
                        continue;
                    }

                    $daysUntilExpiry = (int) ceil((strtotime($verification['date_expiry']) - time()) / 86400);

                    $sent = $this->notificationService->sendExpiryWarning(
                        $verification,
                        $customer,
                        $daysUntilExpiry
                    );

                    if ($sent) {
                        ++$results['warnings_sent'];
                        $results['customers_notified'][] = [
                            'customer_id' => $customer['id_customer'],
                            'verification_id' => $verification['id_kyc_verification'],
                            'days_until_expiry' => $daysUntilExpiry,
                        ];
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Warning for verification {$verification['id_kyc_verification']}: " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Update verifications that have expired
     *
     * @return array Results of the expired verifications update task
     */
    public function updateExpiredVerifications(): array
    {
        $results = [
            'verifications_expired' => 0,
            'errors' => [],
            'notifications_sent' => 0,
        ];

        try {
            $expiredVerifications = $this->verificationRepository->findExpiredVerifications();

            foreach ($expiredVerifications as $verification) {
                try {
                    $updated = $this->verificationRepository->updateStatus(
                        $verification['id_kyc_verification'],
                        'expired',
                        'Automatically expired on ' . date('Y-m-d H:i:s')
                    );

                    if ($updated) {
                        ++$results['verifications_expired'];

                        // Send expiry notification to customer
                        $customer = $this->getCustomerData($verification['id_customer']);
                        if ($customer) {
                            $verification['status'] = 'expired';
                            $sent = $this->notificationService->sendStatusChangeNotification(
                                $verification,
                                $customer,
                                'approved'
                            );
                            if ($sent) {
                                ++$results['notifications_sent'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Expiring verification {$verification['id_kyc_verification']}: " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Cleanup expired documents and their files
     *
     * @return array Results of the document cleanup task
     */
    public function cleanupExpiredDocuments(): array
    {
        $results = [
            'documents_deleted' => 0,
            'files_deleted' => 0,
            'errors' => [],
            'space_freed_mb' => 0,
        ];

        try {
            // Use the existing cleanup method from DocumentService
            $deletedCount = $this->documentService->cleanupExpiredDocuments();
            $results['documents_deleted'] = $deletedCount;

            // Additional cleanup for orphaned files
            $this->cleanupOrphanedFiles($results);
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Cleanup orphaned files that don't have database records
     *
     * @param array $results Results array to update by reference
     *
     * @return void
     */
    public function cleanupOrphanedFiles(array &$results): void
    {
        try {
            if (!is_dir($this->uploadDir)) {
                return;
            }

            $files = glob($this->uploadDir . '/doc_*');
            foreach ($files as $filePath) {
                $filename = basename($filePath);

                // Extract document ID from filename (format: doc_{id}_{hash})
                if (preg_match('/^doc_(\d+)_/', $filename, $matches)) {
                    $documentId = (int) $matches[1];

                    // Check if document exists in database
                    $document = $this->documentRepository->findById($documentId);
                    if (!$document) {
                        // File is orphaned, delete it
                        $fileSize = filesize($filePath);
                        if (unlink($filePath)) {
                            ++$results['files_deleted'];
                            $results['space_freed_mb'] += round($fileSize / 1048576, 2);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = 'Orphaned files cleanup: ' . $e->getMessage();
        }
    }

    /**
     * Cleanup old log entries
     *
     * @param int|null $retentionDays Number of days to retain logs (defaults to config)
     *
     * @return array Results of the log cleanup task
     */
    public function cleanupOldLogs(?int $retentionDays): array
    {
        $results = [
            'logs_deleted' => 0,
            'errors' => [],
        ];

        try {
            $retentionDays = $retentionDays ?? (int) \Configuration::get('PSKYC_LOG_RETENTION_DAYS', 0);

            // Only cleanup if retention is configured
            if ($retentionDays > 0) {
                $results['logs_deleted'] = $this->logRepository->deleteOldLogs($retentionDays);
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Cleanup temporary files and failed uploads
     *
     * @param int $maxAgeHours Maximum age in hours for temporary files (default: 24)
     *
     * @return array Results of the temporary files cleanup task
     */
    public function cleanupTempFiles(int $maxAgeHours = 24): array
    {
        $results = [
            'temp_files_deleted' => 0,
            'errors' => [],
            'space_freed_mb' => 0,
        ];

        try {
            if (!is_dir($this->uploadDir)) {
                return $results;
            }

            // Clean up temporary files older than specified hours
            $tempFiles = glob($this->uploadDir . '/doc_tmp_*');
            $cutoffTime = time() - ($maxAgeHours * 3600);

            foreach ($tempFiles as $filePath) {
                if (filemtime($filePath) < $cutoffTime) {
                    $fileSize = filesize($filePath);
                    if (unlink($filePath)) {
                        ++$results['temp_files_deleted'];
                        $results['space_freed_mb'] += round($fileSize / 1048576, 2);
                    }
                }
            }
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get customer data using the customer repository
     *
     * @param int $customerId Customer ID
     *
     * @return array|null Customer data or null if not found
     */
    private function getCustomerData(int $customerId): ?array
    {
        try {
            $customerData = $this->customerRepository->getCustomerData($customerId);

            return $customerData ?: null;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Get customer data error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return null;
        }
    }

    /**
     * Log maintenance run results
     *
     * @param array $results Results from runDailyMaintenance
     *
     * @return void
     */
    public function logMaintenanceRun(array $results): void
    {
        try {
            $summary = sprintf(
                'Daily maintenance completed in %s seconds. Warnings: %d, Expired: %d, Documents cleaned: %d, Files deleted: %d',
                $results['total_execution_time'],
                $results['tasks']['expiry_warnings']['warnings_sent'] ?? 0,
                $results['tasks']['expired_verifications']['verifications_expired'] ?? 0,
                $results['tasks']['cleanup_documents']['documents_deleted'] ?? 0,
                ($results['tasks']['cleanup_documents']['files_deleted'] ?? 0) + ($results['tasks']['cleanup_temp_files']['temp_files_deleted'] ?? 0)
            );

            if ($results['success']) {
                \PrestaShopLogger::addLog('KYC Daily Maintenance: ' . $summary, 1, null, 'Pskyc');
            } else {
                \PrestaShopLogger::addLog('KYC Daily Maintenance Failed: ' . $summary . ' Errors: ' . implode(', ', $results['errors']), 3, null, 'Pskyc');
            }
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC Maintenance Logging Error: ' . $e->getMessage(), 3, null, 'Pskyc');
        }
    }

    /**
     * Get the cron security token
     *
     * Uses the shop's encryption key to generate a unique, secure token
     * This ensures each installation has a different token while being predictable
     * for legitimate cron job setup
     *
     * @return string 10-character security token
     */
    public function getCronToken(): string
    {
        $encryptionKey = \Configuration::get('PSKYC_ENCRYPTION_KEY');

        // Fallback in case encryption key is missing (shouldn't happen in normal operation)
        if (empty($encryptionKey)) {
            \PrestaShopLogger::addLog('PSKYC encryption key missing for cron token generation', 3, null, 'Pskyc');
            throw new \PrestaShopException('Encryption key not available');
        }

        // Generate token using the encryption key + cron identifier
        $tokenData = $encryptionKey . 'pskyc_cron_token';

        return substr(\Tools::hash($tokenData), 0, 10);
    }

    /**
     * Generate the complete cron URL with token for a specific action
     *
     * @param string $action The maintenance action to run ('daily_maintenance', 'cleanup_documents', etc.)
     *
     * @return string Complete cron URL with security token
     */
    public function generateCronUrl(string $action = 'daily_maintenance'): string
    {
        $token = $this->getCronToken();
        $shopUrl = \Tools::getShopDomainSsl(true, true);

        // Build the cron URL pointing to your module's cron endpoint
        return sprintf(
            '%s/modules/pskyc/cron.php?token=%s&action=%s',
            rtrim($shopUrl, '/'),
            $token,
            urlencode($action)
        );
    }
}
