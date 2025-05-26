<?php
/**
 * PHPUnit Bootstrap for PrestaShop KYC Module Tests
 */

// Set test environment
define('_PS_IN_TEST_', true);
define('_PS_ROOT_DIR_', dirname(__DIR__));
define('_PS_MODULE_DIR_', _PS_ROOT_DIR_ . '/modules/');

// Mock PrestaShop constants and classes that might not be available in test environment
if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.0.0');
}

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Mock PrestaShop core classes for testing
if (!class_exists('PrestaShopLogger')) {
    class PrestaShopLogger
    {
        public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false)
        {
            // Mock implementation for tests
            return true;
        }
    }
}

if (!class_exists('Configuration')) {
    class Configuration
    {
        private static $config = [];

        public static function get($key, $idLang = null)
        {
            return self::$config[$key] ?? null;
        }

        public static function updateValue($key, $value)
        {
            self::$config[$key] = $value;
            return true;
        }

        public static function deleteByName($key)
        {
            unset(self::$config[$key]);
            return true;
        }

        // Reset for tests
        public static function reset()
        {
            self::$config = [];
        }
    }
}

if (!class_exists('Context')) {
    class Context
    {
        public static function getContext()
        {
            $context = new self();
            $context->shop = new class {
                public function getBaseURL($ssl = false)
                {
                    return 'https://test-shop.com/';
                }
            };
            return $context;
        }
    }
}

if (!class_exists('Validate')) {
    class Validate
    {
        public static function isEmail($email)
        {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        }
    }
}

if (!class_exists('Mail')) {
    class Mail
    {
        public static function Send($idLang, $template, $subject, $templateVars, $to, $toName = null, $from = null, $fromName = null, $fileAttachment = null, $modeSMTP = null, $templatePath = null, $die = true, $idShop = null, $bcc = null, $replyTo = null)
        {
            // Mock implementation for tests
            return true;
        }
    }
}

// Set up test database connection mock
if (!class_exists('Db')) {
    class Db
    {
        public static function getInstance()
        {
            return new class {
                public function getRow($sql)
                {
                    return [];
                }

                public function getValue($sql)
                {
                    return null;
                }

                public function execute($sql)
                {
                    return true;
                }

                public function Insert_ID()
                {
                    return 1;
                }
            };
        }
    }
}