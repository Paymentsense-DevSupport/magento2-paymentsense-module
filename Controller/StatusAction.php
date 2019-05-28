<?php
/*
 * Copyright (C) 2019 Paymentsense Ltd.
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
 * @copyright   2019 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\Payments\Controller;

use Magento\Backend\Model\Session;

/**
 * Abstract action class implementing redirect actions
 */
abstract class StatusAction extends CsrfAwareAction
{
    /**
     * @var \Paymentsense\Payments\Helper\DiagnosticMessage
     */
    protected $_messageHelper;

    /**
     * Expiration time of the result of the connection check in seconds
     * Used to reduce the connection requests to the gateway
     *
     * @var int
     */
    protected $_connectionCheckExpiration = 60;

    /**
     * @var \Magento\Backend\Model\Session
     */
    protected $_backendSession;

    /**
     * @var \Paymentsense\Payments\Model\Method\Hosted|\Paymentsense\Payments\Model\Method\Direct
     */
    protected $_method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper
     * @param \Magento\Backend\Model\Session $backendSession
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper,
        Session $backendSession,
        $method
    ) {
        parent::__construct($context, $logger);
        $this->_messageHelper = $messageHelper;
        $this->_backendSession = $backendSession;
        $this->_method         = $method;
    }

    /**
     * Gets an instance of the Magento Checkout Session
     *
     * @return \Magento\Backend\Model\Session
     */
    protected function getBackendSession()
    {
        return $this->_backendSession;
    }

    /**
     * Gets the gateway connection status
     *
     * @return array
     */
    protected function getConnectionMessage()
    {
        $paymentsenseGatewayConnectionTime = $this->getBackendSession()->getPaymentsenseGatewayConnectionTime();
        if (!isset($paymentsenseGatewayConnectionTime)) {
            $paymentsenseGatewayConnectionTime = 0;
        }

        if (time()-$paymentsenseGatewayConnectionTime > $this->_connectionCheckExpiration) {
            $connectionSuccessful = $this->_method->canConnect();
            $this->getBackendSession()->setPaymentsenseGatewayConnectionStatus($connectionSuccessful?1:0);
            $this->getBackendSession()->setPaymentsenseGatewayConnectionTime(time());
        } else {
            $connectionSuccessful = $this->getBackendSession()->getPaymentsenseGatewayConnectionStatus();
        }

        return $this->_messageHelper->getConnectionMessage($connectionSuccessful);
    }
}
