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

namespace Paymentsense\Payments\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;

/**
 * Observer for handling Webapi exceptions on the checkout page.
 *
 * Called before sending the response to the frontend ('controller_front_send_response_before' event)
 */
class ControllerFrontSendResponseBefore implements ObserverInterface
{
    /**
     * @var \Paymentsense\Payments\Helper\Data
     */
    protected $_moduleHelper;

    /**
     * @var \Magento\Framework\Webapi\ErrorProcessor
     */
    protected $_errorProcessor;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @param \Paymentsense\Payments\Helper\Data $moduleHelper
     * @param \Magento\Framework\Webapi\ErrorProcessor $errorProcessor
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Paymentsense\Payments\Helper\Data $moduleHelper,
        \Magento\Framework\Webapi\ErrorProcessor $errorProcessor,
        Session $checkoutSession
    ) {
        $this->_moduleHelper = $moduleHelper;
        $this->_errorProcessor = $errorProcessor;
        $this->_checkoutSession = $checkoutSession;
    }

    /**
     * Observer method running on 'controller_front_send_response_before' event
     * Adds thrown Webapi exceptions to the frontend
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            if ($this->getCheckoutSession()->getPaymentsenseCheckoutErrorMessage()) {
                $response = $observer->getEvent()->getData('response');
                if ($this->isWebapiException($response)) {
                    $exception = $this->getModuleHelper()->createWebapiException(
                        $this->getCheckoutSession()->getPaymentsenseCheckoutErrorMessage()
                    );
                    $response->setException($exception);
                    $this->getCheckoutSession()->setPaymentsenseCheckoutErrorMessage(null);
                }
            }
        } catch (\Exception $e) { // @codingStandardsIgnoreLine
        }
    }

    /**
     * Determines whether it is a Webapi exception
     *
     * @param mixed $response
     * @return bool
     */
    private function isWebapiException($response)
    {
        return $response instanceof \Magento\Framework\Webapi\Rest\Response && $response->isException();
    }

    /**
     * @return \Paymentsense\Payments\Helper\Data
     */
    protected function getModuleHelper()
    {
        return $this->_moduleHelper;
    }

    /**
     * @return \Magento\Framework\Webapi\ErrorProcessor
     */
    protected function getErrorProcessor()
    {
        return $this->_errorProcessor;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
}
