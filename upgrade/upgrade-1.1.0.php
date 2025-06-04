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

/**
 * Upgrade function for PsKyc module version 1.1.0
 *
 * This function is automatically called by PrestaShop when upgrading
 * the module from a previous version to 1.1.0
 *
 * @param Pskyc $module The module instance
 *
 * @return bool True if upgrade successful, false otherwise
 */
function upgrade_module_1_1_0($module)
{
    // Update module configuration with new default values if they don't exist
    if (!Configuration::get('PSKYC_AUTO_NOTIFICATIONS')) {
        Configuration::updateValue('PSKYC_AUTO_NOTIFICATIONS', true);
    }

    // Ensure encryption key exists and is valid for new encryption service
    $key = Configuration::get('PSKYC_ENCRYPTION_KEY');
    if (empty($key) || !ctype_xdigit($key) || strlen($key) !== 64) {
        // Generate new 256-bit encryption key in hexadecimal format
        $newKey = bin2hex(random_bytes(32));
        Configuration::updateValue('PSKYC_ENCRYPTION_KEY', $newKey);
    }

    // Register any new hooks that were added in 1.1.0
    $newHooks = [
        'registerGDPRConsent',
        'actionDeleteGDPRCustomer',
        'actionExportGDPRData',
    ];

    foreach ($newHooks as $hook) {
        if (!$module->isRegisteredInHook($hook)) {
            $module->registerHook($hook);
        }
    }

    // Clear any caches to ensure new templates and translations are loaded
    if (method_exists('Tools', 'clearCache')) {
        Tools::clearCache();
    }

    // Clear Smarty cache to ensure new templates are loaded
    if (isset($module->context->smarty)) {
        $module->context->smarty->clearAllCache();
    }

    // Update module tabs if needed (for Symfony routing updates)
    $module->tabs = [
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

    return true;
}
