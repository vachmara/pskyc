<?php

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load mock classes first
require_once __DIR__ . '/PsKyc/MockProxy.php';

// Load interface before implementations
require_once __DIR__ . '/PsKyc/Interface/FileSystemInterface.php';

// Load virtual file system classes
require_once __DIR__ . '/PsKyc/Mock/VirtualFileSystem.php';
require_once __DIR__ . '/PsKyc/Mock/VirtualFileSystemAdapter.php';

define('_PS_MODULE_DIR_', __DIR__ . '/../modules/pskyc/');
define('_PS_THEME_DIR_', __DIR__ . '/../themes/default/');

function pSQL($string, $htmlOK = false)
{
    return $string;
}
