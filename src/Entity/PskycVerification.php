<?php
namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class representing a KYC verification request.
 *
 * @property int         $id_kyc_verification Verification ID (PK)
 * @property int         $id_customer         Customer ID (FK)
 * @property string      $status              Verification status
 * @property string|null $admin_note          Admin note (HTML)
 * @property string      $date_submitted      Submission date/time
 * @property string|null $date_validated      Validation date/time
 * @property string|null $date_expiry         Expiry date/time
 */
class PskycVerification extends \ObjectModel
{
    /** @var int Verification ID (PK) */
    public $id_kyc_verification;
    /** @var int Customer ID (FK) */
    public $id_customer;
    /** @var string Verification status */
    public $status = self::STATUS_PENDING;
    /** @var string|null Admin note (HTML) */
    public $admin_note;
    /** @var string Submission date/time */
    public $date_submitted;
    /** @var string|null Validation date/time */
    public $date_validated;
    /** @var string|null Expiry date/time */
    public $date_expiry;

    // --- statuses
    public const STATUS_PENDING        = 'pending';
    public const STATUS_UNDER_REVIEW   = 'under_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_EXPIRED        = 'expired';
    public const STATUS_MORE_INFO      = 'requested_more_info';

    public static $definition = [
        'table'   => 'kyc_verification',
        'primary' => 'id_kyc_verification',
        'fields'  => [
            'id_customer'    => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'status'         => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'admin_note'     => ['type' => self::TYPE_HTML,   'validate' => 'isCleanHtml'],
            'date_submitted' => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_validated' => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'required' => false],
            'date_expiry'    => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'required' => false],
        ],
    ];
}
