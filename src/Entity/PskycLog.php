<?php
namespace PrestaShop\Module\Pskyc\Entity;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PskycLog extends \ObjectModel
{
    public $id_kyc_log;
    public $id_kyc_verification;
    public $id_employee;
    public $id_customer;
    public $action;
    public $message;
    public $ip_address;
    public $user_agent;
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
            'ip_address'          => ['type' => self::TYPE_STRING, 'validate' => 'isAnything',   'size' => 39],
            'user_agent'          => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml',  'size' => 255],
            'date_add'            => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];
}
