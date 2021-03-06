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

namespace Paymentsense\Payments\Controller\Hosted;

use Magento\Backend\Model\Session;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;

/**
 * Handles the payment method status request
 */
class Status extends \Paymentsense\Payments\Controller\StatusAction
{
    /**
     * @var \Paymentsense\Payments\Helper\DiagnosticMessage
     */
    protected $_messageHelper;

    /**
     * @var \Paymentsense\Payments\Model\Method\Hosted|\Paymentsense\Payments\Model\Method\Direct
     */
    protected $_method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper
     * @param \Magento\Backend\Model\Session $backendSession
     * @param \Paymentsense\Payments\Model\Method\Hosted $method
     * @param PublicCookieMetadata|null $publicCookieMetadata
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper,
        Session $backendSession,
        \Paymentsense\Payments\Model\Method\Hosted $method,
        PublicCookieMetadata $publicCookieMetadata
    ) {
        parent::__construct($context, $logger, $messageHelper, $backendSession, $method, $publicCookieMetadata);
        $this->_messageHelper = $messageHelper;
        $this->_method        = $method;
    }

    /**
     * Handles the payment method status request
     * Outputs the statusText and className in JSON format
     */
    public function execute()
    {
        $arr = $this->_messageHelper->getStatusMessage($this->_method->isConfigured(), true);
        $arr = array_merge($arr, $this->getConnectionMessage());
        $arr = array_merge($arr, $this->getSettingsMessage());
        $arr = array_merge($arr, $this->getSameSiteCookieMessage());
        $arr = array_merge($arr, $this->getSystemTimeMessage());
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($arr));
    }
}
