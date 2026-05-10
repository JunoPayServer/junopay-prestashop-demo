<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class JunoPay extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'junopay';
        $this->tab = 'payments_gateways';
        $this->version = '0.1.0';
        $this->author = 'Juno Pay Server';
        $this->controllers = array('validation', 'invoice');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('JunoPay');
        $this->description = $this->l('Accept JUNO payments through Juno Pay Server.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'junopay_invoice` (
                `id_order` INT UNSIGNED NOT NULL,
                `invoice_id` VARCHAR(128) NOT NULL,
                `address` TEXT NOT NULL,
                `amount_zat` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return array();
        }
        $option = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pay with JunoPay'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setAdditionalInformation($this->l('A JUNO deposit address is shown after placing the order.'));
        return array($option);
    }

    public function createInvoice(Cart $cart)
    {
        $baseUrl = rtrim((string)Configuration::get('JUNOPAY_BASE_URL'), '/');
        $apiKey = (string)Configuration::get('JUNOPAY_MERCHANT_API_KEY');
        if ($baseUrl === '' || $apiKey === '') {
            throw new Exception('JunoPay is not configured.');
        }

        $rate = (float)(Configuration::get('JUNOPAY_ZATOSHIS_PER_CURRENCY_UNIT') ?: 100000000);
        $amountZat = (int)round((float)$cart->getOrderTotal(true, Cart::BOTH) * $rate);
        $payload = json_encode(array(
            'external_order_id' => 'prestashop-cart-' . (int)$cart->id,
            'amount_zat' => $amountZat,
            'metadata' => array(
                'platform' => 'prestashop',
                'cart_id' => (string)$cart->id,
            ),
        ));

        $ch = curl_init($baseUrl . '/v1/invoices');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $apiKey));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status > 299) {
            throw new Exception($err ?: 'JunoPay invoice creation failed.');
        }
        $decoded = json_decode($body, true);
        $invoice = isset($decoded['data']['invoice']) ? $decoded['data']['invoice'] : array();
        $address = isset($invoice['address']) ? $invoice['address'] : (isset($invoice['payment_address']) ? $invoice['payment_address'] : '');
        if (empty($invoice['invoice_id']) || $address === '') {
            throw new Exception('JunoPay returned an incomplete invoice.');
        }
        return array($invoice, $address, $amountZat);
    }
}
