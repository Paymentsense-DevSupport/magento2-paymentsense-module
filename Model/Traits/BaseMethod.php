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
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isSecure()
    {
        return $this->getModuleHelper()->isStoreSecure();
    }
}
