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

namespace Paymentsense\Payments\Block\Frontend;

/**
 * Order Confirmation block
 *
 * Used for getting the order ID and the base URL of the store
 */
class OrderConfirmation extends \Magento\Framework\View\Element\Template
{
    /**
     * Gets the order ID
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->getRequest()->getParam('orderID');
    }

    /**
     * Gets the base URL of the store
     *
     * @return string|false
     */
    public function getContinueUrl()
    {
        try {
            $result = $this->_storeManager->getStore()->getBaseUrl();
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }
}
