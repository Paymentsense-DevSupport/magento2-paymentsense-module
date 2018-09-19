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

namespace Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset;

use Paymentsense\Payments\Model\Method\Direct;

/**
 * Renderer for the Direct method in the admin panel
 *
 * @package Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset
 */
class DirectPayment extends \Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset\Base\Payment
{
    /**
     * Retrieves the payment card logos CSS class
     *
     * @return string
     */
    protected function getPaymentCardLogosCssClass()
    {
        $method = $this->getObjectManager()->create(Direct::class);
        return $method->getConfigData('allow_amex') ? "card-logos" : "card-logos-no-amex";
    }
}
