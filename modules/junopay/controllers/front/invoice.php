<?php

class JunoPayInvoiceModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        $orderId = (int)Tools::getValue('id_order');
        $invoice = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'junopay_invoice WHERE id_order = ' . $orderId);
        $this->context->smarty->assign(array(
            'invoice' => $invoice,
            'amount' => isset($invoice['amount_zat']) ? ((int)$invoice['amount_zat'] / 100000000) . ' JUNO' : '',
        ));
        $this->setTemplate('module:junopay/views/templates/front/invoice.tpl');
    }
}
