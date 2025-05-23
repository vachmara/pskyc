<?php
namespace PrestaShop\Module\Pskyc\Controller\Admin;

use ModuleAdminController;
use PrestaShop\Module\Pskyc\Service\KycService;
use PrestaShop\Module\Pskyc\Entity\PskycVerification;
use Tools;

/**
 * Admin controller for managing KYC verifications in the back office.
 */
class KycController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * Render the list of KYC verifications.
     *
     * @return string
     */
    public function renderList()
    {
        // Fetch all verifications (implement getVerifications if needed)
        $verifications = PskycVerification::getVerifications();

        $this->context->smarty->assign([
            'verifications' => $verifications,
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_.'pskyc/views/templates/admin/list.tpl');
    }

    /**
     * Handle approve/reject actions for KYC verifications.
     */
    public function postProcess()
    {
        $service = new KycService();

        if (Tools::isSubmit('approve_kyc')) {
            $id = (int)Tools::getValue('id_verification');
            if ($this->access('edit')) {
                $service->approve($id, Tools::getValue('note'));
                $this->confirmations[] = $this->l('KYC verification approved.');
            } else {
                $this->errors[] = $this->l('You do not have permission to approve KYC verifications.');
            }
        } elseif (Tools::isSubmit('reject_kyc')) {
            $id = (int)Tools::getValue('id_verification');
            if ($this->access('edit')) {
                $service->reject($id, Tools::getValue('note'));
                $this->confirmations[] = $this->l('KYC verification rejected.');
            } else {
                $this->errors[] = $this->l('You do not have permission to reject KYC verifications.');
            }
        }
    }
}
