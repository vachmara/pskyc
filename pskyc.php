<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class Pskyc
 * 
 * Main module class for KYC Secure Upload module
 * Handles document verification and encrypted storage for PrestaShop
 */
class Pskyc extends Module
{
    protected $config_form = false;

    /**
     * Module constructor
     * 
     * Initializes module properties and configuration
     */
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
     * Install the module
     * 
     * Creates database tables, sets default configuration,
     * creates upload directory and registers hooks
     * 
     * @return bool True if installation successful, false otherwise
     */
    public function install()
    {
        // Set default configuration values
        Configuration::updateValue('PSKYC_RETENTION_DAYS', 365);
        Configuration::updateValue('PSKYC_ALLOWED_CATEGORIES', json_encode([]));
        Configuration::updateValue('PSKYC_ADMIN_EMAILS', Configuration::get('PS_SHOP_EMAIL'));
        Configuration::updateValue('PSKYC_AUTO_NOTIFICATIONS', true);

        // Generate encryption key
        if (!Configuration::get('PSKYC_ENCRYPTION_KEY')) {
            $key = base64_encode(random_bytes(32));
            Configuration::updateValue('PSKYC_ENCRYPTION_KEY', $key);
        }

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

    /**
     * Uninstall the module
     * 
     * Removes database tables, configuration values and uploaded files
     * 
     * @return bool True if uninstallation successful, false otherwise
     */
    public function uninstall()
    {

        require_once __DIR__ . '/sql/uninstall.php';

        Configuration::deleteByName('PSKYC_RETENTION_DAYS');
        Configuration::deleteByName('PSKYC_ALLOWED_CATEGORIES');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     * 
     * Handles form submission and displays the module configuration page
     * 
     * @return string HTML content for the configuration page
     */
    public function getContent()
    {
        $output = '';
        $errors = [];

        /**
         * If values have been submitted in the form, process.
         */
        if (Tools::isSubmit('submitPskycModule')) {
            $result = $this->postProcess();
            if ($result['success']) {
                $output .= $this->displayConfirmation($this->l('Settings updated successfully!'));
            } else {
                $errors = $result['errors'];
            }
        }

        // Assign template variables
        $this->context->smarty->assign([
            'module_dir' => $this->_path,
            'form_html' => $this->renderForm(),
            'errors' => $errors,
        ]);

        $output .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Create the form that will be displayed in the configuration of your module
     * 
     * @return string HTML form content
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
     * Create the structure of your form
     * 
     * @return array Form configuration array
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
                        'label' => $this->l('Document Retention (days)'),
                        'desc' => $this->l('Documents older than this will be purged automatically. Set to 0 to disable automatic cleanup.'),
                        'col' => 2,
                        'suffix' => 'days',
                        'required' => true,
                        'cast' => 'intval',
                        'class' => 'fixed-width-sm'
                    ],
                    [
                        'type' => 'categories',
                        'name' => 'PSKYC_ALLOWED_CATEGORIES',
                        'label' => $this->l('Categories requiring KYC'),
                        'desc' => $this->l('Select product categories that require KYC verification before purchase. Leave empty to disable automatic order blocking.'),
                        'tree' => [
                            'id' => 'categories-tree',
                            'selected_categories' => json_decode(Configuration::get('PSKYC_ALLOWED_CATEGORIES') ?: '[]', true),
                            'root_category' => (int) Configuration::get('PS_HOME_CATEGORY'),
                            'use_checkbox' => true,
                            'use_search' => true,
                            'disabled_categories' => [],
                            'top_category' => Category::getTopCategory(),
                            'use_context' => true,
                        ],
                    ],
                    [
                        'type' => 'text',
                        'name' => 'PSKYC_ADMIN_EMAILS',
                        'label' => $this->l('Admin notification emails'),
                        'desc' => $this->l('Comma-separated list of emails to notify for new KYC submissions. Example: admin@store.com, manager@store.com'),
                        'col' => 6,
                        'placeholder' => 'admin@yourstore.com, manager@yourstore.com'
                    ],
                    [
                        'type' => 'switch',
                        'name' => 'PSKYC_AUTO_NOTIFICATIONS',
                        'label' => $this->l('Auto notifications'),
                        'desc' => $this->l('Send automatic email notifications to customers when their verification status changes.'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')]
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'encryption_info',
                        'html_content' => '<div class="alert alert-info"><strong>' . $this->l('Security Note:') . '</strong> ' . 
                            $this->l('All uploaded documents are automatically encrypted using AES-256-CBC encryption. The encryption key is automatically generated and stored securely.') . '</div>'
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save Configuration'),
                    'class' => 'btn btn-default pull-right'
                ],
            ],
        ];
    }

    /**
     * Get current configuration form values
     * 
     * @return array Current configuration values
     */
    protected function getConfigFormValues()
    {
        return [
            'PSKYC_RETENTION_DAYS' => (int) Configuration::get('PSKYC_RETENTION_DAYS'),
            'PSKYC_ALLOWED_CATEGORIES' => json_decode(Configuration::get('PSKYC_ALLOWED_CATEGORIES') ?: '[]', true),
            'PSKYC_ADMIN_EMAILS' => Configuration::get('PSKYC_ADMIN_EMAILS'),
            'PSKYC_AUTO_NOTIFICATIONS' => (bool) Configuration::get('PSKYC_AUTO_NOTIFICATIONS'),
        ];
    }

    /**
     * Save form data
     * 
     * Processes and saves the configuration form values
     * 
     * @return array Result with success status and any errors
     */
    protected function postProcess()
    {
        $errors = [];
        $success = true;

        // Validate retention days
        $retentionDays = (int) Tools::getValue('PSKYC_RETENTION_DAYS');
        if ($retentionDays < 0) {
            $errors[] = $this->l('Retention days must be 0 or positive.');
            $success = false;
        }

        // Validate admin emails
        $adminEmails = Tools::getValue('PSKYC_ADMIN_EMAILS');
        if (!empty($adminEmails)) {
            $emails = array_map('trim', explode(',', $adminEmails));
            foreach ($emails as $email) {
                if (!Validate::isEmail($email)) {
                    $errors[] = sprintf($this->l('Invalid email address: %s'), $email);
                    $success = false;
                }
            }
        }

        // If validation passed, save the values
        if ($success) {
            // Handle categories specifically
            $categoryIds = Tools::getValue('PSKYC_ALLOWED_CATEGORIES');
            if (is_array($categoryIds)) {
                // Remove any invalid category IDs
                $categoryIds = array_filter($categoryIds, function($id) {
                    return is_numeric($id) && (int)$id > 0;
                });
                Configuration::updateValue('PSKYC_ALLOWED_CATEGORIES', json_encode(array_values($categoryIds)));
            } else {
                Configuration::updateValue('PSKYC_ALLOWED_CATEGORIES', json_encode([]));
            }

            // Handle other configuration values
            Configuration::updateValue('PSKYC_RETENTION_DAYS', $retentionDays);
            Configuration::updateValue('PSKYC_ADMIN_EMAILS', $adminEmails);
            Configuration::updateValue('PSKYC_AUTO_NOTIFICATIONS', (bool)Tools::getValue('PSKYC_AUTO_NOTIFICATIONS'));

            // Ensure encryption key exists
            $this->ensureEncryptionKey();
        }

        return [
            'success' => $success,
            'errors' => $errors
        ];
    }

    /**
     * Ensure encryption key exists
     * 
     * @return void
     */
    private function ensureEncryptionKey()
    {
        if (!Configuration::get('PSKYC_ENCRYPTION_KEY')) {
            $key = base64_encode(random_bytes(32)); // 256-bit key
            Configuration::updateValue('PSKYC_ENCRYPTION_KEY', $key);
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO
     * 
     * Hook executed on back office header display
     * 
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO
     * 
     * Hook executed on front office header display
     * 
     * @return void
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    /**
     * Hook executed when admin controller sets media
     * 
     * @return void
     */
    public function hookActionAdminControllerSetMedia()
    {
        /* Place your code here. */
    }

    /**
     * Hook executed when an order is validated
     * 
     * Can be used to check KYC status before order completion
     * 
     * @return void
     */
    public function hookActionValidateOrder()
    {
        /* Place your code here. */
    }

    /**
     * Hook executed in admin customers page
     * 
     * Can display KYC status for each customer
     * 
     * @return void
     */
    public function hookDisplayAdminCustomers()
    {
        /* Place your code here. */
    }

    /**
     * Hook executed in admin order page
     * 
     * Can display KYC status for the order's customer
     * 
     * @return void
     */
    public function hookDisplayAdminOrder()
    {
        /* Place your code here. */
    }

    /**
     * Display KYC verification link in customer account
     * 
     * Hook executed in customer account page to show KYC verification box
     * 
     * @return string HTML content for the KYC verification box
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
