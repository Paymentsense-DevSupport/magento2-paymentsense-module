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

namespace Paymentsense\Payments\Controller\Hosted;

use Paymentsense\Payments\Model\Psgw\TransactionStatus;

/**
 * Handles the response from the payment gateway
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class Index extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\Payments\Model\Method\Hosted
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\Payments\Model\Method\Hosted $method
    ) {
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Processes the response from the Hosted Payment Form
     */
    public function execute()
    {
        $this->_method->getLogger()->info('Callback request from the Hosted Payment Form has been received.');
        if (!$this->getRequest()->isPost()) {
            $this->_method->getLogger()->warning('Non-POST callback request triggering HTTP status code 400.');
            $this->getResponse()->setHttpResponseCode(
                \Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST
            );
            return;
        }

        $trxStatusAndMessage = $this->_method->getTrxStatusAndMessage($this->getPostData());

        if ($trxStatusAndMessage['TrxStatus'] !== TransactionStatus::INVALID) {
            $order = $this->_method->getOrder($this->getPostData());
            if ($order) {
                $this->_method->getModuleHelper()->setOrderState($order, $trxStatusAndMessage['TrxStatus']);
                $this->_method->updatePayment($order, $this->getPostData());

                if ($trxStatusAndMessage['TrxStatus'] === TransactionStatus::SUCCESS) {
                    $this->_method->sendNewOrderEmail($order);
                }
            }
        }

        $this->processActions($trxStatusAndMessage);
        $this->_method->getLogger()->info('Callback request from the Hosted Payment Form has been processed.');
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param array $trxStatusAndMessage Array containing transaction status and message
     */
    private function processActions($trxStatusAndMessage)
    {
        switch ($trxStatusAndMessage['TrxStatus']) {
            case TransactionStatus::SUCCESS:
                $this->executeSuccessAction();
                break;
            case TransactionStatus::FAILED:
                $this->executeFailureAction($trxStatusAndMessage['Message']);
                break;
            case TransactionStatus::INVALID:
                $this->getResponse()->setHttpResponseCode(
                    \Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST
                );
                break;
        }
    }
}
