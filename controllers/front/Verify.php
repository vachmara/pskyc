<?php
use Symfony\Component\Translation\Exception\InvalidArgumentException;

class PskycVerifyModuleFrontController extends ModuleFrontController
{
    /**
     * @var Pskyc
     */
    public $module;

    /**
     * @throws PrestaShopException
     */
    public function initContent()
    {
        $context = Context::getContext();
        if (empty($context->customer->id)) {
            Tools::redirect('index.php');
        }

        parent::initContent();

        $params = [
            'token' => sha1($context->customer->secure_key),
        ];

        $this->context->smarty->assign([
            'pskyc_ps_version' => (bool) version_compare(_PS_VERSION_, '1.7', '>='),
            'pskyc_id_customer' => Context::getContext()->customer->id,
        ]);

        $this->context->smarty->tpl_vars['page']->value['body_classes']['page-customer-account'] = true;
        $this->setTemplate('module:pskyc/views/templates/front/account/page.tpl');
    }

     /**
     * Get breadcrumb links
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = $this->addMyAccountToBreadcrumb();
        $breadcrumb['links'][] = [
           'title' => $this->trans('KYC - Verify your identity', [], 'Modules.Pskyc.Shop'),
           'url' => $this->context->link->getModuleLink($this->module->name, 'verify', [], true),
        ];

        return $breadcrumb;
    }
}