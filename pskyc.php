<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Pskyc extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'pskyc';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Valentin Chmara';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('KYC Secure Upload');
        $this->description = $this->l('Open source KYC document verification and encrypted storage for PrestaShop. GDPR compliant.');

        $this->confirmUninstall = $this->l('All KYC verification data and associated documents will be permanently deleted. This action cannot be undone.');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        // default config
        Configuration::updateValue('PSKYC_RETENTION_DAYS', 365);
        Configuration::updateValue('PSKYC_ALLOWED_CATEGORIES', json_encode([]));

        require_once __DIR__ . '/sql/install.php';

        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/secure_upload/';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0700, true)) {
            return false;
        }

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook('actionValidateOrder') &&
            $this->registerHook('displayAdminCustomers') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('displayCustomerAccount');
    }

    public function uninstall()
    {

        require_once __DIR__ . '/sql/uninstall.php';

        Configuration::deleteByName('PSKYC_RETENTION_DAYS');
        Configuration::deleteByName('PSKYC_ALLOWED_CATEGORIES');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitPskycModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPskycModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('KYC Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'name' => 'PSKYC_RETENTION_DAYS',
                        'label' => $this->l('Retention (days)'),
                        'desc' => $this->l('Documents older than this will be purged automatically.'),
                        'col' => 2,
                    ],
                    [
                        'type' => 'categories',
                        'name' => 'PSKYC_ALLOWED_CATEGORIES',
                        'label' => $this->l('Categories requiring KYC'),
                        'tree' => [
                            'root_category' => (int) Configuration::get('PS_HOME_CATEGORY'),
                        ],
                    ],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];
    }

    protected function getConfigFormValues()
    {
        return [
            'PSKYC_RETENTION_DAYS' => (int) Configuration::get('PSKYC_RETENTION_DAYS'),
            'PSKYC_ALLOWED_CATEGORIES' => json_decode(Configuration::get('PSKYC_ALLOWED_CATEGORIES') ?: '[]', true),
        ];
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookActionAdminControllerSetMedia()
    {
        /* Place your code here. */
    }

    public function hookActionValidateOrder()
    {
        /* Place your code here. */
    }

    public function hookDisplayAdminCustomers()
    {
        /* Place your code here. */
    }

    public function hookDisplayAdminOrder()
    {
        /* Place your code here. */
    }

    /**
     * @return string
     */
    public function hookDisplayCustomerAccount()
    {
        $context = Context::getContext();

        $this->context->smarty->assign([
            'frontController' => $context->link->getModuleLink($this->name, 'verify', [], true),
            'customerId' => $context->customer->id,
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/front/account/box.tpl');
    }
}
