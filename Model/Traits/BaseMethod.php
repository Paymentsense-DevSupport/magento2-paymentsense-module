<?php
/*
 * Copyright (C) 2018 Paymentsense Ltd.
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
 * @copyright   2018 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\Payments\Model\Traits;

use Paymentsense\Payments\Helper\Logger;

/**
 * Trait containing class methods common for all payment methods
 */
trait BaseMethod
{
    use CardDetailsTransactions;
    use CrossReferenceTransactions;

    /**
     * Retrieve payment method code
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
     */
    public function createLogger()
    {
        return new Logger($this->getCode());
    }

    /**
     * Retrieves the Transaction Type
     *
     * @return string
     */
    public function getConfigTransactionType()
    {
        return $this->getConfigData('transaction_type');
    }

    /**
     * Checks base currency against the allowed currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        return $this->getModuleHelper()->isCurrencyAllowed($this->getCode(), $currencyCode);
    }
}
