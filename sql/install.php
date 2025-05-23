<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kyc_verifications` (
    `id_kyc_verification` INT(11) NOT NULL AUTO_INCREMENT,
    `id_customer` INT(11) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT "pending",
    `admin_note` TEXT NULL,
    `date_submitted` DATETIME NOT NULL,
    `date_validated` DATETIME NULL,
    `date_expiry` DATETIME NULL,
    PRIMARY KEY (`id_kyc_verification`),
    KEY `id_customer` (`id_customer`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kyc_documents` (
    `id_kyc_document` INT(11) NOT NULL AUTO_INCREMENT,
    `id_kyc_verification` INT(11) NOT NULL,
    `type` VARCHAR(64) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `filepath` VARCHAR(255) NOT NULL,
    `filesize` INT(11) NOT NULL,
    `encrypted` TINYINT(1) NOT NULL DEFAULT 1,
    `date_uploaded` DATETIME NOT NULL,
    PRIMARY KEY (`id_kyc_document`),
    KEY `id_kyc_verification` (`id_kyc_verification`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
