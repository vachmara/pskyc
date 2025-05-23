<?php
/**
 * MIT License
 * Copyright (c) 2025 Valentin Chmara
 */
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ps_kycsecureupload` (
    `id_ps_kycsecureupload` int(11) NOT NULL AUTO_INCREMENT,
    PRIMARY KEY  (`id_ps_kycsecureupload`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
