<?php

namespace PrestaShop\Module\Pskyc\Service;

use Context;
use Configuration;
use Symfony\Component\Translation\TranslatorInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\LayoutInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCatalogInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollectionInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\MailTemplate;
use PrestaShop\PrestaShop\Core\MailTemplate\MailTemplateInterface;
use PrestaShop\PrestaShop\Adapter\MailTemplate\MailPartialTemplateRenderer;
use Symfony\Component\Templating\EngineInterface;

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
     * @var Context
     */
    private $context;

    /**
     * @var EngineInterface
     */
    private $templating;

    /**
     * NotificationService constructor
     * 
     * @param TranslatorInterface $translator Translator service for internationalization
     * @param EngineInterface $templating Templating engine for email rendering
     */
    public function __construct(TranslatorInterface $translator, EngineInterface $templating = null)
    {
        $this->translator = $translator;
        $this->context = Context::getContext();
        $this->templating = $templating;
    }

    /**
     * Send verification status change notification to customer
     * 
     * Notifies the customer when their KYC verification status changes
     * 
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     * @param string|null $previousStatus Previous verification status (if applicable)
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
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true)
            ];

            $subject = $this->getEmailSubject($verification['status']);
            $customerLang = $customer['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

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
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true)
            ];

            $subject = $this->translator->trans(
                'KYC Documents Received - Verification #%id%',
                ['%id%' => $verification['id_kyc_verification']],
                'Modules.Pskyc.Shop'
            );

            $customerLang = $customer['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

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
                'shop_name' => Configuration::get('PS_SHOP_NAME')
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
                    Configuration::get('PS_LANG_DEFAULT')
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
     * Send document expiry warning to customer
     * 
     * Warns customers that their documents will expire soon
     * 
     * @param array $verification Verification record from database
     * @param array $customer Customer record from database
     * @param int $daysUntilExpiry Number of days until expiry
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
                'renewal_url' => $this->context->link->getModuleLink('pskyc', 'verify'),
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
                'shop_url' => $this->context->shop->getBaseURL(true)
            ];

            $subject = $this->translator->trans(
                'KYC Verification Expiring Soon',
                [],
                'Modules.Pskyc.Shop'
            );

            $customerLang = $customer['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

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
     * Send email using PrestaShop 8's theme system
     * 
     * @param string $template Template name (without .html.twig extension)
     * @param string $subject Email subject
     * @param array $templateVars Template variables
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param int $langId Language ID
     * @return bool True if email was sent successfully
     */
    private function sendThemeEmail(string $template, string $subject, array $templateVars, string $toEmail, string $toName, int $langId): bool
    {
        try {
            // Try modern theme approach first
            if ($this->templating && class_exists('PrestaShop\PrestaShop\Core\MailTemplate\MailTemplate')) {
                return $this->sendModernThemeEmail($template, $subject, $templateVars, $toEmail, $toName, $langId);
            }

            // Fallback to traditional Mail::Send with theme path
            return \Mail::Send(
                $langId,
                $template,
                $subject,
                $templateVars,
                $toEmail,
                $toName,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                _PS_MODULE_DIR_ . 'pskyc/mails/',
                false,
                null,
                null,
                _PS_MODULE_DIR_ . 'pskyc/mails/themes/pskyc/'
            );

        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Theme email send error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return false;
        }
    }

    /**
     * Send email using PrestaShop 8's modern MailTemplate system
     * 
     * @param string $template Template name
     * @param string $subject Email subject
     * @param array $templateVars Template variables
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param int $langId Language ID
     * @return bool True if email was sent successfully
     */
    private function sendModernThemeEmail(string $template, string $subject, array $templateVars, string $toEmail, string $toName, int $langId): bool
    {
        try {
            // Get language info
            $language = new \Language($langId);
            $templateVars['language'] = [
                'locale' => $language->locale ?? 'en',
                'id' => $langId
            ];
            
            // Add shop context
            $templateVars['shop'] = [
                'name' => Configuration::get('PS_SHOP_NAME'),
                'url' => $this->context->shop->getBaseURL(true)
            ];

            // Render the email template
            $templatePath = '@Modules/pskyc/mails/themes/pskyc/layouts/' . $template . '.html.twig';
            $htmlContent = $this->templating->render($templatePath, $templateVars);

            // Send using Swift Mailer or Mail class
            return \Mail::Send(
                $langId,
                null, // No template file needed as we have content
                $subject,
                [],
                $toEmail,
                $toName,
                Configuration::get('PS_SHOP_EMAIL'),
                Configuration::get('PS_SHOP_NAME'),
                null,
                null,
                null,
                false,
                null,
                null,
                null,
                $htmlContent // Pass rendered HTML content directly
            );

        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Modern theme email error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return false;
        }
    }

    /**
     * Get email subject based on verification status
     * 
     * Generates appropriate email subject for each status
     * 
     * @param string $status The verification status
     * @return string The translated email subject
     */
    private function getEmailSubject(string $status): string
    {
        $subjects = [
            'approved' => 'KYC Verification Approved',
            'rejected' => 'KYC Verification Rejected',
            'under_review' => 'KYC Verification Under Review',
            'expired' => 'KYC Verification Expired',
            'pending' => 'KYC Verification Received'
        ];

        $subjectKey = $subjects[$status] ?? 'KYC Verification Status Updated';
        
        return $this->translator->trans($subjectKey, [], 'Modules.Pskyc.Shop');
    }

    /**
     * Get administrator email addresses
     * 
     * Retrieves email addresses of administrators who should receive KYC notifications
     * 
     * @return array Array of admin email data with 'email' and 'name' keys
     */
    private function getAdminEmails(): array
    {
        try {
            $adminEmails = [];
            
            // Get super admin email
            $superAdminEmail = Configuration::get('PS_SHOP_EMAIL');
            if (!empty($superAdminEmail)) {
                $adminEmails[] = [
                    'email' => $superAdminEmail,
                    'name' => Configuration::get('PS_SHOP_NAME')
                ];
            }

            // Get additional admin emails from configuration
            $additionalEmails = Configuration::get('PSKYC_ADMIN_EMAILS');
            if (!empty($additionalEmails)) {
                $emails = explode(',', $additionalEmails);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if (\Validate::isEmail($email)) {
                        $adminEmails[] = [
                            'email' => $email,
                            'name' => 'KYC Administrator'
                        ];
                    }
                }
            }

            return $adminEmails;

        } catch (\Exception $e) {
            \PrestaShopLogger::addLog('Error getting admin emails: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [];
        }
    }

    /**
     * Send bulk notification to multiple recipients
     * 
     * Sends the same notification to multiple email addresses
     * 
     * @param array $recipients Array of recipient data with 'email' and 'name' keys
     * @param string $template Email template name
     * @param string $subject Email subject
     * @param array $templateVars Template variables
     * @return array Results array with success count and failed emails
     */
    public function sendBulkNotification(array $recipients, string $template, string $subject, array $templateVars): array
    {
        $successCount = 0;
        $failedEmails = [];

        foreach ($recipients as $recipient) {
            try {
                $result = $this->sendThemeEmail(
                    $template,
                    $subject,
                    $templateVars,
                    $recipient['email'],
                    $recipient['name'],
                    Configuration::get('PS_LANG_DEFAULT')
                );

                if ($result) {
                    $successCount++;
                } else {
                    $failedEmails[] = $recipient['email'];
                }

            } catch (\Exception $e) {
                $failedEmails[] = $recipient['email'];
                \PrestaShopLogger::addLog(
                    'Bulk notification error for ' . $recipient['email'] . ': ' . $e->getMessage(),
                    3,
                    null,
                    'Pskyc'
                );
            }
        }

        return [
            'success_count' => $successCount,
            'failed_emails' => $failedEmails,
            'total_sent' => count($recipients)
        ];
    }
}