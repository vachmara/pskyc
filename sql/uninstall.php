<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$ok = true;
$db = Db::getInstance();

/* 1. Drop child table first (avoids FK constraint errors) */
$ok &= $db->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'kyc_document`');

/* 2. Drop log table */
$ok &= $db->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'kyc_log`');

/* 3. Then drop parent table */
$ok &= $db->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'kyc_verification`');

/* 3. Remove module configuration values */
$ok &= Configuration::deleteByName('PSKYC_RETENTION_DAYS');
$ok &= Configuration::deleteByName('PSKYC_ALLOWED_CATEGORIES');
// …add any other config keys you create

/* 4. Purge stored encrypted files */
$uploadDir = _PS_MODULE_DIR_.'pskyc/secure_upload/';
if (is_dir($uploadDir)) {
    array_map('unlink', glob($uploadDir.'*'));
    @rmdir($uploadDir);
}

return (bool) $ok;
