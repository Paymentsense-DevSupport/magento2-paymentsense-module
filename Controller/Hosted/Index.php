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

use Paymentsense\Payments\Model\Psgw\TransactionStatus;

/**
 * Handles the response from the payment gateway
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class Index extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * Request Types
     */
    const REQ_NOTIFICATION      = '0';
    const REQ_CUSTOMER_REDIRECT = '1';

    /**
     * Response Status Codes (used in the processing of the notification of the SERVER result delivery method)
     */
    const STATUS_CODE_OK    = '0';
    const STATUS_CODE_ERROR = '30';

    /**
     * Response Messages (used in the processing of the notification of the SERVER result delivery method)
     */
    const MSG_SUCCESS              = 'Request processed successfully.';
    const MSG_NON_POST_HTTP_METHOD = 'Non-POST HTTP Method.';
    const MSG_HASH_DIGEST_ERROR    = 'Invalid Hash Digest.';
    const MSG_INVALID_ORDER        = 'Invalid Order.';

    /**
     * An array containing the status code and message outputted on the response of the gateway callbacks
     *
     * @var array
     */
    protected $_responseVars = [
        'status_code' => '',
        'message'     => '',
    ];

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Paymentsense\Payments\Model\Method\Hosted
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Paymentsense\Payments\Model\Method\Hosted $method
    ) {
        $this->_resultPageFactory = $resultPageFactory;
        parent::__construct($context, $logger, $checkoutSession, $orderFactory, $method);
    }
    // @codingStandardsIgnoreEnd

    /**
     * Processes the callbacks received from the Hosted Payment Form
     *
     * @throws \Exception
     */
    public function execute()
    {
        $config = $this->_method->getConfigHelper();
        switch ($config->getResultDeliveryMethod()) {
            case 'POST':
                $this->processPostResponse();
                break;
            case 'SERVER':
                switch ($this->getRequestType()) {
                    case self::REQ_NOTIFICATION:
                        $this->processServerNotification();
                        break;
                    case self::REQ_CUSTOMER_REDIRECT:
                        $this->processServerCustomerRedirect();
                        break;
                }
                break;
            default:
                $this->_method->getLogger()->info('Unsupported Result Delivery Method.');
                break;
        }
    }

    /**
     * Gets the request type (notification or customer redirect)
     *
     * @return string
     */
    private function getRequestType()
    {
        $postData = [];
        if ($this->getRequest()->isPost()) {
            $postData = $this->getRequest()->getPost();
        }

        return array_key_exists('StatusCode', $postData) && is_numeric($postData['StatusCode'])
            ? self::REQ_NOTIFICATION
            : self::REQ_CUSTOMER_REDIRECT;
    }

    /**
     * Processes the response of the POST result delivery method
     *
     * @throws \Exception
     */
    private function processPostResponse()
    {
        $this->_method->getLogger()->info(
            'POST Callback request from the Hosted Payment Form has been received.'
        );
        if (! $this->getRequest()->isPost()) {
            $this->_method->getLogger()->warning('Non-POST callback request triggering HTTP status code 400.');
            $this->getResponse()->setHttpResponseCode(
                \Magento\Framework\Webapi\Exception::HTTP_BAD_REQUEST
            );
            return;
        }

        $data = $this->getPostData();

        $trxStatusAndMessage = $this->_method->getTrxStatusAndMessage($this->getRequestType(), $data);

        if ($trxStatusAndMessage['TrxStatus'] !== TransactionStatus::INVALID) {
            $order = $this->_method->getOrder($data);
            if ($order) {
                $this->_method->getModuleHelper()->setOrderState($order, $trxStatusAndMessage['TrxStatus']);
                $this->_method->updatePayment($order, $data);

                if ($trxStatusAndMessage['TrxStatus'] === TransactionStatus::SUCCESS) {
                    $this->_method->sendNewOrderEmail($order);
                }
            }
        }

        $this->processActions($trxStatusAndMessage);
        $this->_method->getLogger()->info(
            'POST Callback request from the Hosted Payment Form has been processed.'
        );
    }

    /**
     * Processes the notification of the SERVER result delivery method
     *
     * @throws \Exception
     */
    private function processServerNotification()
    {
        $this->_method->getLogger()->info(
            'SERVER Callback request from the Hosted Payment Form has been received.'
        );
        if (! $this->getRequest()->isPost()) {
            $this->_method->getLogger()->warning('Non-POST HTTP Method responding with an error to the gateway.');
            $this->setError(self::MSG_NON_POST_HTTP_METHOD);
        } else {
            $data = $this->getPostData();

            $trxStatusAndMessage = $this->_method->getTrxStatusAndMessage($this->getRequestType(), $data);

            if ($trxStatusAndMessage['TrxStatus'] !== TransactionStatus::INVALID) {
                $order = $this->_method->getOrder($data);
                if ($order) {
                    $this->_method->getModuleHelper()->setOrderState($order, $trxStatusAndMessage['TrxStatus']);
                    $this->_method->updatePayment($order, $data);
                    $this->setSuccess();
                    if ($trxStatusAndMessage['TrxStatus'] === TransactionStatus::SUCCESS) {
                        $this->_method->sendNewOrderEmail($order);
                    }
                } else {
                    $this->setError(self::MSG_INVALID_ORDER);
                }
            } else {
                $this->setError(self::MSG_HASH_DIGEST_ERROR);
            }

            $this->outputResponse();
            $this->_method->getLogger()->info(
                'SERVER Callback request from the Hosted Payment Form has been processed.'
            );
        }
    }

    /**
     * Processes the customer redirect of the SERVER result delivery method
     *
     * @throws \Exception
     */
    private function processServerCustomerRedirect()
    {
        $this->_method->getLogger()->info(
            'SERVER Customer Redirect from the Hosted Payment Form has been received.'
        );

        $data = $this->getQueryData();

        if ($this->_method->isHashDigestValid($this->getRequestType(), $data)) {
            $trxStatusAndMessage = $this->_method->loadTrxStatusAndMessage($data);
        } else {
            $this->_method->getLogger()->warning('Callback request with invalid hash digest has been received.');
            $trxStatusAndMessage = [
                'TrxStatus' => TransactionStatus::INVALID,
                'Message'   => ''
            ];
        }

        $this->processActions($trxStatusAndMessage);
        $this->_method->getLogger()->info(
            'SERVER Customer Redirect from the Hosted Payment Form has been processed.'
        );
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param array $trxStatusAndMessage Array containing transaction status and message
     *
     * @throws \Magento\Framework\Exception\LocalizedException
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

    /**
     * Sets the success response message and status code
     */
    private function setSuccess()
    {
        $this->setResponse(self::STATUS_CODE_OK, self::MSG_SUCCESS);
    }

    /**
     * Sets the error response message and status code
     *
     * @param string $message Response message.
     */
    private function setError($message)
    {
        $this->setResponse(self::STATUS_CODE_ERROR, $message);
    }

    /**
     * Sets the response variables
     *
     * @param string $statusCode Response status code.
     * @param string $message Response message.
     */
    private function setResponse($statusCode, $message)
    {
        $this->_responseVars['status_code'] = $statusCode;
        $this->_responseVars['message']     = $message;
    }

    /**
     * Builds the response
     *
     */
    private function buildResponse()
    {
        return sprintf(
            'StatusCode=%d&Message=%s',
            $this->_responseVars['status_code'],
            $this->_responseVars['message']
        );
    }

    /**
     * Outputs the response
     */
    private function outputResponse()
    {
        $this->getResponse()
            ->setHeader('Content-type', 'text/html; charset=UTF-8')
            ->setBody($this->buildResponse());
    }
}
