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

use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;
use PrestaShop\Module\Pskyc\Service\VerificationService;
use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * Class PskycVerifyModuleFrontController
 *
 * Front office controller for KYC document verification
 * Handles customer document uploads and verification status display
 */
class PskycVerifyModuleFrontController extends ModuleFrontController
{
    /**
     * @var Pskyc
     */
    public $module;

    /**
     * @var VerificationService
     */
    private $verificationService;

    /**
     * @var DocumentService
     */
    private $documentService;

    /**
     * @var NotificationService
     */
    private $notificationService;

    /**
     * Initialize content for the KYC verification page
     *
     * Displays the verification form and handles form submissions
     * Redirects non-logged customers to home page
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    public function initContent()
    {
        $context = Context::getContext();
        if (empty($context->customer->id)) {
            Tools::redirect('index.php');
        }

        parent::initContent();

        $this->initializeServices();

        if (Tools::isSubmit('action')) {
            $this->processForm();
        }

        $verification = $this->getCustomerVerification();
        $documents = [];
        if ($verification) {
            $verificationWithDocs = $this->verificationService->getVerificationWithDocuments($verification['id_kyc_verification']);
            if ($verificationWithDocs && isset($verificationWithDocs['documents'])) {
                $documents = $verificationWithDocs['documents'];
            }
        }

        $this->context->smarty->assign([
            'pskyc_ps_version' => (bool) version_compare(_PS_VERSION_, '1.7', '>='),
            'pskyc_id_customer' => $context->customer->id,
            'verification' => $verification,
            'documents' => $documents,
            'token' => sha1($context->customer->secure_key),
        ]);

        $this->context->smarty->tpl_vars['page']->value['body_classes']['page-customer-account'] = true;
        $this->setTemplate('module:pskyc/views/templates/front/account/page.tpl');
    }

    /**
     * Initialize Symfony services
     *
     * Gets services using PrestaShop's service accessor
     *
     * @return void
     */
    private function initializeServices()
    {
        try {
            $this->verificationService = $this->module->get('PrestaShop\Module\Pskyc\Service\VerificationService');
            $this->documentService = $this->module->get('PrestaShop\Module\Pskyc\Service\DocumentService');
            $this->notificationService = $this->module->get('PrestaShop\Module\Pskyc\Service\NotificationService');
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Service initialization failed: ' . $e->getMessage(), 2, null, 'Pskyc');
            throw new PrestaShopException('Required services not available');
        }
    }

    /**
     * Process form submissions
     *
     * Validates security token and routes to appropriate action handler
     *
     * @return void
     */
    protected function processForm()
    {
        $action = Tools::getValue('action');
        $token = Tools::getValue('token');
        $expectedToken = sha1($this->context->customer->secure_key);

        if ($token !== $expectedToken) {
            $this->errors[] = $this->trans('Invalid security token.', [], 'Modules.Pskyc.Shop');

            return;
        }

        switch ($action) {
            case 'upload_documents':
                $this->processDocumentUpload();
                break;
            case 'reupload_document':
                $this->processReuploadDocument();
                break;
            default:
                $this->errors[] = $this->trans('Invalid action.', [], 'Modules.Pskyc.Shop');
        }
    }

    /**
     * Process document upload submission
     *
     * Validates form data, uploaded files, and creates verification record
     * Handles file upload and stores encrypted documents using Symfony services
     *
     * @return void
     */
    protected function processDocumentUpload()
    {
        try {
            $requiredFields = ['id_document_type', 'address_document_type', 'data_consent', 'document_authenticity'];
            foreach ($requiredFields as $field) {
                if (!Tools::getValue($field)) {
                    $this->errors[] = $this->trans('Please fill in all required fields.', [], 'Modules.Pskyc.Shop');

                    return;
                }
            }

            $documentType = Tools::getValue('id_document_type');
            $requiresBothSides = $this->documentService->requiresBothSides($documentType);

            // Validate uploaded files
            if ($requiresBothSides) {
                $idDocumentFront = $_FILES['id_document_front'] ?? null;
                $idDocumentBack = $_FILES['id_document_back'] ?? null;

                if (
                    !$idDocumentFront || $idDocumentFront['error'] !== UPLOAD_ERR_OK
                    || !$idDocumentBack || $idDocumentBack['error'] !== UPLOAD_ERR_OK
                ) {
                    $this->errors[] = $this->trans('Please upload both front and back sides of your identity document.', [], 'Modules.Pskyc.Shop');

                    return;
                }
                if (!$this->validateUploadedFile($idDocumentFront) || !$this->validateUploadedFile($idDocumentBack)) {
                    return;
                }
                $idDocument = null;
            } else {
                $idDocument = $_FILES['id_document'] ?? null;
                if (!$idDocument || $idDocument['error'] !== UPLOAD_ERR_OK) {
                    $this->errors[] = $this->trans('Please upload your identity document.', [], 'Modules.Pskyc.Shop');

                    return;
                }
                if (!$this->validateUploadedFile($idDocument)) {
                    return;
                }
            }

            $addressDocument = $_FILES['address_document'] ?? null;
            if (!$addressDocument || $addressDocument['error'] !== UPLOAD_ERR_OK) {
                $this->errors[] = $this->trans('Please upload your proof of address document.', [], 'Modules.Pskyc.Shop');

                return;
            }
            if (!$this->validateUploadedFile($addressDocument)) {
                return;
            }

            // Pass the correct files to the upload handler
            if ($requiresBothSides) {
                $this->processDocumentUploadWithServices(['front' => $idDocumentFront, 'back' => $idDocumentBack], $addressDocument);
            } else {
                $this->processDocumentUploadWithServices($idDocument, $addressDocument);
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('KYC Upload Error: ' . $e->getMessage(), 3, null, 'Pskyc');
            $this->errors[] = $this->trans('An error occurred while processing your request.', [], 'Modules.Pskyc.Shop');
        }
    }

    /**
     * Process document upload using Symfony services
     *
     * @param array $idDocument Identity document file data (for single-sided documents or ['front'=>...,'back'=>...] for two-sided)
     * @param array $addressDocument Address document file data
     *
     * @return void
     */
    private function processDocumentUploadWithServices($idDocument, $addressDocument)
    {
        // Only allow one active verification per customer
        $existingVerification = $this->verificationService->getMostRecentVerification($this->context->customer->id);
        if ($existingVerification && !empty($existingVerification['status']) && in_array($existingVerification['status'], ['pending', 'under_review'])) {
            $this->errors[] = $this->trans('You already have a verification request in progress.', [], 'Modules.Pskyc.Shop');

            return;
        }

        $verificationResult = $this->verificationService->createVerification($this->context->customer->id, [
            'customer_note' => Tools::getValue('additional_notes'),
        ]);

        if (empty($verificationResult['success']) || empty($verificationResult['verification_id'])) {
            $this->errors[] = $this->trans('Failed to create verification request.', [], 'Modules.Pskyc.Shop');

            return;
        }

        $verificationId = $verificationResult['verification_id'];
        $documentType = Tools::getValue('id_document_type');
        $identityUploadResults = [];

        if ($this->documentService->requiresBothSides($documentType)) {
            // Two-sided document
            $front = $idDocument['front'] ?? null;
            $back = $idDocument['back'] ?? null;
            if ($front) {
                $identityUploadResults['front'] = $this->documentService->uploadDocument(
                    $verificationId,
                    $front,
                    $documentType,
                    'front'
                );
            }
            if ($back) {
                $identityUploadResults['back'] = $this->documentService->uploadDocument(
                    $verificationId,
                    $back,
                    $documentType,
                    'back'
                );
            }
        } else {
            // Single document
            $identityUploadResults['single'] = $this->documentService->uploadDocument(
                $verificationId,
                $idDocument,
                $documentType
            );
        }

        $addressDocumentResult = $this->documentService->uploadDocument(
            $verificationId,
            $addressDocument,
            Tools::getValue('address_document_type')
        );

        // Check if all uploads were successful
        $allIdentityUploadsSuccessful = true;
        $identityErrors = [];
        foreach ($identityUploadResults as $side => $result) {
            if (empty($result['success'])) {
                $allIdentityUploadsSuccessful = false;
                $identityErrors[] = ucfirst($side) . ' side: ' . ($result['message'] ?? 'Unknown error');
            }
        }

        if ($allIdentityUploadsSuccessful && !empty($addressDocumentResult['success'])) {
            $completenessCheck = $this->documentService->checkDocumentCompleteness($verificationId);

            if (!empty($completenessCheck['complete'])) {
                $this->success[] = $this->trans('Your documents have been uploaded successfully and are being reviewed.', [], 'Modules.Pskyc.Shop');
            } else {
                $this->success[] = $this->trans('Documents uploaded successfully. Additional documents may be required.', [], 'Modules.Pskyc.Shop');
            }

            // Get customer data and verification with documents for notifications
            $customerData = $this->getCustomerData($this->context->customer->id);
            if ($customerData !== null) {
                $verification = $this->verificationService->getVerificationWithDocuments($verificationId);
                $documents = $verification['documents'] ?? [];

                // Send document upload confirmation to customer
                $this->notificationService->sendDocumentUploadConfirmation(
                    $verification,
                    $customerData,
                    $documents
                );

                // Send admin notification for new verification request
                $this->notificationService->sendAdminNotification(
                    $verification,
                    $customerData
                );
            }

            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'verify', [], true));
        } else {
            $errors = [];
            if (!$allIdentityUploadsSuccessful) {
                $errors = array_merge($errors, $identityErrors);
            }
            if (empty($addressDocumentResult['success'])) {
                $errors[] = 'Address document: ' . ($addressDocumentResult['message'] ?? 'Unknown error');
            }
            $this->errors[] = implode('. ', $errors);
        }
    }

    /**
     * Process re-upload of a single document
     *
     * @return void
     */
    protected function processReuploadDocument()
    {
        $documentId = (int) Tools::getValue('document_id');
        $file = $_FILES['reupload_file'] ?? null;
        if (!$documentId || !$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->trans('Please select a file to re-upload.', [], 'Modules.Pskyc.Shop');

            return;
        }
        if (!$this->validateUploadedFile($file)) {
            return;
        }
        $result = $this->documentService->replaceDocument($documentId, $file);
        if (!empty($result['success'])) {
            $this->success[] = $this->trans('Document re-uploaded successfully. It will be reviewed by our team.', [], 'Modules.Pskyc.Shop');
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'verify', [], true));
        } else {
            $this->errors[] = $result['message'] ?? $this->trans('Failed to re-upload document.', [], 'Modules.Pskyc.Shop');
        }
    }

    /**
     * Validate uploaded file
     *
     * Checks file upload errors, size limits, and allowed MIME types
     *
     * @param array $file Uploaded file array from $_FILES
     *
     * @return bool True if file is valid, false otherwise
     */
    protected function validateUploadedFile($file)
    {
        if (!isset($file['error'], $file['size'], $file['tmp_name'])) {
            $this->errors[] = $this->trans('Invalid file upload.', [], 'Modules.Pskyc.Shop');

            return false;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->trans('File upload failed. Please try again.', [], 'Modules.Pskyc.Shop');

            return false;
        }

        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $this->errors[] = $this->trans('File size must be less than 10MB.', [], 'Modules.Pskyc.Shop');

            return false;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes)) {
            $this->errors[] = $this->trans('Only JPG, PNG, and PDF files are allowed.', [], 'Modules.Pskyc.Shop');

            return false;
        }

        return true;
    }

    /**
     * Get customer's current verification record
     *
     * Retrieves the most recent verification record for the logged-in customer using service
     *
     * @return array|null Verification record array or null if none found
     */
    protected function getCustomerVerification()
    {
        try {
            $verifications = $this->verificationService->getMostRecentVerification($this->context->customer->id);

            return $verifications ?: null;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Get customer verification error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return null;
        }
    }

    /**
     * Get customer data
     *
     * Retrieves customer information for notifications and processing
     *
     * @param int $customerId The customer ID
     *
     * @return array|null Customer data or null if not found
     */
    private function getCustomerData(int $customerId): ?array
    {
        try {
            $customerRepository = $this->module->get('PrestaShop\Module\Pskyc\Repository\CustomerRepository');
            if ($customerRepository === false) {
                return null;
            }
            $customerData = $customerRepository->getCustomerData($customerId);

            return $customerData ?: null;
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Get customer data error: ' . $e->getMessage(), 3, null, 'Pskyc');

            return null;
        }
    }

    /**
     * Get breadcrumb links for the page
     *
     * Builds navigation breadcrumb including "My Account" and current page
     *
     * @return array Breadcrumb configuration array
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
