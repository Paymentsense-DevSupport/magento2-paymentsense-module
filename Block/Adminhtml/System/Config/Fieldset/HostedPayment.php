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

namespace Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset;

use Paymentsense\Payments\Model\Method\Hosted;
use Magento\Backend\Model\Auth\Session;

/**
 * Renderer for the Hosted method in the admin panel
 */
class HostedPayment extends Base\Payment
{
    /**
     * @var Hosted
     */
    protected $method;

    /**
     * @param \Magento\Backend\Block\Context $context
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\View\Helper\Js $jsHelper
     * @param \Magento\Framework\Url $urlHelper
     * @param Hosted $method
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Framework\Url $urlHelper,
        Hosted $method
    ) {
        $this->method = $method;
        parent::__construct($context, $authSession, $jsHelper, $urlHelper);
    }
}
