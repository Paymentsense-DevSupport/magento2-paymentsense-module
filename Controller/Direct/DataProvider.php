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

namespace Paymentsense\Payments\Controller\Direct;

/**
 * Provides data for the form redirecting to the ACS (Access Control Server)
 */
class DataProvider extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\Payments\Model\Method\Direct
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\Payments\Model\Method\Direct $method
    ) {
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Handles ajax requests and provides the form data for redirecting to the ACS
     * Generates application/json response containing the form data in JSON format
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $termUrl= $this->_method->getModuleHelper()->getUrl(
            $this->_method->getCode(),
            'index',
            ['action' => self::ACSRESPONSE]
        );
        $data = $this->_method->buildAcsFormData($termUrl);
        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($data));
    }
}
