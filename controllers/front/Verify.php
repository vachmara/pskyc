<?php
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use PrestaShop\Module\Pskyc\Service\VerificationService;
use PrestaShop\Module\Pskyc\Service\DocumentService;
use PrestaShop\Module\Pskyc\Service\NotificationService;

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
     * @throws PrestaShopException
     * @return void
     */
    public function initContent()
    {
        $context = Context::getContext();
        if (empty($context->customer->id)) {
            Tools::redirect('index.php');
        }

        parent::initContent();

        // Initialize services through module
        $this->initializeServices();

        // Handle form submissions
        if (Tools::isSubmit('action')) {
            $this->processForm();
        }

        // Load existing verification data using service
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
            // Use PrestaShop's service accessor pattern
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

        // Validate token
        if ($token !== $expectedToken) {
            $this->errors[] = $this->trans('Invalid security token.', [], 'Modules.Pskyc.Shop');
            return;
        }

        switch ($action) {
            case 'upload_documents':
                $this->processDocumentUpload();
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
            // Validate required fields
            $requiredFields = ['id_document_type', 'address_document_type', 'data_consent', 'document_authenticity'];
            foreach ($requiredFields as $field) {
                if (!Tools::getValue($field)) {
                    $this->errors[] = $this->trans('Please fill in all required fields.', [], 'Modules.Pskyc.Shop');
                    return;
                }
            }

            // Validate uploaded files
            if (!isset($_FILES['id_document']) || !isset($_FILES['address_document'])) {
                $this->errors[] = $this->trans('Please upload both required documents.', [], 'Modules.Pskyc.Shop');
                return;
            }

            $idDocument = $_FILES['id_document'];
            $addressDocument = $_FILES['address_document'];

            // Validate file uploads
            if (!$this->validateUploadedFile($idDocument) || !$this->validateUploadedFile($addressDocument)) {
                return; // Error messages are set in validateUploadedFile
            }

            $this->processDocumentUploadWithServices($idDocument, $addressDocument);

        } catch (Exception $e) {
            PrestaShopLogger::addLog('KYC Upload Error: ' . $e->getMessage(), 3, null, 'Pskyc');
            $this->errors[] = $this->trans('An error occurred while processing your request.', [], 'Modules.Pskyc.Shop');
        }
    }

    /**
     * Process document upload using Symfony services
     * 
     * @param array $idDocument Identity document file data (for single-sided documents)
     * @param array $addressDocument Address document file data
     * @return void
     */
    private function processDocumentUploadWithServices($idDocument, $addressDocument)
    {
        // Check if customer already has a verification in progress using service
        $existingVerifications = $this->verificationService->getMostRecentVerification($this->context->customer->id);
        if (!empty($existingVerifications)) {
            $existingVerification = $existingVerifications[0];
            if (in_array($existingVerification['status'], ['pending', 'under_review'])) {
                $this->errors[] = $this->trans('You already have a verification request in progress.', [], 'Modules.Pskyc.Shop');
                return;
            }
        }

        // Create new verification record using service
        $verificationResult = $this->verificationService->createVerification($this->context->customer->id, [
            'admin_note' => Tools::getValue('additional_notes')
        ]);

        if (!$verificationResult['success']) {
            $this->errors[] = $this->trans('Failed to create verification request.', [], 'Modules.Pskyc.Shop');
            return;
        }

        $verificationId = $verificationResult['verification_id'];
        $documentType = Tools::getValue('id_document_type');

        // Handle identity document upload(s)
        $identityUploadResults = [];
        
        // Check if document type requires both sides
        if ($this->documentService->requiresBothSides($documentType)) {
            // Handle front/back uploads
            if (isset($_FILES['id_document_front']) && $_FILES['id_document_front']['error'] === UPLOAD_ERR_OK) {
                $identityUploadResults['front'] = $this->documentService->uploadDocument(
                    $verificationId,
                    $_FILES['id_document_front'],
                    $documentType,
                    'front'
                );
            }
            
            if (isset($_FILES['id_document_back']) && $_FILES['id_document_back']['error'] === UPLOAD_ERR_OK) {
                $identityUploadResults['back'] = $this->documentService->uploadDocument(
                    $verificationId,
                    $_FILES['id_document_back'],
                    $documentType,
                    'back'
                );
            }
        } else {
            // Handle single document upload
            $identityUploadResults['single'] = $this->documentService->uploadDocument(
                $verificationId,
                $idDocument,
                $documentType
            );
        }

        // Upload address document
        $addressDocumentResult = $this->documentService->uploadDocument(
            $verificationId,
            $addressDocument,
            Tools::getValue('address_document_type')
        );

        // Check if all uploads were successful
        $allIdentityUploadsSuccessful = true;
        $identityErrors = [];
        
        foreach ($identityUploadResults as $side => $result) {
            if (!$result['success']) {
                $allIdentityUploadsSuccessful = false;
                $identityErrors[] = ucfirst($side) . ' side: ' . $result['message'];
            }
        }

        if ($allIdentityUploadsSuccessful && $addressDocumentResult['success']) {
            // Check if all required documents are complete
            $completenessCheck = $this->documentService->checkDocumentCompleteness($verificationId);
            
            if ($completenessCheck['complete']) {
                $this->success[] = $this->trans('Your documents have been uploaded successfully and are being reviewed.', [], 'Modules.Pskyc.Shop');
            } else {
                $this->success[] = $this->trans('Documents uploaded successfully. Additional documents may be required.', [], 'Modules.Pskyc.Shop');
            }
            
            // Send notification email to customer using service
            $customerData = $this->getCustomerData($this->context->customer->id);
            if ($customerData) {
                $verification = $this->verificationService->getVerificationWithDocuments($verificationId);
                $this->notificationService->sendStatusChangeNotification(
                    $verification,
                    $customerData,
                    null // No previous status for new submission
                );
            }
            
            // Redirect to avoid resubmission
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'verify', [], true));
        } else {
            // Handle upload errors
            $errors = [];
            if (!$allIdentityUploadsSuccessful) {
                $errors = array_merge($errors, $identityErrors);
            }
            if (!$addressDocumentResult['success']) {
                $errors[] = 'Address document: ' . $addressDocumentResult['message'];
            }
            $this->errors[] = implode('. ', $errors);
        }
    }

    /**
     * Validate uploaded file
     * 
     * Checks file upload errors, size limits, and allowed MIME types
     * 
     * @param array $file Uploaded file array from $_FILES
     * @return bool True if file is valid, false otherwise
     */
    protected function validateUploadedFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->trans('File upload failed. Please try again.', [], 'Modules.Pskyc.Shop');
            return false;
        }

        // Check file size (10MB limit)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            $this->errors[] = $this->trans('File size must be less than 10MB.', [], 'Modules.Pskyc.Shop');
            return false;
        }

        // Check file type
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
            return !empty($verifications) ? $verifications[0] : null;
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
     * @return array|null Customer data or null if not found
     */
    private function getCustomerData(int $customerId): ?array
    {
        try {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'customer` WHERE `id_customer` = ' . (int)$customerId;
            return Db::getInstance()->getRow($sql);
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