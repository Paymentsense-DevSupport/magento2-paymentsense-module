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

namespace Paymentsense\Payments\Controller;

use Magento\Sales\Model\Order;
use Magento\Checkout\Model\Session;
use Paymentsense\Payments\Model\Method\Hosted;
use Paymentsense\Payments\Model\Method\Direct;
use Paymentsense\Payments\Model\Method\Moto;

/**
 * Abstract action class implementing redirect actions
 */
abstract class CheckoutAction extends CsrfAwareAction
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var Hosted|Direct|Moto
     */
    protected $_method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        $method
    ) {
        parent::__construct($context, $logger);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory    = $orderFactory;
        $this->_method          = $method;
    }

    /**
     * Gets an instance of the Magento Checkout Session
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Gets an instance of the Magento Order Factory
     * It can be used to instantiate an order
     *
     * @return \Magento\Sales\Model\OrderFactory
     */
    protected function getOrderFactory()
    {
        return $this->_orderFactory;
    }

    /**
     * Gets an instance of the current Checkout Order Object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $result = null;
        $orderId = $this->getCheckoutSession()->getLastRealOrderId();
        if (isset($orderId)) {
            $order = $this->getOrderFactory()->create()->loadByIncrementId($orderId);
            if ($order->getId()) {
                $result = $order;
            }
        }
        return $result;
    }

    /**
     * Cancels the current order and restores the quote
     *
     * @param string $comment
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    protected function cancelOrderAndRestoreQuote($comment)
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();
        if ($order->getId() && $order->getState() != Order::STATE_CANCELED) {
            $order->registerCancellation($comment)->save();
        }
        $this->getCheckoutSession()->restoreQuote();
    }

    /**
     * Handles Success Action
     */
    protected function executeSuccessAction()
    {
        $this->_method->getLogger()->info('Success Action has been triggered.');
        $this->redirectToOrderConfirmation();
        $this->_method->getLogger()->info('A redirect to the Order Confirmation has been set.');
    }

    /**
     * Handles Failure Action
     *
     * @param string $message
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeFailureAction($message)
    {
        $this->_method->getLogger()->info('Failure Action with message "' . $message . '" has been triggered.');
        $this->getMessageManager()->addErrorMessage($message);
        $this->cancelOrderAndRestoreQuote($message);
        $this->redirectToCheckoutCart();
        $this->_method->getLogger()->info('A redirect to the Checkout Cart has been set.');
    }

    /**
     * Handles Cancel Action
     *
     * @param string $message
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function executeCancelAction($message)
    {
        $this->_method->getLogger()->info('Cancel Action with message "' . $message . '" has been triggered.');
        $this->cancelOrderAndRestoreQuote($message);
        $this->redirectToCheckoutFragmentPayment();
        $this->_method->getLogger()->info('A redirect to the Checkout Fragment Payment has been set.');
    }

    /**
     * Redirects to the Order Confirmation Page
     *
     * @return void
     */
    protected function redirectToOrderConfirmation()
    {
        $sessionOrderId = $this->getCheckoutSession()->getLastRealOrderId();
        $gatewayOrderId = $this->getRequest()->getParam('OrderID');
        $orderId        = $sessionOrderId ? $sessionOrderId : $gatewayOrderId;
        $method         = ($this->_method instanceof Hosted) ? 'hosted' : 'direct';
        $this->_redirect("paymentsense/$method/orderconfirmation", ['orderID' => $orderId]);
    }

    /**
     * Redirects to the Checkout Cart
     *
     * @return void
     */
    protected function redirectToCheckoutCart()
    {
        $this->_redirect('checkout/cart');
    }

    /**
     * Redirects to the Checkout Payment Page
     *
     * @return void
     */
    protected function redirectToCheckoutFragmentPayment()
    {
        $this->_redirect('checkout', ['_fragment' => 'payment']);
    }

    /**
     * Gets the redirect action
     *
     * @return string
     */
    protected function getReturnAction()
    {
        return $this->getRequest()->getParam('action');
    }
}
