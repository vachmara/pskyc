<?php
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

        // Handle form submissions
        if (Tools::isSubmit('action')) {
            $this->processForm();
        }

        // Load existing verification data
        $verification = $this->getCustomerVerification();
        $documents = $verification ? $this->getVerificationDocuments($verification['id_kyc_verification']) : [];

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
     * Handles file upload and stores encrypted documents
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

            // Check if customer already has a verification in progress
            $existingVerification = $this->getCustomerVerification();
            if ($existingVerification && in_array($existingVerification['status'], ['pending', 'under_review'])) {
                $this->errors[] = $this->trans('You already have a verification request in progress.', [], 'Modules.Pskyc.Shop');
                return;
            }

            // Create new verification record
            $verificationId = $this->createVerification();
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

        } catch (Exception $e) {
            PrestaShopLogger::addLog('KYC Upload Error: ' . $e->getMessage(), 3, null, 'Pskyc');
            $this->errors[] = $this->trans('An error occurred while processing your request.', [], 'Modules.Pskyc.Shop');
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
     * Retrieves the most recent verification record for the logged-in customer
     * 
     * @return array|false Verification record array or false if none found
     */
    protected function getCustomerVerification()
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'kyc_verification` 
                WHERE `id_customer` = ' . (int)$this->context->customer->id . ' 
                ORDER BY `date_submitted` DESC LIMIT 1';
        
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Get verification documents
     * 
     * Retrieves all documents associated with a verification record
     * 
     * @param int $verificationId The verification record ID
     * @return array Array of document records
     */
    protected function getVerificationDocuments($verificationId)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'kyc_document` 
                WHERE `id_kyc_verification` = ' . (int)$verificationId . ' 
                ORDER BY `date_uploaded` ASC';
        
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Create new verification record
     * 
     * Inserts a new verification record with pending status
     * 
     * @return int|false New verification ID or false on failure
     */
    protected function createVerification()
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
     * Process documents using uploader controller logic
     * 
     * Delegates document processing to the uploader controller
     * 
     * @param int $verificationId The verification record ID
     * @param array $documents Array of documents to process
     * @return array Result array with success status and message
     */
    protected function processDocuments($verificationId, $documents)
    {
        require_once _PS_MODULE_DIR_ . 'pskyc/controllers/front/uploader.php';
        
        $uploader = new PskycUploaderModuleFrontController();
        $uploader->module = $this->module;
        
        return $uploader->processDocuments($verificationId, $documents);
    }

    /**
     * Log action to the KYC log table
     * 
     * Records customer actions and system events for audit trail
     * 
     * @param int $verificationId The verification record ID
     * @param string $action Action performed (e.g., 'documents_uploaded')
     * @param string $message Descriptive message about the action
     * @return void
     */
    protected function logAction($verificationId, $action, $message)
    {
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'kyc_log` 
                (`id_kyc_verification`, `id_customer`, `action`, `message`, `ip_address`, `user_agent`, `date_add`) 
                VALUES (' . (int)$verificationId . ', ' . (int)$this->context->customer->id . ', "' . pSQL($action) . '", 
                "' . pSQL($message) . '", "' . pSQL(Tools::getRemoteAddr()) . '", "' . pSQL($_SERVER['HTTP_USER_AGENT']) . '", NOW())';
        
        Db::getInstance()->execute($sql);
    }

    /**
     * Send notification email to customer
     * 
     * Sends email notifications based on verification status changes
     * 
     * @param string $type Email type (e.g., 'documents_submitted', 'approved', 'rejected')
     * @return void
     */
    protected function sendNotificationEmail($type)
    {
        // Implementation for sending emails based on type
        // This would integrate with PrestaShop's Mail class
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