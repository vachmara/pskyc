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
 * KYC Secure Upload Cron Controller
 *
 * Handles daily maintenance tasks using the dedicated MaintenanceService
 */
class PskycCronModuleFrontController extends ModuleFrontController
{
    /**
     * @var Pskyc
     */
    public $module;

    /**
     * @var PrestaShop\Module\Pskyc\Service\MaintenanceService
     */
    private $maintenanceService;

    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
    }

    public function postProcess()
    {
        try {
            $this->initializeServices();
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Cron service initialization failed: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Service initialization failed');

            return;
        }

        // Security: Validate token to prevent unauthorized access
        $expectedToken = $this->maintenanceService->getCronToken();
        $providedToken = Tools::getValue('token');

        if ($expectedToken !== $providedToken) {
            header('HTTP/1.1 403 Forbidden');
            header('Status: 403 Forbidden');
            $this->ajaxRender('Bad token');

            return;
        }

        $action = Tools::getValue('action', 'daily_maintenance');

        switch ($action) {
            case 'daily_maintenance':
                $this->runDailyMaintenance();
                break;
            case 'send_warnings':
                $this->runExpiryWarnings();
                break;
            case 'update_expired':
                $this->runUpdateExpired();
                break;
            case 'cleanup_documents':
                $this->runDocumentCleanup();
                break;
            case 'cleanup_logs':
                $this->runLogCleanup();
                break;
            case 'cleanup_temp':
                $this->runTempCleanup();
                break;
            default:
                header('HTTP/1.1 400 Bad Request');
                header('Status: 400 Bad Request');
                $this->ajaxRender('Unknown action');
        }
    }

    /**
     * Initialize Symfony services
     *
     * Gets services using PrestaShop's service accessor
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function initializeServices()
    {
        try {
            $this->maintenanceService = $this->module->get('PrestaShop\Module\Pskyc\Service\MaintenanceService');
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Service initialization failed: ' . $e->getMessage(), 3, null, 'Pskyc');
            throw new PrestaShopException('MaintenanceService not available');
        }
    }

    /**
     * Run all daily maintenance tasks
     */
    private function runDailyMaintenance()
    {
        try {
            $results = $this->maintenanceService->runDailyMaintenance();

            // Log the maintenance run
            $this->maintenanceService->logMaintenanceRun($results);

            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Daily maintenance error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Maintenance failed: ' . $e->getMessage());
        }
    }

    /**
     * Run expiry warnings task only
     */
    private function runExpiryWarnings()
    {
        try {
            $warningDays = (int) Tools::getValue('warning_days', 30);
            $results = $this->maintenanceService->sendExpiryWarnings($warningDays);
            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Expiry warnings error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Expiry warnings failed: ' . $e->getMessage());
        }
    }

    /**
     * Run update expired verifications task only
     */
    private function runUpdateExpired()
    {
        try {
            $results = $this->maintenanceService->updateExpiredVerifications();
            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Update expired error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Update expired failed: ' . $e->getMessage());
        }
    }

    /**
     * Run document cleanup task only
     */
    private function runDocumentCleanup()
    {
        try {
            $results = $this->maintenanceService->cleanupExpiredDocuments();
            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Document cleanup error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Document cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Run log cleanup task only
     */
    private function runLogCleanup()
    {
        try {
            $retentionDays = (int) Tools::getValue('retention_days', 0);
            $results = $this->maintenanceService->cleanupOldLogs($retentionDays ?: null);
            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Log cleanup error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Log cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Run temporary files cleanup task only
     */
    private function runTempCleanup()
    {
        try {
            $maxAge = (int) Tools::getValue('max_age_hours', 24);
            $results = $this->maintenanceService->cleanupTempFiles($maxAge);
            $this->ajaxRender(json_encode($results));
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Temp cleanup error: ' . $e->getMessage(), 3, null, 'Pskyc');
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');
            $this->ajaxRender('Temp cleanup failed: ' . $e->getMessage());
        }
    }
}
