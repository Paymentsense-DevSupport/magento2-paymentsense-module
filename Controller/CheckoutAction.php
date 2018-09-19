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

namespace Paymentsense\Payments\Controller;

use Magento\Sales\Model\Order;

/**
 * Abstract action class implementing redirect actions
 */
abstract class CheckoutAction extends \Paymentsense\Payments\Controller\Action
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
     * @var \Paymentsense\Payments\Helper\Checkout
     */
    protected $_checkoutHelper;

    /**
     * @var \Paymentsense\Payments\Model\Method\Hosted|\Paymentsense\Payments\Model\Method\Direct
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
        \Magento\Checkout\Model\Session $checkoutSession,
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
        $this->redirectToCheckoutOnePageSuccess();
        $this->_method->getLogger()->info('A redirect to the Checkout Success Page has been set.');
    }

    /**
     * Handles Failure Action
     *
     * @param string $message
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
     * Handles 3-D Secure Authentication Request Action
     */
    protected function execute3dsRequestAction()
    {
        $this->redirectToCheckoutOnePageSuccess();
    }

    /**
     * Handles Cancel Action
     *
     * @param string $message
     */
    protected function executeCancelAction($message)
    {
        $this->_method->getLogger()->info('Cancel Action with message "' . $message . '" has been triggered.');
        $this->cancelOrderAndRestoreQuote($message);
        $this->redirectToCheckoutFragmentPayment();
        $this->_method->getLogger()->info('A redirect to the Checkout Fragment Payment has been set.');
    }

    /**
     * Redirects to the Checkout Success Page
     *
     * @return void
     */
    protected function redirectToCheckoutOnePageSuccess()
    {
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Redirects to the page performing redirect to the ACS (Access Control Server)
     *
     * @return void
     */
    protected function redirectToAvsRedirectPage()
    {
        $this->_redirect('paymentsense/direct', ['action' => Action::THREEDSCANCEL]);
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
