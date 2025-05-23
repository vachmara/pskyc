<?php
namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class representing a KYC log entry (action, message, IP, etc).
 *
 * @property int    $id_kyc_log          Log entry ID (PK)
 * @property int    $id_kyc_verification Related verification ID (FK)
 * @property int    $id_employee         Employee ID (nullable)
 * @property int    $id_customer         Customer ID (nullable)
 * @property string $action              Action type
 * @property string $message             Log message (HTML)
 * @property string $ip_address          IP address (IPv4/IPv6 string)
 * @property string $user_agent          User agent string
 * @property string $date_add            Log date/time
 */
class PskycLog extends \ObjectModel
{
    /** @var int Log entry ID (PK) */
    public $id_kyc_log;
    /** @var int Related verification ID (FK) */
    public $id_kyc_verification;
    /** @var int|null Employee ID (nullable) */
    public $id_employee;
    /** @var int|null Customer ID (nullable) */
    public $id_customer;
    /** @var string Action type */
    public $action;
    /** @var string Log message (HTML) */
    public $message;
    /** @var string IP address (IPv4/IPv6 string) */
    public $ip_address;
    /** @var string User agent string */
    public $user_agent;
    /** @var string Log date/time */
    public $date_add;

    public static $definition = [
        'table'   => 'kyc_log',
        'primary' => 'id_kyc_log',
        'fields'  => [
            'id_kyc_verification' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'id_employee'         => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'id_customer'         => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'action'              => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32],
            'message'             => ['type' => self::TYPE_HTML,   'validate' => 'isCleanHtml'],
            // Store as hex string (max 39 chars for IPv6), convert to/from binary in DB layer
            'ip_address'          => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',   'size' => 39],
            'user_agent'          => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml',  'size' => 255],
            'date_add'            => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];

    /**
     * Convert IP address to binary for DB storage (VARBINARY(16)).
     * @param string $ip
     * @return string|false
     */
    public static function ipToBinary($ip)
    {
        return @inet_pton($ip);
    }

    /**
     * Convert binary IP from DB to string (IPv4/IPv6).
     * @param string $bin
     * @return string|false
     */
    public static function binaryToIp($bin)
    {
        return @inet_ntop($bin);
    }
}
