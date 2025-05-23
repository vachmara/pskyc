<?php
namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PskycDocument extends \ObjectModel
{
    /** @var int */
    public $id_kyc_document;

    /** @var int */
    public $id_kyc_verification;

    /** @var string Document type (ID, proof_of_address…) */
    public $type;

    /** @var string Encrypted filename on disk */
    public $filename;

    /** @var int Size in bytes */
    public $filesize;

    /** @var string Mime type */
    public $mime;

    /** @var string SHA-256 hash */
    public $sha256;

    /** @var bool */
    public $encrypted = true;

    /** @var string Initialization vector (hex) */
    public $iv;

    /** @var string DateTime of upload */
    public $date_uploaded;

    /** @var string Nullable expiry date */
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
            'sha256'              => ['type' => self::TYPE_STRING, 'validate' => 'isSha1', 'size' => 64],
            'encrypted'           => ['type' => self::TYPE_BOOL,   'validate' => 'isBool'],
            'iv'                  => ['type' => self::TYPE_STRING, 'validate' => 'isSha1', 'size' => 32],
            'date_uploaded'       => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'expires_at'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate', 'required' => false],
        ],
    ];
}
