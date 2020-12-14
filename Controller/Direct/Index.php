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

use Paymentsense\Payments\Controller\Action;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Magento\Checkout\Model\Session;

/**
 * Front Controller for Paymentsense Direct method
 */
class Index extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $_resultPageFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\Payments\Model\Method\Direct
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Psr\Log\LoggerInterface $logger,
        Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\Payments\Model\Method\Direct $method
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }

    /**
     * Handles the new orders placed
     *
     * @return null|\Magento\Framework\View\Result\Page
     *
     * @throws \Exception
     */
    public function execute()
    {
        $result = null;
        switch ($this->getReturnAction()) {
            case self::ACSRESPONSE:
                $this->processAcsResponse();
                break;
            case self::THREEDSCOMPLETE:
                $this->process3dsComplete();
                break;
            case self::THREEDSERROR:
                $this->process3dsError();
                break;
            case self::THREEDSCANCEL:
                $this->process3dsCancel();
                break;
            default:
                $result = $this->processCardDetailsTxnReturn();
        }
        return $result;
    }

    /**
     * Performs redirection to the ACS (Access Control Server)
     *
     * @return \Magento\Framework\View\Result\Page
     */
    private function performAcsRedirect()
    {
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->addHandle('acs_request');
        return $resultPage;
    }

    /**
     * Processes the response from the ACS (Access Control Server)
     *
     * @throws \Exception
     */
    private function processAcsResponse()
    {
        $transactionResult = [
            'StatusCode' => '',
            'Message'    => ''
        ];
        $postData = $this->getPostData();
        $this->_method->getLogger()->info('Callback request from the ACS has been received.');
        $order = $this->getOrder();
        if (isset($order)) {
            $action = Action::THREEDSCOMPLETE;
            $transactionResult = $this->_method->process3dsResponse($order, $postData);
            $this->getCheckoutSession()->setPaymentsense3dsResponseMessage($transactionResult['Message']);
        } else {
            $action = Action::THREEDSERROR;
        }
        $link = $this->_method->getModuleHelper()->getUrlBuilder()->getUrl(
            'paymentsense/direct',
            ['action' => $action]
        );
        $html='<html><head><script>window.top.location.href = "' . $link . '";</script></head></html>';
        $this->getResponse()->setBody($html);
        $this->getCheckoutSession()->setPaymentsenseAcsUrl(null);
        $this->_method->getLogger()->info(
            'Callback request from the ACS has been processed. ' .
            'Transaction status code was "' . $transactionResult['StatusCode'] . '".'
        );
    }

    /**
     * Performs redirection based on the response from the ACS (Access Control Server)
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function process3dsComplete()
    {
        $message = $this->getCheckoutSession()->getPaymentsense3dsResponseMessage();
        if ($message == '') {
            $this->executeSuccessAction();
        } else {
            $this->executeFailureAction($message);
        }
        $this->getCheckoutSession()->setPaymentsense3dsResponseMessage(null);
    }

    /**
     * Processes the errors appeared during the 3D Secure authentication
     *
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function process3dsError()
    {
        $message = "A 3-D Secure authentication error occurred while processing the order";
        $order = $this->getOrder();
        if (isset($order)) {
            $this->_method->getModuleHelper()->setOrderState($order, TransactionResultCode::FAILED, $message);
        }
        $this->executeCancelAction($message);
    }

    /**
     * Processes the cancellation during the 3D Secure authentication
     *
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function process3dsCancel()
    {
        $message = "Customer cancelled the order at the 3-D Secure authentication";
        $order = $this->getOrder();
        if (isset($order)) {
            $this->_method->getModuleHelper()->setOrderState($order, TransactionResultCode::FAILED, $message);
        }
        $this->executeCancelAction($message);
    }

    /**
     * Handles the return action after processing the Card Details Transaction.
     * If the card is enrolled in a 3-D Secure scheme a redirect to the ACS will be made
     *
     * @return null|\Magento\Framework\View\Result\Page
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function processCardDetailsTxnReturn()
    {
        $result = null;
        $order = $this->getOrder();
        if (isset($order)) {
            $acsUrl = $this->getCheckoutSession()->getPaymentsenseAcsUrl();
            if (isset($acsUrl)) {
                $result = $this->performAcsRedirect();
            } else {
                $this->executeSuccessAction();
            }
        } else {
            $this->executeCancelAction('An error occurred. Order not found.');
        }
        return $result;
    }
}
