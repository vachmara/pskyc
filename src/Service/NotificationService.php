<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

namespace PrestaShop\Module\Pskyc\Service;

use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class NotificationService
 *
 * Handles email notifications for KYC verification status changes using PrestaShop 8's modern email theme system
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
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'verification_id' => $verification['id_kyc_verification'],
                'status' => $verification['status'],
                'previous_status' => $previousStatus,
                'date_submitted' => $verification['date_submitted'],
                'date_validated' => $verification['date_validated'] ?? null,
                'date_expiry' => $verification['date_expiry'] ?? null,
                'admin_note' => $verification['admin_note'] ?? '',
                'shop_name' => \Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true),
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
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'verification_id' => $verification['id_kyc_verification'],
                'document_count' => count($documents),
                'documents' => $documents,
                'date_submitted' => $verification['date_submitted'],
                'shop_name' => \Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true),
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
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'customer_email' => $customer['email'],
                'customer_id' => $customer['id_customer'],
                'verification_id' => $verification['id_kyc_verification'],
                'date_submitted' => $verification['date_submitted'],
                'admin_url' => $this->context->link->getAdminLink('AdminModules') . '&configure=pskyc',
                'shop_name' => \Configuration::get('PS_SHOP_NAME'),
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
                'customer_name' => $customer['firstname'] . ' ' . $customer['lastname'],
                'verification_id' => $verification['id_kyc_verification'],
                'days_until_expiry' => $daysUntilExpiry,
                'expiry_date' => $verification['date_expiry'],
                'shop_name' => \Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true),
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
     * Send bulk notifications to multiple recipients
     *
     * @param array $recipients Array of recipients with email and name
     * @param string $template Email template name
     * @param string $subject Email subject
     * @param array $templateVars Template variables
     *
     * @return array Results with success count and failed emails
     */
    public function sendBulkNotification(array $recipients, string $template, string $subject, array $templateVars): array
    {
        $results = [
            'success_count' => 0,
            'failed_emails' => [],
            'total_sent' => count($recipients),
        ];

        foreach ($recipients as $recipient) {
            $recipientName = $recipient['name'] ?? '';
            $success = $this->sendThemeEmail(
                $template,
                $subject,
                $templateVars,
                $recipient['email'],
                $recipientName,
                \Configuration::get('PS_LANG_DEFAULT')
            );

            if ($success) {
                ++$results['success_count'];
            } else {
                $results['failed_emails'][] = $recipient['email'];
            }
        }

        return $results;
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
     * Get admin emails from database
     *
     * @return array Array of admin email addresses and names
     */
    private function getAdminEmails(): array
    {
        try {
            $sql = 'SELECT e.email, CONCAT(e.firstname, " ", e.lastname) as name 
                    FROM ' . _DB_PREFIX_ . 'employee e 
                    WHERE e.active = 1';

            $db = \Db::getInstance();
            $adminEmails = $db->executeS($sql);

            if (empty($adminEmails)) {
                return [];
            }

            return array_map(function ($admin) {
                return [
                    'email' => $admin['email'],
                    'name' => $admin['name'],
                ];
            }, $adminEmails);
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
     * @param string $recipientName Recipient name
     * @param string|int $langId Language ID
     *
     * @return bool True if email was sent successfully
     */
    private function sendThemeEmail(
        string $template,
        string $subject,
        array $templateVars,
        string $recipientEmail,
        ?string $recipientName,
        $langId,
    ): bool {
        try {
            if (empty($recipientEmail)) {
                return false;
            }

            // Ensure langId is an integer
            $langId = (int) $langId;
            if ($langId <= 0) {
                $langId = (int) \Configuration::get('PS_LANG_DEFAULT');
            }

            // Use PrestaShop's Mail::Send method
            return \Mail::Send(
                $langId,
                $template,
                $subject,
                $templateVars,
                $recipientEmail,
                $recipientName,
                null, // from email (use default)
                null, // from name (use default)
                null, // file attachment
                null, // mode_smtp
                _PS_MODULE_DIR_ . 'pskyc/mails/', // template_path
                false, // die
                null, // id_shop
                null, // bcc
                null  // reply_to
            );
        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Theme email error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return false;
        }
    }
}
