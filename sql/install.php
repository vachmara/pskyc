<?php

/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 *
 * @author Valentin Chmara
 * @copyright Valentin Chmara
 * @license MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

/* KYC VERIFICATION */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kyc_verification` (
    `id_kyc_verification` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_customer`         INT(11) UNSIGNED NOT NULL,
    `status`              VARCHAR(32)     NOT NULL DEFAULT "pending",
    `admin_note`          TEXT            NULL,
    `customer_note`       TEXT            NULL,
    `date_submitted`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_validated`      DATETIME        NULL,
    `date_expiry`         DATETIME        NULL,
    PRIMARY KEY (`id_kyc_verification`),
    KEY `idx_customer` (`id_customer`)
  ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

/* KYC DOCUMENT */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kyc_document` (
    `id_kyc_document`     INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_kyc_verification` INT(11) UNSIGNED NOT NULL,
    `type`                VARCHAR(64)     NOT NULL,
    `side`                VARCHAR(16)     NULL,
    `filename`            VARCHAR(255)    NOT NULL,
    `filesize`            INT(11) UNSIGNED NOT NULL,
    `mime`                VARCHAR(128)    NOT NULL,
    `sha256`              CHAR(64)        NOT NULL,
    `iv`                  CHAR(32)        NOT NULL,
    `encrypted`           TINYINT(1)      NOT NULL DEFAULT 1,
    `date_uploaded`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at`          DATETIME        NULL,
    `status`              VARCHAR(32)     NOT NULL DEFAULT "pending",
    `admin_note`          TEXT            NULL,
    PRIMARY KEY (`id_kyc_document`),
    KEY `idx_verif` (`id_kyc_verification`),
    CONSTRAINT `fk_kyc_document_verification` FOREIGN KEY (`id_kyc_verification`) REFERENCES `' . _DB_PREFIX_ . 'kyc_verification` (`id_kyc_verification`) ON DELETE CASCADE
  ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

/* KYC LOG */
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'kyc_log` (
    `id_kyc_log`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_kyc_verification` INT(11) UNSIGNED NOT NULL,
    `id_employee`         INT(11) UNSIGNED NULL,
    `id_customer`         INT(11) UNSIGNED NULL,
    `action`              VARCHAR(32)     NOT NULL,
    `message`             TEXT            NULL,
    `ip_address`          VARCHAR(39)     NULL,
    `user_agent`          VARCHAR(255)    NULL,
    `date_add`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_kyc_log`),
    KEY `idx_verif` (`id_kyc_verification`),
    KEY `idx_employee` (`id_employee`),
    KEY `idx_customer` (`id_customer`),
    CONSTRAINT `fk_kyc_log_verification` FOREIGN KEY (`id_kyc_verification`) REFERENCES `' . _DB_PREFIX_ . 'kyc_verification` (`id_kyc_verification`) ON DELETE CASCADE
  ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;
