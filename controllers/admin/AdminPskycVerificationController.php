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

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

class AdminPskycVerificationController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->bootstrap = true;
    }

    public function initContent()
    {
        parent::initContent();

        $targetUrl = $this->context->link->getAdminLink('AdminModules') . '&configure=pskyc';

        $symfonyContainerClass = '\\PrestaShop\\PrestaShop\\Adapter\\SymfonyContainer';
        if (class_exists($symfonyContainerClass)) {
            $container = SymfonyContainer::getInstance();
            if ($container !== null && $container->has('router')) {
                try {
                    $targetUrl = $container->get('router')->generate('ps_pskyc_verification_index');
                } catch (Exception $e) {
                    PrestaShopLogger::addLog('KYC admin tab redirect error: ' . $e->getMessage(), 2, null, 'Pskyc');
                }
            }
        }

        Tools::redirectAdmin($targetUrl);
    }
}
