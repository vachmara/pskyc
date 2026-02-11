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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class NotificationService
 *
 * Handles email notifications for KYC verification status changes using PrestaShop 8/9's modern email theme system
 * Sends automated emails to customers and administrators
 */
class NotificationService
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var \Context
     */
    private $context;

    /**
     * NotificationService constructor
     *
     * @param TranslatorInterface $translator Translator service for internationalization
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->context = \Context::getContext();
    }

    /**
     * Send verification status change notification to customer
     *
     * Notifies the customer when their KYC verification status changes
     *
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     * @param string|null $previousStatus Previous verification status (if applicable)
     *
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendStatusChangeNotification(array $verification, array $customer, ?string $previousStatus = null): bool
    {
        try {
            $templateVars = [
                '{firstname}' => $customer['firstname'],
                '{lastname}' => $customer['lastname'],
                '{verification_id}' => $verification['id_kyc_verification'],
                '{status_label}' => $this->getStatusLabel($verification['status']),
                // Do not expose internal admin notes to customers
                '{date_submitted}' => $verification['date_submitted'],
            ];

            $subject = $this->getEmailSubjectForStatus($verification['status']);
            $customerLang = $customer['id_lang'] ?? \Configuration::get('PS_LANG_DEFAULT');

            return $this->sendThemeEmail(
                'verification_status',
                $subject,
                $templateVars,
                $customer['email'],
                $customer['firstname'] . ' ' . $customer['lastname'],
                $customerLang
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC notification error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Send document upload confirmation to customer
     *
     * Confirms that documents have been received and are being reviewed
     *
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     * @param array $documents Array of uploaded document records
     *
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendDocumentUploadConfirmation(array $verification, array $customer, array $documents): bool
    {
        try {
            $templateVars = [
                '{firstname}' => $customer['firstname'],
                '{lastname}' => $customer['lastname'],
                '{verification_id}' => $verification['id_kyc_verification'],
                '{document_count}' => count($documents),
                '{upload_date}' => $verification['date_submitted'],
                '{verification_status_url}' => $this->context->shop->getBaseURL(true),
            ];

            $subject = $this->translator->trans(
                'KYC Documents Received - Verification #%id%',
                ['%id%' => $verification['id_kyc_verification']],
                'Modules.Pskyc.Shop'
            );

            $customerLang = $customer['id_lang'] ?? \Configuration::get('PS_LANG_DEFAULT');

            return $this->sendThemeEmail(
                'document_upload_confirmation',
                $subject,
                $templateVars,
                $customer['email'],
                $customer['firstname'] . ' ' . $customer['lastname'],
                $customerLang
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC upload confirmation error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Send admin notification for new verification request
     *
     * Notifies administrators when a new KYC verification is submitted
     *
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     *
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendAdminNotification(array $verification, array $customer): bool
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return false;
            }

            $templateVars = [
                '{customer_name}' => $customer['firstname'] . ' ' . $customer['lastname'],
                '{customer_email}' => $customer['email'],
                '{customer_id}' => $customer['id_customer'],
                '{verification_id}' => $verification['id_kyc_verification'],
                '{document_count}' => $verification['document_count'] ?? 0,
                '{admin_verification_url}' => $this->context->shop->getBaseURL(true),
            ];

            $subject = $this->translator->trans(
                'New KYC Verification Request - #%id%',
                ['%id%' => $verification['id_kyc_verification']],
                'Modules.Pskyc.Admin'
            );

            $success = true;
            foreach ($adminEmails as $adminEmail) {
                $result = $this->sendThemeEmail(
                    'admin_new_verification',
                    $subject,
                    $templateVars,
                    $adminEmail['email'],
                    $adminEmail['name'],
                    \Configuration::get('PS_LANG_DEFAULT')
                );
                $success = $success && $result;
            }

            return $success;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC admin notification error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Send admin notification for verification status change
     *
     * Notifies administrators when a customer's verification status is updated
     *
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     *
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendAdminStatusChangeNotification(array $verification, array $customer): bool
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return false;
            }

            $templateVars = [
                '{customer_name}' => $customer['firstname'] . ' ' . $customer['lastname'],
                '{customer_email}' => $customer['email'],
                '{customer_id}' => $customer['id_customer'],
                '{verification_id}' => $verification['id_kyc_verification'],
                '{status_label}' => $this->getStatusLabel($verification['status']),
                '{status_message}' => $verification['admin_note'] ?? '',
                '{admin_verification_url}' => $this->context->shop->getBaseURL(true),
            ];

            $subject = $this->getEmailSubjectForStatus($verification['status']);

            $success = true;
            foreach ($adminEmails as $adminEmail) {
                $result = $this->sendThemeEmail(
                    'admin_verification_status',
                    $subject,
                    $templateVars,
                    $adminEmail['email'],
                    $adminEmail['name'],
                    \Configuration::get('PS_LANG_DEFAULT')
                );
                $success = $success && $result;
            }

            return $success;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC admin notification error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Send expiry warning notification to customer
     *
     * Warns customers that their documents will expire soon
     *
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     * @param int $daysUntilExpiry Number of days until expiry
     *
     * @return bool True if email was sent successfully, false otherwise
     */
    public function sendExpiryWarning(array $verification, array $customer, int $daysUntilExpiry): bool
    {
        try {
            $templateVars = [
                '{firstname}' => $customer['firstname'],
                '{lastname}' => $customer['lastname'],
                '{verification_id}' => $verification['id_kyc_verification'],
                '{days_remaining}' => $daysUntilExpiry,
                '{expiry_date}' => $verification['date_expiry'],
                '{renewal_url}' => $this->context->shop->getBaseURL(true),
            ];

            $subject = $this->translator->trans(
                'KYC Verification Expiry Warning - #%id%',
                ['%id%' => $verification['id_kyc_verification']],
                'Modules.Pskyc.Shop'
            );

            $customerLang = $customer['id_lang'] ?? \Configuration::get('PS_LANG_DEFAULT');

            return $this->sendThemeEmail(
                'verification_expiry_warning',
                $subject,
                $templateVars,
                $customer['email'],
                $customer['firstname'] . ' ' . $customer['lastname'],
                $customerLang
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('KYC expiry warning error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }

    /**
     * Get human-readable status label
     *
     * @param string $status Verification status
     *
     * @return string Translated status label
     */
    private function getStatusLabel(string $status): string
    {
        switch ($status) {
            case 'approved':
                return $this->translator->trans('Approved', [], 'Modules.Pskyc.Shop');
            case 'rejected':
                return $this->translator->trans('Rejected', [], 'Modules.Pskyc.Shop');
            case 'pending':
                return $this->translator->trans('Pending Review', [], 'Modules.Pskyc.Shop');
            case 'expired':
                return $this->translator->trans('Expired', [], 'Modules.Pskyc.Shop');
            case 'requested_more_info':
                return $this->translator->trans('More Information Required', [], 'Modules.Pskyc.Shop');
            default:
                return $this->translator->trans('Unknown', [], 'Modules.Pskyc.Shop');
        }
    }

    /**
     * Get appropriate email subject based on verification status
     *
     * @param string $status Verification status
     *
     * @return string Translated email subject
     */
    private function getEmailSubjectForStatus(string $status): string
    {
        switch ($status) {
            case 'approved':
                return $this->translator->trans(
                    'KYC Verification Approved',
                    [],
                    'Modules.Pskyc.Shop'
                );
            case 'rejected':
                return $this->translator->trans(
                    'KYC Verification Rejected',
                    [],
                    'Modules.Pskyc.Shop'
                );
            case 'expired':
                return $this->translator->trans(
                    'KYC Verification Expired',
                    [],
                    'Modules.Pskyc.Shop'
                );
            case 'pending':
            default:
                return $this->translator->trans(
                    'KYC Verification Status Update',
                    [],
                    'Modules.Pskyc.Shop'
                );
        }
    }

    /**
     * Get admin emails from PSKYC configuration
     *
     * Uses PSKYC_ADMIN_EMAILS configuration setting with fallback to shop email
     *
     * @return array Array of admin email addresses and names
     */
    private function getAdminEmails(): array
    {
        try {
            // Get configured admin emails
            $adminEmailsConfig = \Configuration::get('PSKYC_ADMIN_EMAILS');

            if (empty($adminEmailsConfig)) {
                // Fallback to shop email
                $shopEmail = \Configuration::get('PS_SHOP_EMAIL');
                if (empty($shopEmail)) {
                    return [];
                }

                return [
                    [
                        'email' => $shopEmail,
                        'name' => \Configuration::get('PS_SHOP_NAME'),
                    ],
                ];
            }

            // Parse comma-separated emails
            $emails = array_map('trim', explode(',', $adminEmailsConfig));
            $adminEmails = [];

            foreach ($emails as $email) {
                if (\Validate::isEmail($email)) {
                    $adminEmails[] = [
                        'email' => $email,
                        'name' => 'Administrator', // Generic name for configured emails
                    ];
                }
            }

            // If no valid emails found, fallback to shop email
            if (empty($adminEmails)) {
                $shopEmail = \Configuration::get('PS_SHOP_EMAIL');
                if (!empty($shopEmail)) {
                    $adminEmails[] = [
                        'email' => $shopEmail,
                        'name' => \Configuration::get('PS_SHOP_NAME'),
                    ];
                }
            }

            return $adminEmails;
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Error getting admin emails: ' . $e->getMessage(), 3, null, 'Pskyc');

            return [];
        }
    }

    /**
     * Send email using PrestaShop's email system
     *
     * @param string $template Template name
     * @param string $subject Email subject
     * @param array $templateVars Template variables
     * @param string $recipientEmail Recipient email address
     * @param string|null $recipientName Recipient name
     * @param string|int|null $langId Language ID
     *
     * @return bool True if email was sent successfully
     */
    private function sendThemeEmail(
        string $template,
        string $subject,
        array $templateVars,
        string $recipientEmail,
        ?string $recipientName = null,
        $langId = null,
    ): bool {
        try {
            if (empty($recipientEmail)) {
                return false;
            }

            // Ensure langId is an integer
            $langId = (int) ($langId ?? \Configuration::get('PS_LANG_DEFAULT'));
            if ($langId <= 0) {
                $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
            }

            $langIso = \Language::getIsoById($langId);
            $themePath = _PS_THEME_DIR_ . 'modules/pskyc/mails/';
            $modulePath = _PS_MODULE_DIR_ . 'pskyc/mails/';

            $useModulePath = !file_exists($themePath . $langIso . '/' . $template . '.txt')
                || !file_exists($themePath . $langIso . '/' . $template . '.html');

            return \Mail::Send(
                $langId,
                $template,
                $subject,
                $templateVars,
                $recipientEmail,
                $recipientName,
                null,
                null,
                null,
                null,
                $useModulePath ? $modulePath : null,
                false,
                null,
                null,
                null
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Theme email error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }
}
