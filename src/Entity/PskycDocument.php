<?php
namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class representing a KYC document upload.
 *
 * @property int    $id_kyc_document      Document ID (PK)
 * @property int    $id_kyc_verification  Related verification ID (FK)
 * @property string $type                 Document type (ID, proof_of_address, etc)
 * @property string $filename             Encrypted filename on disk
 * @property int    $filesize             Size in bytes
 * @property string $mime                 Mime type
 * @property string $sha256               SHA-256 hash (hex)
 * @property bool   $encrypted            Is file encrypted
 * @property string $iv                   Initialization vector (hex)
 * @property string $date_uploaded        Upload date/time
 * @property string $expires_at           Expiry date/time (nullable)
 */
class PskycDocument extends \ObjectModel
{
    /** @var int Document ID (PK) */
    public $id_kyc_document;
    /** @var int Related verification ID (FK) */
    public $id_kyc_verification;
    /** @var string Document type (ID, proof_of_address, etc) */
    public $type;
    /** @var string Encrypted filename on disk */
    public $filename;
    /** @var int Size in bytes */
    public $filesize;
    /** @var string Mime type */
    public $mime;
    /** @var string SHA-256 hash (hex) */
    public $sha256;
    /** @var bool Is file encrypted */
    public $encrypted = true;
    /** @var string Initialization vector (hex) */
    public $iv;
    /** @var string Upload date/time */
    public $date_uploaded;
    /** @var string|null Expiry date/time */
    public $expires_at;

    public static $definition = [
        'table'   => 'kyc_document',
        'primary' => 'id_kyc_document',
        'fields'  => [
            'id_kyc_verification' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'type'                => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64],
            'filename'            => ['type' => self::TYPE_STRING, 'validate' => 'isFileName', 'size' => 255],
            'filesize'            => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedInt'],
            'mime'                => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 128],
            // SHA-256 is 64 hex chars, IV is 32 hex chars (not SHA1)
            'sha256'              => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 64],
            'encrypted'           => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'iv'                  => ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'size' => 32],
            'date_uploaded'       => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'expires_at'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'required' => false],
        ],
    ];
}
