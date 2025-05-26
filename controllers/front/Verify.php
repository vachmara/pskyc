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
        
        if ($verification && $this->verificationService) {
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
            $this->verificationService = $this->get('PrestaShop\Module\Pskyc\Service\VerificationService');
            $this->documentService = $this->get('PrestaShop\Module\Pskyc\Service\DocumentService');
            $this->notificationService = $this->get('PrestaShop\Module\Pskyc\Service\NotificationService');
            
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Service initialization failed: ' . $e->getMessage(), 2, null, 'Pskyc');
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

            // Use service if available, otherwise fallback to legacy method
            if ($this->verificationService) {
                $this->processDocumentUploadWithServices($idDocument, $addressDocument);
            } else {
                $this->processDocumentUploadLegacy($idDocument, $addressDocument);
            }

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
        $existingVerifications = $this->verificationService->getCustomerVerifications($this->context->customer->id, 1);
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
        if ($this->documentService && $this->documentService->requiresBothSides($documentType)) {
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
            if ($customerData && $this->notificationService) {
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
     * Process document upload using legacy methods
     * 
     * @param array $idDocument Identity document file data
     * @param array $addressDocument Address document file data
     * @return void
     */
    private function processDocumentUploadLegacy($idDocument, $addressDocument)
    {
        // Check if customer already has a verification in progress
        $existingVerification = $this->getCustomerVerificationLegacy();
        if ($existingVerification && in_array($existingVerification['status'], ['pending', 'under_review'])) {
            $this->errors[] = $this->trans('You already have a verification request in progress.', [], 'Modules.Pskyc.Shop');
            return;
        }

        // Create new verification record
        $verificationId = $this->createVerificationLegacy();
        if (!$verificationId) {
            $this->errors[] = $this->trans('Failed to create verification request.', [], 'Modules.Pskyc.Shop');
            return;
        }

        // Process documents via uploader controller
        $uploadResult = $this->processDocuments($verificationId, [
            'id_document' => [
                'file' => $idDocument,
                'type' => Tools::getValue('id_document_type'),
                'category' => 'identity'
            ],
            'address_document' => [
                'file' => $addressDocument,
                'type' => Tools::getValue('address_document_type'),
                'category' => 'address'
            ]
        ]);

        if ($uploadResult['success']) {
            // Log the action
            $this->logAction($verificationId, 'documents_uploaded', 'Customer uploaded KYC documents');
            
            $this->success[] = $this->trans('Your documents have been uploaded successfully and are being reviewed.', [], 'Modules.Pskyc.Shop');
            
            // Send notification email to customer
            $this->sendNotificationEmail('documents_submitted');
            
            // Redirect to avoid resubmission
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'verify', [], true));
        } else {
            $this->errors[] = $uploadResult['message'];
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
            if ($this->verificationService) {
                $verifications = $this->verificationService->getCustomerVerifications($this->context->customer->id, 1);
                return !empty($verifications) ? $verifications[0] : null;
            } else {
                return $this->getCustomerVerificationLegacy();
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Get customer verification error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return $this->getCustomerVerificationLegacy();
        }
    }

    /**
     * Get customer's current verification record using legacy method
     * 
     * @return array|false Verification record array or false if none found
     */
    protected function getCustomerVerificationLegacy()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'kyc_verification` 
                WHERE `id_customer` = ' . (int)$this->context->customer->id . ' 
                ORDER BY `date_submitted` DESC LIMIT 1';
        
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Create new verification record using legacy method
     * 
     * @return int|false New verification ID or false on failure
     */
    protected function createVerificationLegacy()
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'kyc_verification` 
                (`id_customer`, `status`, `date_submitted`) 
                VALUES (' . (int)$this->context->customer->id . ', "pending", NOW())';
        
        if (Db::getInstance()->execute($sql)) {
            return Db::getInstance()->Insert_ID();
        }
        
        return false;
    }

    /**
     * Get verification documents
     * 
     * Retrieves all documents associated with a verification record
     * This method is kept for backward compatibility but delegates to service
     * 
     * @param int $verificationId The verification record ID
     * @return array Array of document records
     */
    protected function getVerificationDocuments($verificationId)
    {
        try {
            $verification = $this->verificationService->getVerificationWithDocuments($verificationId);
            return $verification ? ($verification['documents'] ?? []) : [];
        } catch (Exception $e) {
            PrestaShopLogger::addLog('Get verification documents error: ' . $e->getMessage(), 3, null, 'Pskyc');
            return [];
        }
    }

    /**
     * Create new verification record
     * 
     * This method is deprecated - use VerificationService instead
     * Kept for backward compatibility
     * 
     * @deprecated Use VerificationService::createVerification() instead
     * @return int|false New verification ID or false on failure
     */
    protected function createVerification()
    {
        $result = $this->verificationService->createVerification($this->context->customer->id);
        return $result['success'] ? $result['verification_id'] : false;
    }

    /**
     * Process documents using uploader controller logic
     * 
     * This method is deprecated - DocumentService handles uploads now
     * Kept for backward compatibility
     * 
     * @param int $verificationId The verification record ID
     * @param array $documents Array of documents to process
     * @return array Result array with success status and message
     */
    protected function processDocuments($verificationId, $documents)
    {
        // Fallback to old uploader for backward compatibility
        if (!$this->documentService) {
            require_once _PS_MODULE_DIR_ . 'pskyc/controllers/front/uploader.php';
            
            $uploader = new PskycUploaderModuleFrontController();
            $uploader->module = $this->module;
            
            return $uploader->processDocuments($verificationId, $documents);
        }

        // Use DocumentService for new implementation
        $results = [];
        foreach ($documents as $key => $docData) {
            $result = $this->documentService->uploadDocument(
                $verificationId,
                $docData['file'],
                $docData['type']
            );
            $results[$key] = $result;
        }

        // Return consolidated result
        $allSuccessful = array_reduce($results, function($carry, $result) {
            return $carry && $result['success'];
        }, true);

        return [
            'success' => $allSuccessful,
            'message' => $allSuccessful ? 'All documents uploaded successfully' : 'Some documents failed to upload',
            'results' => $results
        ];
    }

    /**
     * Log action to the KYC log table
     * 
     * This method is deprecated - logging is handled by services now
     * Kept for backward compatibility
     * 
     * @deprecated Logging is handled automatically by services
     * @param int $verificationId The verification record ID
     * @param string $action Action performed (e.g., 'documents_uploaded')
     * @param string $message Descriptive message about the action
     * @return void
     */
    protected function logAction($verificationId, $action, $message)
    {
        // Logging is now handled automatically by services
        // This method is kept for backward compatibility
        PrestaShopLogger::addLog("KYC Action - {$action}: {$message}", 1, null, 'Pskyc');
    }

    /**
     * Send notification email to customer
     * 
     * This method is deprecated - use NotificationService instead
     * Kept for backward compatibility
     * 
     * @deprecated Use NotificationService::sendStatusChangeNotification() instead
     * @param string $type Email type (e.g., 'documents_submitted', 'approved', 'rejected')
     * @return void
     */
    protected function sendNotificationEmail($type)
    {
        // Notifications are now handled by NotificationService
        // This method is kept for backward compatibility
        if ($this->notificationService) {
            $customerData = $this->getCustomerData($this->context->customer->id);
            $verification = $this->getCustomerVerification();
            
            if ($customerData && $verification) {
                $this->notificationService->sendStatusChangeNotification($verification, $customerData, null);
            }
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