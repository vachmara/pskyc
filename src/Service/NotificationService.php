<?php

namespace PrestaShop\Module\Pskyc\Service;

use Context;
use Mail;
use Customer;
use Configuration;
use Symfony\Component\Translation\TranslatorInterface;

class NotificationService
{
    private $translator;
    private $context;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->context = Context::getContext();
    }

    /**
     * Send email when customer submits documents for verification
     */
    public function sendVerificationSubmitted($customerId, $verificationId)
    {
        $customer = new Customer($customerId);
        
        $templateVars = [
            '{customer_firstname}' => $customer->firstname,
            '{customer_lastname}' => $customer->lastname,
            '{verification_id}' => $verificationId,
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => $this->context->shop->getBaseURL(true),
        ];

        return Mail::Send(
            $this->context->language->id,
            'pskyc_verification_submitted',
            $this->translator->trans('KYC Verification - Documents Submitted', [], 'Modules.Pskyc.Shop'),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'pskyc/mails/'
        );
    }

    /**
     * Send email when verification status changes
     */
    public function sendStatusUpdate($customerId, $status, $adminNote = null)
    {
        $customer = new Customer($customerId);
        
        $statusMessages = [
            'approved' => $this->translator->trans('Your identity verification has been approved!', [], 'Modules.Pskyc.Shop'),
            'rejected' => $this->translator->trans('Your identity verification has been rejected.', [], 'Modules.Pskyc.Shop'),
            'pending_info' => $this->translator->trans('Additional information is required for your verification.', [], 'Modules.Pskyc.Shop'),
        ];

        $templateVars = [
            '{customer_firstname}' => $customer->firstname,
            '{customer_lastname}' => $customer->lastname,
            '{status}' => $status,
            '{status_message}' => $statusMessages[$status] ?? '',
            '{admin_note}' => $adminNote ?: '',
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
            '{shop_url}' => $this->context->shop->getBaseURL(true),
            '{my_account_url}' => $this->context->link->getPageLink('my-account'),
        ];

        return Mail::Send(
            $this->context->language->id,
            'pskyc_status_update',
            $this->translator->trans('KYC Verification - Status Update', [], 'Modules.Pskyc.Shop'),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'pskyc/mails/'
        );
    }

    /**
     * Send notification to admin when new verification is submitted
     */
    public function sendAdminNotification($verificationId, $customerId)
    {
        $customer = new Customer($customerId);
        $adminEmail = Configuration::get('PS_SHOP_EMAIL');
        
        $templateVars = [
            '{customer_firstname}' => $customer->firstname,
            '{customer_lastname}' => $customer->lastname,
            '{customer_email}' => $customer->email,
            '{verification_id}' => $verificationId,
            '{admin_url}' => $this->context->link->getAdminLink('AdminPskycVerification'),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
        ];

        return Mail::Send(
            $this->context->language->id,
            'pskyc_admin_notification',
            $this->translator->trans('New KYC Verification Request', [], 'Modules.Pskyc.Admin'),
            $templateVars,
            $adminEmail,
            Configuration::get('PS_SHOP_NAME'),
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'pskyc/mails/'
        );
    }

    /**
     * Send reminder email for pending verifications
     */
    public function sendReminderEmail($customerId, $daysPending)
    {
        $customer = new Customer($customerId);
        
        $templateVars = [
            '{customer_firstname}' => $customer->firstname,
            '{customer_lastname}' => $customer->lastname,
            '{days_pending}' => $daysPending,
            '{verification_url}' => $this->context->link->getModuleLink('pskyc', 'verify'),
            '{shop_name}' => Configuration::get('PS_SHOP_NAME'),
        ];

        return Mail::Send(
            $this->context->language->id,
            'pskyc_reminder',
            $this->translator->trans('KYC Verification - Reminder', [], 'Modules.Pskyc.Shop'),
            $templateVars,
            $customer->email,
            $customer->firstname . ' ' . $customer->lastname,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'pskyc/mails/'
        );
    }


    /**
     * Create in-app notification/alert
     */
    public function createInAppNotification($customerId, $message, $type = 'info')
    {
        // TODO: Store notification in database for display in customer account
        // Could be used for dashboard notifications
        return true;
    }
}