<?php

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load MockProxy system
require_once __DIR__ . '/PsKyc/MockProxy.php';

function pSQL($string, $htmlOK = false)
{
    return $string;
}