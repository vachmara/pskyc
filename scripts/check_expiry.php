<?php
/**
 * KYC Expiry Check Cron Job
 * 
 * This script should be run daily via cron to check for:
 * - Verifications nearing expiry (30 days warning)
 * - Expired verifications that need status update
 * 
 * Crontab example:
 * 0 9 * * * /usr/bin/php /path/to/prestashop/modules/pskyc/scripts/check_expiry.php
 */

// Prevent direct access
if (php_sapi_name() !== 'cli') {
    exit('This script can only be run from command line.');
}

// Include PrestaShop config
$prestashopPath = dirname(dirname(dirname(__DIR__)));
require_once $prestashopPath . '/config/config.inc.php';
require_once $prestashopPath . '/init.php';

// Set up logging
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
    PrestaShopLogger::addLog("KYC Expiry Check: {$message}", 1, null, 'Pskyc');
}

try {
    logMessage("Starting KYC expiry check...");
    
    // Get module instance
    $module = Module::getInstanceByName('pskyc');
    if (!$module || !$module->active) {
        logMessage("KYC module not found or not active. Exiting.");
        exit(1);
    }
    
    // Get services
    $verificationService = $module->get('PrestaShop\Module\Pskyc\Service\VerificationService');
    $notificationService = $module->get('PrestaShop\Module\Pskyc\Service\NotificationService');
    $verificationRepository = $module->get('PrestaShop\Module\Pskyc\Repository\VerificationRepository');
    $customerRepository = $module->get('PrestaShop\Module\Pskyc\Repository\CustomerRepository');
    
    // Check for verifications expiring in 30 days
    $expiringVerifications = $verificationRepository->findExpiringVerifications(30);
    $warningsSent = 0;
    
    foreach ($expiringVerifications as $verification) {
        $customerData = $customerRepository->getCustomerData($verification['id_customer']);
        if ($customerData) {
            $daysUntilExpiry = ceil((strtotime($verification['date_expiry']) - time()) / (60 * 60 * 24));
            
            $result = $notificationService->sendExpiryWarning($verification, $customerData, $daysUntilExpiry);
            if ($result) {
                $warningsSent++;
                logMessage("Expiry warning sent to customer {$verification['id_customer']} (verification #{$verification['id_kyc_verification']})");
            } else {
                logMessage("Failed to send expiry warning to customer {$verification['id_customer']}");
            }
        }
    }
    
    // Check for expired verifications that need status update
    $expiredVerifications = $verificationRepository->findExpiredVerifications();
    $expiredUpdated = 0;
    
    foreach ($expiredVerifications as $verification) {
        $result = $verificationService->updateStatus($verification['id_kyc_verification'], 'expired', 'Automatically expired due to expiry date');
        if ($result) {
            $expiredUpdated++;
            logMessage("Verification #{$verification['id_kyc_verification']} marked as expired");
        } else {
            logMessage("Failed to mark verification #{$verification['id_kyc_verification']} as expired");
        }
    }
    
    logMessage("KYC expiry check completed successfully:");
    logMessage("- Expiry warnings sent: {$warningsSent}");
    logMessage("- Verifications marked as expired: {$expiredUpdated}");
    
} catch (Exception $e) {
    logMessage("Error during expiry check: " . $e->getMessage());
    PrestaShopLogger::addLog("KYC Expiry Check Error: " . $e->getMessage(), 3, null, 'Pskyc');
    exit(1);
}

exit(0);