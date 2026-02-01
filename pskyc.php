<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
use PrestaShop\Module\Pskyc\Service\VerificationService;
use PrestaShop\PrestaShop\Core\MailTemplate\Layout\Layout;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCatalogInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeCollectionInterface;
use PrestaShop\PrestaShop\Core\MailTemplate\ThemeInterface;

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
     * @var string|null
     */
    public $confirmReset;

    /**
     * Module constructor
     *
     * Initializes module properties and configuration
     */
    public function __construct()
    {
        $this->name = 'pskyc';
        $this->tab = 'administration';
        $this->version = '1.1.3';
        $this->author = 'Valentin Chmara';
        $this->need_instance = 1;

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('KYC Secure Upload', [], 'Modules.Pskyc.Admin');
        $this->description = $this->trans('Open source KYC document verification and encrypted storage for PrestaShop. GDPR compliant.', [], 'Modules.Pskyc.Admin');

        $warning = $this->trans('All KYC verification data and associated documents will be permanently deleted. This action cannot be undone.', [], 'Modules.Pskyc.Admin');

        $this->confirmUninstall = $warning;
        $this->confirmReset = $warning;

        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => '9.9.99'];

        // Define admin tabs following modern PrestaShop 8/9 official documentation
        $this->tabs = [
            [
                'route_name' => 'ps_pskyc_verification_index',
                'class_name' => 'AdminPskycVerification',
                'visible' => true,
                'name' => 'KYC Verifications',
                'wording' => 'KYC Verifications',
                'wording_domain' => 'Modules.Pskyc.Admin',
                'parent_class_name' => 'AdminParentCustomer',
            ],
        ];
    }

    /**
     * Tell PrestaShop that this module uses the new translation system (XLF files).
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Install the module
     *
     * Creates database tables, sets default configuration,
     * creates upload directory with .htaccess protection and registers hooks
     *
     * @return bool True if installation successful, false otherwise
     */
    public function install()
    {
        // Set default configuration values
        Configuration::updateValue('PSKYC_RETENTION_DAYS', 365);
        Configuration::updateValue('PSKYC_KYC_REQUIRED_CATEGORIES', json_encode([]));
        Configuration::updateValue('PSKYC_ADMIN_EMAILS', Configuration::get('PS_SHOP_EMAIL'));
        Configuration::updateValue('PSKYC_AUTO_NOTIFICATIONS', true);

        // Generate encryption key - MUST be hexadecimal for EncryptionService
        $this->generateEncryptionKey();

        require_once __DIR__ . '/sql/install.php';

        // Create secure upload directory with proper permissions and .htaccess protection
        if (!$this->createSecureUploadDirectory()) {
            return false;
        }

        return parent::install()
            && $this->registerHook('actionCheckoutRender')
            && $this->registerHook('actionValidateOrderBefore')
            && $this->registerHook('displayBeforeCarrier')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayAdminCustomers')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('registerGDPRConsent')
            && $this->registerHook('actionDeleteGDPRCustomer')
            && $this->registerHook('actionExportGDPRData')
            && $this->registerHook(ThemeCatalogInterface::LIST_MAIL_THEMES_HOOK);
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
        Configuration::deleteByName('PSKYC_KYC_REQUIRED_CATEGORIES');

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

        /*
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

        // Check upload directory security status
        $securityStatus = $this->checkUploadDirectorySecurity();

        // Display security warnings if any
        if ($securityStatus['status'] === 'warning') {
            foreach ($securityStatus['warnings'] as $warning) {
                $output .= $this->displayWarning($this->l('Security Warning: ') . $warning);
            }
        } elseif ($securityStatus['status'] === 'error') {
            foreach ($securityStatus['warnings'] as $warning) {
                $output .= $this->displayError($this->l('Security Error: ') . $warning);
            }
        } elseif ($securityStatus['status'] === 'secure') {
            $output .= $this->displayConfirmation($this->l('Upload directory security: All security measures are properly configured.'));
        }

        // Get verification URL safely
        $verificationUrl = '';
        try {
            $router = $this->get('router');
            if ($router) {
                $verificationUrl = $router->generate('ps_pskyc_verification_index');
            }
        } catch (Exception $e) {
            // Fallback to admin link if router fails
            $verificationUrl = $this->context->link->getAdminLink('AdminModules') . '&configure=pskyc';
        }

        // Assign template variables
        $this->context->smarty->assign([
            'module_version' => $this->version,
            'module_dir' => $this->_path,
            'form_html' => $this->renderForm(),
            'errors' => $errors,
            'security_status' => $securityStatus,
            'verification_url' => $verificationUrl,
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

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
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
                        'class' => 'fixed-width-sm',
                    ],
                    [
                        'type' => 'categories',
                        'name' => 'PSKYC_KYC_REQUIRED_CATEGORIES',
                        'label' => $this->l('Categories requiring KYC'),
                        'desc' => $this->l('Select product categories that require KYC verification before purchase. Leave empty to disable automatic order blocking.'),
                        'tree' => [
                            'id' => 'categories-tree',
                            'selected_categories' => json_decode(Configuration::get('PSKYC_KYC_REQUIRED_CATEGORIES') ?: '[]', true),
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
                        'placeholder' => 'admin@yourstore.com, manager@yourstore.com',
                    ],
                    [
                        'type' => 'switch',
                        'name' => 'PSKYC_AUTO_NOTIFICATIONS',
                        'label' => $this->l('Auto notifications'),
                        'desc' => $this->l('Send automatic email notifications to customers when their verification status changes.'),
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'encryption_info',
                        'html_content' => $this->context->smarty->fetch($this->local_path . 'views/templates/admin/security_note.tpl'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Configuration'),
                    'class' => 'btn btn-default pull-right',
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
            'PSKYC_KYC_REQUIRED_CATEGORIES' => json_decode(Configuration::get('PSKYC_KYC_REQUIRED_CATEGORIES') ?: '[]', true),
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
            $categoryIds = Tools::getValue('PSKYC_KYC_REQUIRED_CATEGORIES');
            if (is_array($categoryIds)) {
                // Remove any invalid category IDs
                $categoryIds = array_filter($categoryIds, function ($id) {
                    return is_numeric($id) && (int) $id > 0;
                });
                Configuration::updateValue('PSKYC_KYC_REQUIRED_CATEGORIES', json_encode(array_values($categoryIds)));
            } else {
                Configuration::updateValue('PSKYC_KYC_REQUIRED_CATEGORIES', json_encode([]));
            }

            // Handle other configuration values
            Configuration::updateValue('PSKYC_RETENTION_DAYS', $retentionDays);
            Configuration::updateValue('PSKYC_ADMIN_EMAILS', $adminEmails);
            Configuration::updateValue('PSKYC_AUTO_NOTIFICATIONS', (bool) Tools::getValue('PSKYC_AUTO_NOTIFICATIONS'));

            // Ensure encryption key exists
            $this->ensureEncryptionKey();
        }

        return [
            'success' => $success,
            'errors' => $errors,
        ];
    }

    /**
     * Generate encryption key
     *
     * Generates a new 256-bit encryption key in hexadecimal format
     * This method ensures the key is always compatible with EncryptionService
     *
     * @return void
     */
    private function generateEncryptionKey()
    {
        $key = bin2hex(random_bytes(32)); // 256-bit key as hexadecimal string
        Configuration::updateValue('PSKYC_ENCRYPTION_KEY', $key);
    }

    /**
     * Ensure encryption key exists and is valid
     *
     * Checks if encryption key exists and is valid hex format.
     * If not, generates a new one.
     *
     * @return void
     */
    private function ensureEncryptionKey()
    {
        $key = Configuration::get('PSKYC_ENCRYPTION_KEY');

        // Check if key exists and is valid hex (64 characters for 32 bytes)
        if (empty($key) || !ctype_xdigit($key) || strlen($key) !== 64) {
            $this->generateEncryptionKey();
        }
    }

    /**
     * Hook executed in admin customers page
     *
     * Can display KYC status for each customer
     *
     * @return string|void
     */
    public function hookDisplayAdminCustomers()
    {
        $customerId = Tools::getValue('id_customer');
        if (!$customerId) {
            return;
        }
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');
        $verifications = $verificationService->getVerificationsByCustomerId($customerId);

        // Render the Twig template instead of Smarty
        $twig = $this->get('twig');
        if (!$twig) {
            return;
        }

        return $twig->render('@Modules/pskyc/views/templates/admin/customers/kyc_status.html.twig', [
            'verifications' => $verifications,
            'count' => count($verifications ?? []),
            'customerId' => $customerId,
        ]);
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

    /**
     * @param array $hookParams
     */
    public function hookActionListMailThemes(array $hookParams)
    {
        if (!isset($hookParams['mailThemes'])) {
            return;
        }

        /** @var ThemeCollectionInterface $themes */
        $themes = $hookParams['mailThemes'];

        /** @var ThemeInterface $theme */
        foreach ($themes as $theme) {
            if (!in_array($theme->getName(), ['classic', 'modern'])) {
                continue;
            }

            $this->addLayoutsToTheme($theme, $theme->getName());
        }
    }

    /**
     * Add KYC layouts to a specific theme
     *
     * @param ThemeInterface $theme
     * @param string $themeName
     *
     * @return void
     */
    private function addLayoutsToTheme(ThemeInterface $theme, string $themeName)
    {
        // Waiting this to be resolved in PrestaShop 9: https://github.com/PrestaShop/PrestaShop/issues/35214
        $moduleLayoutsPath = '@Modules/' . $this->name . "/mails/layouts/{$themeName}/";

        // Define our KYC layouts
        $layouts = [
            'verification_status' => [
                'name' => 'verification_status',
                'htmlTemplate' => $moduleLayoutsPath . 'verification_status.html.twig',
            ],
            'verification_expiry_warning' => [
                'name' => 'verification_expiry_warning',
                'htmlTemplate' => $moduleLayoutsPath . 'verification_expiry_warning.html.twig',
            ],
            'document_upload_confirmation' => [
                'name' => 'document_upload_confirmation',
                'htmlTemplate' => $moduleLayoutsPath . 'document_upload_confirmation.html.twig',
            ],
            'admin_new_verification' => [
                'name' => 'admin_new_verification',
                'htmlTemplate' => $moduleLayoutsPath . 'admin_new_verification.html.twig',
            ],
        ];

        // Add each layout to the theme
        foreach ($layouts as $layoutConfig) {
            // Check if templates exist before adding
            if (file_exists($layoutConfig['htmlTemplate'])) {
                $layout = new Layout(
                    $layoutConfig['name'],
                    $layoutConfig['htmlTemplate'],
                    '',
                    $this->name
                );

                $theme->getLayouts()->add($layout);
            }
        }
    }

    /**
     * Hook executed during checkout rendering
     *
     * Adds KYC step to checkout process if KYC verification is required
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionCheckoutRender($params)
    {
        if (!isset($params['checkoutProcess'])) {
            return;
        }

        $checkoutProcess = $params['checkoutProcess'];
        $context = Context::getContext();

        // Check if customer is logged in
        if (!$context->customer->id) {
            return;
        }

        // Check if KYC is required for cart products
        $kycRequired = $this->isKycRequiredForCart($context->cart);

        if (!$kycRequired) {
            return;
        }

        // Get customer's verification status
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');
        $verification = $verificationService->getMostRecentVerification($context->customer->id);

        // Only add step if verification is not approved
        if ($verification && $verification['status'] === 'approved') {
            return;
        }

        // Create KYC step
        $kycStep = new PrestaShop\Module\Pskyc\Checkout\KycStep(
            $context,
            $this->getTranslator()
        );

        $kycUrl = $context->link->getModuleLink($this->name, 'verify', [], true);

        $kycStep
            ->setKycUrl($kycUrl)
            ->setReachable(true)
            ->setComplete(false);

        // Get current steps and insert KYC step after the first step
        $steps = $checkoutProcess->getSteps();

        // Insert KYC step after personal information step (index 0)
        array_splice($steps, 1, 0, [$kycStep]);

        // Set the modified steps array back to checkout process
        $checkoutProcess->setSteps($steps);
    }

    /**
     * Check if KYC is required for products in cart
     *
     * @param Cart $cart
     *
     * @return bool
     */
    private function isKycRequiredForCart($cart)
    {
        $kycRequiredCategories = json_decode(Configuration::get('PSKYC_KYC_REQUIRED_CATEGORIES') ?: '[]', true);

        if (empty($kycRequiredCategories)) {
            return false;
        }

        $products = $cart->getProducts();
        $cartCategoryIds = [];

        foreach ($products as $product) {
            if (!empty($product['id_product'])) {
                $productCategories = Product::getProductCategories((int) $product['id_product']);
                $cartCategoryIds = array_merge($cartCategoryIds, $productCategories);
            } elseif (!empty($product['id_category_default'])) {
                $cartCategoryIds[] = (int) $product['id_category_default'];
            }
        }

        $cartCategoryIds = array_unique($cartCategoryIds);

        return count(array_intersect($kycRequiredCategories, $cartCategoryIds)) > 0;
    }

    /**
     * Hook to register GDPR consent
     */
    public function hookRegisterGDPRConsent()
    {
        // This hook can be used to register GDPR consent for KYC document uploads
        // For now, we don't need to do anything here, but it's available for future use
        return true;
    }

    /**
     * Hook to export GDPR data
     *
     * @param Customer $customer
     *
     * @return string JSON encoded data
     */
    public function hookActionExportGDPRData($customer)
    {
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');
        $verifications = $verificationService->getGdprData($customer->id);

        return json_encode($verifications) ?: '[]';
    }

    /**
     * Hook to delete GDPR customer data
     *
     * @param Customer $customer
     *
     * @return void
     */
    public function hookActionDeleteGDPRCustomer($customer)
    {
        // This hook can be used to delete KYC-related data when a customer is deleted
        // For now, we don't need to do anything here, but it's available for future use
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');
        $verificationService->deleteVerificationsByCustomerId($customer->id);
    }

    /**
     * Hook executed before order validation
     *
     * In PrestaShop 8, this behavior is injected via a PaymentModule override.
     * In future PrestaShop versions where a native "validate order before" hook is available,
     * this method can be wired to that native hook.
     * Blocks order creation if KYC verification is required but not approved.
     *
     * @param array $params Contains ['cart', 'customer']
     */
    public function hookActionValidateOrderBefore($params)
    {
        $cart = $params['cart'] ?? null;
        $customer = $params['customer'] ?? null;

        if (!$cart || !$customer || !$customer->id || $customer->is_guest) {
            return;
        }

        if (!$this->isKycRequiredForCart($cart)) {
            return;
        }

        // Get customer's verification status
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');
        $verification = $verificationService->getMostRecentVerification($customer->id);

        // Allow order only if verification is approved
        if ($verification && $verification['status'] === 'approved') {
            return true;
        }

        // KYC required but not approved - redirect to verification page
        // Log the event for debugging
        PrestaShopLogger::addLog(
            'KYC: Order blocked for customer ' . $customer->id . ' - verification required, redirecting to KYC page',
            1,
            null,
            'Pskyc'
        );

        // Redirect to KYC verification page
        $kycUrl = $this->context->link->getModuleLink($this->name, 'verify', ['kyc_required' => 1], true);
        Tools::redirect($kycUrl);
    }

    /**
     * Hook displayed before carrier selection in checkout
     *
     * Shows a warning if KYC verification is required but not completed
     * Works with both standard and third-party checkout modules
     *
     * @param array $params
     *
     * @return string HTML content
     */
    public function hookDisplayBeforeCarrier($params)
    {
        $context = Context::getContext();

        // Check if customer is logged in
        if (!$context->customer->id || $context->customer->is_guest) {
            return '';
        }

        // Check if KYC is required for cart products
        $kycRequired = $this->isKycRequiredForCart($context->cart);

        if (!$kycRequired) {
            return '';
        }

        // Get customer's verification status
        /** @var VerificationService $verificationService */
        $verificationService = $this->get('PrestaShop\\Module\\Pskyc\\Service\\VerificationService');
        $verification = $verificationService->getMostRecentVerification($context->customer->id);

        // Only show warning if not approved
        if ($verification && $verification['status'] === 'approved') {
            return '';
        }

        // Build verification URL
        $verifyUrl = $context->link->getModuleLink($this->name, 'verify', [], true);

        // Assign template variables
        $this->context->smarty->assign([
            'kyc_verify_url' => $verifyUrl,
            'kyc_status' => $verification ? $verification['status'] : 'not_started',
        ]);

        return $this->fetch('module:' . $this->name . '/views/templates/front/checkout/kyc-warning.tpl');
    }

    /**
     * Hook to load CSS/JS assets on front pages
     *
     * Loads KYC-related assets when needed (checkout pages, account pages)
     *
     * @return void
     */
    public function hookActionFrontControllerSetMedia()
    {
        $controller = $this->context->controller;

        // Only load on checkout and account pages
        if ($controller instanceof OrderController
            || ($controller instanceof ModuleFrontController && $controller->module->name === $this->name)
            || (isset($controller->php_self) && $controller->php_self === 'my-account')) {
            // Register CSS
            $this->context->controller->registerStylesheet(
                'module-pskyc-front',
                'modules/' . $this->name . '/views/css/front.css',
                ['media' => 'all', 'priority' => 150]
            );

            // Register JS
            $this->context->controller->registerJavascript(
                'module-pskyc-front',
                'modules/' . $this->name . '/views/js/front.js',
                ['position' => 'bottom', 'priority' => 150]
            );
        }
    }

    /**
     * Create secure upload directory with comprehensive security measures
     *
     * @return bool True if directory created successfully with proper security, false otherwise
     */
    private function createSecureUploadDirectory()
    {
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/secure_upload/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            // Use 0700 for Unix/Linux (owner read/write/execute only)
            if (!@mkdir($uploadDir, 0700, true)) {
                return false;
            }
        }

        // Verify directory security
        if (!$this->verifyDirectorySecurity($uploadDir)) {
            return false;
        }

        // Create .htaccess file from template
        $htaccessFile = $uploadDir . '.htaccess';
        if (!file_exists($htaccessFile)) {
            if (!$this->createHtaccessFile($htaccessFile)) {
                return false;
            }
        }

        // Create index.php to prevent directory listing fallback
        $indexFile = $uploadDir . 'index.php';
        if (!file_exists($indexFile)) {
            if (!$this->createSecurityIndexFile($indexFile)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify directory security settings
     *
     * @param string $uploadDir The upload directory path
     *
     * @return bool True if directory is secure, false otherwise
     */
    private function verifyDirectorySecurity(string $uploadDir): bool
    {
        // Check if directory is readable and writable by the web server
        if (!is_readable($uploadDir) || !is_writable($uploadDir)) {
            return false;
        }

        // Additional security checks for Unix/Linux systems
        if (function_exists('fileperms') && DIRECTORY_SEPARATOR === '/') {
            $perms = fileperms($uploadDir);
            $octal = substr(sprintf('%o', $perms), -4);

            // Check if permissions are too permissive (should be 0700 or similar)
            if ((int) $octal > 0755) {
                // Try to fix permissions
                if (!@chmod($uploadDir, 0700)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Create .htaccess file with proper security
     *
     * @param string $htaccessFile Path to .htaccess file
     *
     * @return bool True if created successfully, false otherwise
     */
    private function createHtaccessFile(string $htaccessFile): bool
    {
        $templateFile = __DIR__ . '/secure_upload_htaccess_template.txt';

        if (!file_exists($templateFile)) {
            return false;
        }

        $htaccessContent = file_get_contents($templateFile);
        if ($htaccessContent === false) {
            return false;
        }

        if (file_put_contents($htaccessFile, $htaccessContent) === false) {
            return false;
        }

        // Set restrictive permissions on .htaccess file (Unix/Linux)
        if (DIRECTORY_SEPARATOR === '/') {
            @chmod($htaccessFile, 0644);
        }

        return true;
    }

    /**
     * Create security index.php file to prevent directory listing
     *
     * @param string $indexFile Path to index.php file
     *
     * @return bool True if created successfully, false otherwise
     */
    private function createSecurityIndexFile(string $indexFile): bool
    {
        $templateFile = __DIR__ . '/index.php';

        if (!file_exists($templateFile)) {
            return false;
        }

        $indexContent = file_get_contents($templateFile);
        if ($indexContent === false) {
            return false;
        }

        if (file_put_contents($indexFile, $indexContent) === false) {
            return false;
        }

        // Set restrictive permissions on index.php file (Unix/Linux)
        if (DIRECTORY_SEPARATOR === '/') {
            @chmod($indexFile, 0644);
        }

        return true;
    }

    /**
     * Verify upload directory security on each configuration page load
     *
     * @return array Security status with warnings if any
     */
    private function checkUploadDirectorySecurity(): array
    {
        $uploadDir = _PS_MODULE_DIR_ . $this->name . '/secure_upload/';
        $warnings = [];
        $status = 'secure';

        // Check if directory exists
        if (!is_dir($uploadDir)) {
            return [
                'status' => 'error',
                'warnings' => ['Upload directory does not exist'],
            ];
        }

        // Check .htaccess file
        $htaccessFile = $uploadDir . '.htaccess';
        if (!file_exists($htaccessFile)) {
            $warnings[] = '.htaccess file is missing - directory may be accessible via web';
            $status = 'warning';
        }

        // Check index.php file
        $indexFile = $uploadDir . 'index.php';
        if (!file_exists($indexFile)) {
            $warnings[] = 'Security index.php file is missing';
            $status = 'warning';
        }

        // Check directory permissions (Unix/Linux)
        if (function_exists('fileperms') && DIRECTORY_SEPARATOR === '/') {
            $perms = fileperms($uploadDir);
            $octal = substr(sprintf('%o', $perms), -4);

            if ((int) $octal > 0755) {
                $warnings[] = 'Directory permissions may be too permissive (' . $octal . ')';
                $status = 'warning';
            }
        }

        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            $warnings[] = 'Directory is not writable - file uploads will fail';
            $status = 'error';
        }

        return [
            'status' => $status,
            'warnings' => $warnings,
        ];
    }
}
