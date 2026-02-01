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
class PaymentModule extends PaymentModuleCore
{
    /**
     * Validate an order in database
     * Function called from a payment module.
     *
     * @param int $id_cart
     * @param int $id_order_state
     * @param float $amount_paid Amount really paid by customer (in the default currency)
     * @param string $payment_method Payment method (eg. 'Credit card')
     * @param string|null $message Message to attach to order
     * @param array $extra_vars
     * @param int|null $currency_special
     * @param bool $dont_touch_amount
     * @param string|bool $secure_key
     * @param Shop $shop
     * @param string|null $order_reference if this parameter is not provided, a random order reference will be generated
     *
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = [],
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        ?Shop $shop = null,
        ?string $order_reference = null,
    ) {
        // Only for Prestashop 8: execute the pre-order validation hook
        if (version_compare(_PS_VERSION_, '9.0.0', '<')) {
            Hook::exec('actionValidateOrderBefore', [
                'cart' => $this->context->cart,
                'customer' => $this->context->customer,
                'currency' => $this->context->currency,
                'id_order_state' => &$id_order_state,
                'payment_method' => $payment_method,
            ]);
        }

        return parent::validateOrder(
            $id_cart,
            $id_order_state,
            $amount_paid,
            $payment_method,
            $message,
            $extra_vars,
            $currency_special,
            $dont_touch_amount,
            $secure_key,
            $shop,
            $order_reference
        );
    }
}
