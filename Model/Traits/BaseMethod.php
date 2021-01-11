<?php
/*
 * Copyright (C) 2020 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\Payments\Model\Traits;

use Magento\Sales\Model\Order;
use Paymentsense\Payments\Helper\Logger;

/**
 * Trait containing class methods common for all payment methods
 */
trait BaseMethod
{
    use Transactions;

    /**
     * Retrieves payment method code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Creates logger
     *
     * @return \Magento\Payment\Model\Method\Logger
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createLogger()
    {
        return new Logger($this);
    }

    /**
     * Checks base currency against the allowed currency
     *
     * @param string $currencyCode
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function canUseForCurrency($currencyCode)
    {
        return $this->getModuleHelper()->isCurrencyAllowed($this->getCode(), $currencyCode);
    }

    /**
     * Sends the new order email to the customer
     *
     * @param  \Magento\Sales\Model\Order $order
     */
    public function sendNewOrderEmail($order)
    {
        $this->_orderSender->send($order);
    }

    /**
     * Determines whether the payment method is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->getConfigHelper()->isMethodActive($this->getCode());
    }

    /**
     * Determines whether the payment method is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->getConfigHelper()->isMethodConfigured($this->getCode());
    }

    /**
     * Determines whether the store is secure
     *
     * @return bool
     */
    public function isSecure()
    {
        try {
            $result = $this->getModuleHelper()->isStoreSecure();
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets Sales Order
     *
     * @param string|null $gatewayOrderId Gateway order ID
     * @return \Magento\Sales\Model\Order $order
     */
    public function getOrder($gatewayOrderId)
    {
        $result         = null;
        $orderId        = null;
        $sessionOrderId = $this->getCheckoutSession()->getLastRealOrderId();
        switch (true) {
            case empty($gatewayOrderId):
                $this->getLogger()->error('OrderID returned by the gateway is empty.');
                break;
            case empty($sessionOrderId):
                $this->getLogger()->warning(
                    'Session OrderID is empty. OrderID returned by the gateway (' . $gatewayOrderId .
                    ') will be used.'
                );
                $orderId = $gatewayOrderId;
                break;
            case $sessionOrderId !== $gatewayOrderId:
                $this->getLogger()->error(
                    'Session OrderID (' . $sessionOrderId . ') differs from the OrderID (' . $gatewayOrderId .
                    ') returned by the gateway.'
                );
                break;
            default:
                $orderId = $gatewayOrderId;
                break;
        }

        if ($orderId) {
            $objectManager = $this->getModuleHelper()->getObjectManager();
            $orderObj      = $objectManager->create(Order::class);
            $order         = $orderObj->loadByIncrementId($orderId);
            if ($order) {
                if ($order->getId()) {
                    $result = $order;
                }
            }
        }

        return $result;
    }
}
