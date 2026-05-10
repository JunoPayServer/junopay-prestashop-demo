#!/usr/bin/env php
<?php

chdir('/var/www/html');
require_once '/var/www/html/config/config.inc.php';
require_once '/var/www/html/init.php';

Configuration::updateValue('PS_SHOP_NAME', 'JunoPay PrestaShop Demo');
Configuration::updateValue('JUNOPAY_BASE_URL', rtrim(getenv('JUNOPAY_BASE_URL') ?: '', '/'));
Configuration::updateValue('JUNOPAY_MERCHANT_API_KEY', getenv('JUNOPAY_MERCHANT_API_KEY') ?: '');
Configuration::updateValue('JUNOPAY_WEBHOOK_SECRET', getenv('JUNOPAY_WEBHOOK_SECRET') ?: '');
Configuration::updateValue('JUNOPAY_ZATOSHIS_PER_CURRENCY_UNIT', '100000000');

$db = Db::getInstance();
$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'junopay_invoice` (
    `id_order` INT UNSIGNED NOT NULL,
    `invoice_id` VARCHAR(128) NOT NULL,
    `address` TEXT NOT NULL,
    `amount_zat` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_order`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4');

$moduleId = (int)$db->getValue("SELECT id_module FROM " . _DB_PREFIX_ . "module WHERE name = 'junopay'");
if (!$moduleId) {
    $db->insert('module', array(
        'name' => 'junopay',
        'active' => 1,
        'version' => '0.1.0',
    ));
    $moduleId = (int)$db->Insert_ID();
} else {
    $db->update('module', array('active' => 1, 'version' => '0.1.0'), "id_module = " . (int)$moduleId);
}
$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'module_shop WHERE id_module = ' . (int)$moduleId);
$db->insert('module_shop', array(
    'id_module' => $moduleId,
    'id_shop' => (int)Configuration::get('PS_SHOP_DEFAULT'),
    'enable_device' => 7,
));
foreach (array('paymentOptions', 'paymentReturn', 'displayPaymentReturn') as $hookName) {
    $hookId = (int)$db->getValue("SELECT id_hook FROM " . _DB_PREFIX_ . "hook WHERE name = '" . pSQL($hookName) . "'");
    if ($hookId) {
        $db->execute('DELETE FROM ' . _DB_PREFIX_ . 'hook_module WHERE id_module = ' . (int)$moduleId . ' AND id_hook = ' . (int)$hookId);
        $position = (int)$db->getValue('SELECT COALESCE(MAX(position), 0) + 1 FROM ' . _DB_PREFIX_ . 'hook_module WHERE id_hook = ' . (int)$hookId);
        $db->insert('hook_module', array(
            'id_module' => $moduleId,
            'id_hook' => $hookId,
            'id_shop' => (int)Configuration::get('PS_SHOP_DEFAULT'),
            'position' => $position,
        ));
    }
}

$currencyId = (int)$db->getValue("SELECT id_currency FROM " . _DB_PREFIX_ . "currency WHERE iso_code = 'JUN' AND deleted = 0");
if ($currencyId) {
    $currency = new Currency($currencyId);
} else {
    $currency = new Currency();
}
$currency->name = 'JUNO';
$currency->iso_code = 'JUN';
$currency->numeric_iso_code = '999';
$currency->conversion_rate = 1;
$currency->precision = 8;
$currency->active = 1;
$currency->unofficial = 1;
if ($currencyId) {
    $currency->update();
} else {
    $currency->add();
}
Configuration::updateValue('PS_CURRENCY_DEFAULT', (int)$currency->id);
$db->execute('UPDATE ' . _DB_PREFIX_ . "currency_lang SET name = 'JUNO', symbol = 'JUNO', pattern = NULL WHERE id_currency = " . (int)$currency->id);
$db->execute('UPDATE ' . _DB_PREFIX_ . "currency_shop SET conversion_rate = 1 WHERE id_currency = " . (int)$currency->id);

$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'module_currency WHERE id_module = ' . (int)$moduleId);
foreach ($db->executeS('SELECT id_currency FROM ' . _DB_PREFIX_ . 'currency WHERE active = 1 AND deleted = 0') as $row) {
    $db->insert('module_currency', array(
        'id_module' => $moduleId,
        'id_shop' => (int)Configuration::get('PS_SHOP_DEFAULT'),
        'id_currency' => (int)$row['id_currency'],
    ));
}
$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'module_country WHERE id_module = ' . (int)$moduleId);
foreach ($db->executeS('SELECT id_country FROM ' . _DB_PREFIX_ . 'country WHERE active = 1') as $row) {
    $db->insert('module_country', array(
        'id_module' => $moduleId,
        'id_shop' => (int)Configuration::get('PS_SHOP_DEFAULT'),
        'id_country' => (int)$row['id_country'],
    ));
}
$db->execute('DELETE FROM ' . _DB_PREFIX_ . 'module_group WHERE id_module = ' . (int)$moduleId);
foreach ($db->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'group') as $row) {
    $db->insert('module_group', array(
        'id_module' => $moduleId,
        'id_shop' => (int)Configuration::get('PS_SHOP_DEFAULT'),
        'id_group' => (int)$row['id_group'],
    ));
}

$existing = (int)Db::getInstance()->getValue("SELECT pl.id_product FROM " . _DB_PREFIX_ . "product_lang pl WHERE pl.name = '1 gallon of air'");
if (!$existing) {
    $product = new Product();
    $product->name = array((int)Configuration::get('PS_LANG_DEFAULT') => '1 gallon of air');
    $product->link_rewrite = array((int)Configuration::get('PS_LANG_DEFAULT') => 'gallon-of-air');
    $product->description = array((int)Configuration::get('PS_LANG_DEFAULT') => 'A demo product for the JunoPay PrestaShop module.');
    $product->description_short = array((int)Configuration::get('PS_LANG_DEFAULT') => 'A demo product for JunoPay.');
    $product->price = 1;
    $product->active = 1;
    $product->id_category_default = 2;
    $product->id_tax_rules_group = 0;
    $product->visibility = 'both';
    $product->minimal_quantity = 1;
    $product->is_virtual = 1;
    $product->add();
    $product->addToCategories(array(2));
    StockAvailable::setQuantity((int)$product->id, 0, 999);
}
