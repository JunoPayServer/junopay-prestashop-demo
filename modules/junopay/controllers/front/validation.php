<?php

class JunoPayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$cart || !$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice) {
            Tools::redirect('index.php?controller=order');
        }

        try {
            list($invoice, $address, $amountZat) = $this->module->createInvoice($cart);
            $customer = new Customer((int)$cart->id_customer);
            $this->module->validateOrder((int)$cart->id, (int)Configuration::get('PS_OS_BANKWIRE'), (float)$cart->getOrderTotal(true, Cart::BOTH), $this->module->displayName, 'JunoPay invoice: ' . $invoice['invoice_id'] . "\nAddress: " . $address, array(), null, false, $customer->secure_key);
            $orderId = (int)$this->module->currentOrder;
            Db::getInstance()->insert('junopay_invoice', array(
                'id_order' => $orderId,
                'invoice_id' => pSQL($invoice['invoice_id']),
                'address' => pSQL($address),
                'amount_zat' => (int)$amountZat,
            ), false, true, Db::REPLACE);
            Tools::redirect($this->context->link->getModuleLink($this->module->name, 'invoice', array('id_order' => $orderId), true));
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->redirectWithNotifications('index.php?controller=order');
        }
    }
}
