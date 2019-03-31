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

namespace Paymentsense\Payments\Controller\Hosted;

/**
 * Handles the payment method status request
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class Status extends \Paymentsense\Payments\Controller\StatusAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Backend\Model\Session $backendSession
     * @param \Paymentsense\Payments\Model\Method\Hosted
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Backend\Model\Session $backendSession,
        \Paymentsense\Payments\Model\Method\Hosted $method
    ) {
        parent::__construct($context, $logger, $backendSession, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Handles the payment method status request
     * Outputs the statusText and className in JSON format
     */
    public function execute()
    {
        $status = $this->getStatus();
        $connection = $this->getConnection();
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode(array_merge($status, $connection)));
    }

    /**
     * Gets the payment method status
     */
    private function getStatus()
    {
        switch (true) {
            case !$this->_method->isConfigured():
                $result = [
                    'statusText' => __('Unavailable (Payment method not configured)'),
                    'statusClassName' => 'red-text'
                ];
                break;
            default:
                $result = [
                    'statusText' => __('Enabled'),
                    'statusClassName' => 'green-text'
                ];
                break;
        }
        return $result;
    }
}
