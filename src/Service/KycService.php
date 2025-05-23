<?php
namespace PrestaShop\Module\Pskyc\Service;

use PrestaShop\Module\Pskyc\Entity\PskycVerification;
use PrestaShop\Module\Pskyc\Entity\PskycDocument;
use PrestaShop\Module\Pskyc\Entity\PskycLog;
use PrestaShop\Module\Pskyc\Helper\EncryptionHelper;
use Context;
use Tools;
use Db;
use Mail;

/**
 * Service for handling KYC verification, document upload, and status management.
 */
class KycService
{
    private string $storagePath;
    private string $encryptionKey;

    /**
     * KycService constructor.
     * @throws \RuntimeException if encryption key is not set
     */
    public function __construct()
    {
        $this->storagePath = _PS_MODULE_DIR_.'pskyc/secure_upload/';
        $this->encryptionKey = Configuration::get('PSKYC_ENCRYPTION_KEY');
        if (empty($this->encryptionKey) || strlen($this->encryptionKey) !== 64) {
            throw new \RuntimeException('Encryption key is not set or invalid.');
        }
    }

    /**
     * Submit a new KYC verification with uploaded files.
     *
     * @param int $idCustomer
     * @param array $files
     * @return PskycVerification
     * @throws \RuntimeException on file or encryption error
     */
    public function submit(int $idCustomer, array $files): PskycVerification
    {
        $verification = new PskycVerification();
        $verification->id_customer = $idCustomer;
        $verification->status = PskycVerification::STATUS_PENDING;
        $verification->date_submitted = date('Y-m-d H:i:s');
        $verification->add();

        foreach ($files as $file) {
            $content = @file_get_contents($file['tmp_name']);
            if ($content === false) {
                throw new \RuntimeException('Failed to read uploaded file.');
            }
            $iv = EncryptionHelper::generateIv();
            $encrypted = EncryptionHelper::encrypt($content, $this->encryptionKey, $iv);

            $filename = uniqid('kyc_', true).'.enc';
            $path = $this->storagePath.$filename;
            if (@file_put_contents($path, $encrypted) === false) {
                throw new \RuntimeException('Failed to save encrypted file.');
            }

            $doc = new PskycDocument();
            $doc->id_kyc_verification = $verification->id_kyc_verification;
            $doc->type = $file['kyc_type'] ?? 'unknown'; // Use a logical type, not MIME
            $doc->filename = $filename;
            $doc->filesize = $file['size'];
            $doc->mime = $file['type'];
            $doc->sha256 = EncryptionHelper::sha256($content);
            $doc->iv = $iv;
            $doc->encrypted = true;
            $doc->date_uploaded = date('Y-m-d H:i:s');
            $doc->add();

            $this->log($verification->id_kyc_verification, 'upload', 'Document uploaded: '.$filename);
        }

        return $verification;
    }

    /**
     * Approve a KYC verification.
     *
     * @param int $idVerification
     * @param string $note
     */
    public function approve(int $idVerification, string $note = ''): void
    {
        $verification = new PskycVerification($idVerification);
        $verification->status = PskycVerification::STATUS_APPROVED;
        $verification->admin_note = $note;
        $verification->date_validated = date('Y-m-d H:i:s');
        $verification->update();

        $this->log($idVerification, 'approve', $note);
        // TODO: send approval email
    }

    /**
     * Reject a KYC verification.
     *
     * @param int $idVerification
     * @param string $note
     */
    public function reject(int $idVerification, string $note): void
    {
        $verification = new PskycVerification($idVerification);
        $verification->status = PskycVerification::STATUS_REJECTED;
        $verification->admin_note = $note;
        $verification->update();

        $this->log($idVerification, 'reject', $note);
        // TODO: send rejection email
    }

    /**
     * Check if a customer has a valid (approved) KYC verification.
     *
     * @param int $idCustomer
     * @return bool
     */
    public function isKycValid(int $idCustomer): bool
    {
        $sql = 'SELECT status FROM '._DB_PREFIX_.'kyc_verification WHERE id_customer = '.(int)$idCustomer.' ORDER BY date_submitted DESC LIMIT 1';
        $status = Db::getInstance()->getValue($sql);
        return $status === PskycVerification::STATUS_APPROVED;
    }

    /**
     * Log a KYC action.
     *
     * @param int $idVerification
     * @param string $action
     * @param string $message
     */
    public function log(int $idVerification, string $action, string $message = ''): void
    {
        $log = new PskycLog();
        $log->id_kyc_verification = $idVerification;
        $log->id_employee = (int)(Context::getContext()->employee->id ?? 0);
        $log->id_customer = (int)(Context::getContext()->customer->id ?? 0);
        $log->action = $action;
        $log->message = $message;
        $log->ip_address = Tools::getRemoteAddr(); // Store as string, entity handles conversion
        $log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log->date_add = date('Y-m-d H:i:s');
        $log->add();
    }
}
