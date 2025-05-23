<?php
namespace PrestaShop\Module\Pskyc\Controller\Front;

use ModuleFrontController;
use PrestaShop\Module\Pskyc\Service\KycService;
use Tools;

/**
 * Front controller for KYC verification submission and status display.
 */
class VerificationController extends ModuleFrontController
{
    /**
     * Display the KYC verification page and status.
     */
    public function initContent()
    {
        parent::initContent();

        // Only allow logged-in customers
        if (!$this->context->customer->isLogged()) {
            Tools::redirect('index.php?controller=authentication&back=module-pskyc-verification');
        }

        $kycService = new KycService();
        $isValid = $kycService->isKycValid($this->context->customer->id);

        $this->context->smarty->assign([
            'kyc_valid' => $isValid,
            'kyc_errors' => $this->errors ?? [],
            'kyc_success' => $this->success ?? '',
        ]);

        $this->setTemplate('module:pskyc/views/templates/front/verification.tpl');
    }

    /**
     * Handle KYC form submission and file upload.
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submit_kyc')) {
            $files = $_FILES['kyc_files'] ?? [];
            $normalizedFiles = $this->normalizeFilesArray($files);
            $errors = $this->validateFiles($normalizedFiles);
            if (!empty($errors)) {
                $this->errors = $errors;
                return;
            }
            try {
                $service = new KycService();
                $service->submit($this->context->customer->id, $normalizedFiles);
                $this->success = $this->l('Your KYC documents have been submitted successfully.');
                Tools::redirect('index.php?controller=module-pskyc-verification&success=1');
            } catch (\Exception $e) {
                $this->errors[] = $this->l('An error occurred while submitting your documents: ').$e->getMessage();
            }
        }
    }

    /**
     * Normalize the $_FILES array for multiple or single file uploads.
     *
     * @param array $files
     * @return array
     */
    private function normalizeFilesArray(array $files): array
    {
        $result = [];
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $result[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        } elseif (isset($files['name'])) {
            // Single file upload
            $result[] = $files;
        }
        return $result;
    }

    /**
     * Validate uploaded files for type, size, and errors.
     *
     * @param array $files
     * @return array List of error messages
     */
    private function validateFiles(array $files): array
    {
        $errors = [];
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $this->l('File upload error: ').$file['name'];
                continue;
            }
            if (!in_array($file['type'], $allowedTypes)) {
                $errors[] = $this->l('Invalid file type: ').$file['name'];
            }
            if ($file['size'] > $maxSize) {
                $errors[] = $this->l('File too large (max 5MB): ').$file['name'];
            }
        }
        return $errors;
    }
}