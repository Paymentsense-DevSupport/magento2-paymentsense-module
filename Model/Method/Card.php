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

namespace Paymentsense\Payments\Model\Method;

use Paymentsense\Payments\Model\Psgw\Psgw;
use Paymentsense\Payments\Model\Psgw\DataBuilder;
use Paymentsense\Payments\Model\Psgw\TransactionType;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Paymentsense\Payments\Model\Traits\BaseMethod;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Abstract Card class used by the Direct and MOTO payment methods
 *
 * @package Paymentsense\Payments\Model\Method
 */
abstract class Card extends \Magento\Payment\Model\Method\Cc
{
    use BaseMethod;

    protected $_code;
    protected $_canOrder                = true;
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canCancelInvoice        = true;
    protected $_canVoid                 = true;
    protected $_isInitializeNeeded      = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canSaveCc               = false;

    /**
     * @var OrderSender
     */
    protected $_orderSender;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\App\Action\Context $actionContext
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Paymentsense\Payments\Helper\Data $moduleHelper
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\App\Action\Context $actionContext,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Paymentsense\Payments\Helper\Data $moduleHelper,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $logger = $this->createLogger();
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_actionContext   = $actionContext;
        $this->_storeManager    = $storeManager;
        $this->_checkoutSession = $checkoutSession;
        $this->_moduleHelper    = $moduleHelper;
        $this->_orderSender     = $orderSender;
        $this->_configHelper    = $this->getModuleHelper()->getMethodConfig($this->getCode());
    }

    /**
     * Gets the logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger->getLogger();
    }

    /**
     * Gets the payment action based on the transaction type
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $action = null;
        $config = $this->getConfigHelper();
        $transactionType = $config->getTransactionType();
        switch ($transactionType) {
            case TransactionType::PREAUTH:
                $action = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE;
                break;
            case TransactionType::SALE:
                $action = \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
                break;
            default:
                $message = sprintf($this->getHelper()->__('Transaction type is "%s" not supported', $transactionType));
                $this->getLogger()->error($message);
                $this->getModuleHelper()->throwWebapiException(
                    sprintf('Transaction type is "%s" not supported', $transactionType)
                );
        }

        return $action;
    }

    /**
     * Builds the data for the form redirecting to the ACS
     *
     * @param string $termUrl The callback URL when returning from the ACS
     *
     * @return array
     */
    public function buildAcsFormData($termUrl)
    {
        $fields          = null;
        $checkoutSession = $this->getCheckoutSession();
        if (!empty($checkoutSession)) {
            $order = $checkoutSession->getLastRealOrder();
            if (!empty($order)) {
                $orderId = $order->getRealOrderId();
                $fields  = [
                    'url' => $checkoutSession->getPaymentsenseAcsUrl(),
                    'elements' => [
                        'PaReq' => $checkoutSession->getPaymentsensePaReq(),
                        'MD' => $checkoutSession->getPaymentsenseMD(),
                        'TermUrl' => $termUrl
                    ]
                ];
                $this->getLogger()->info('Preparing ACS redirect for order #' . $orderId);
            }
        }
        return $fields;
    }

    /**
     * Performs PREAUTH transaction for new orders placed using the "ACTION_AUTHORIZE" action
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Exception
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->getLogger()->info('ACTION_AUTHORIZE has been triggered.');
        $order = $payment->getOrder();
        if ($this->_canUseCheckout) {
            $order->setCanSendNewEmailFlag(false);
        }
        $orderId = $order->getIncrementId();
        $this->getLogger()->info('Preparing PREAUTH transaction for order #' . $orderId);
        return $this->processInitialTransaction($payment, $amount);
    }

    /**
     *  Performs SALE transaction for new orders placed using the "ACTION_AUTHORIZE_CAPTURE" action
     *  and COLLECTION transaction for existing orders
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $authTransaction = $this->getModuleHelper()->lookUpAuthorisationTransaction($payment);

        if (isset($authTransaction)) {
            // Existing order
            try {
                $this->getLogger()->info('Preparing COLLECTION transaction for order #' . $orderId);
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            // New order
            $this->getLogger()->info('ACTION_AUTHORIZE_CAPTURE has been triggered.');
            if ($this->_canUseCheckout) {
                $order->setCanSendNewEmailFlag(false);
            }
            $config = $this->getConfigHelper();
            $this->getLogger()->info(
                'Preparing ' . $config->getTransactionType() . ' transaction for order #' . $orderId
            );
            return $this->processInitialTransaction($payment, $amount);
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Builds the input data array for the initial transaction (Card Details Transaction)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function buildInitialTransactionData($payment, $amount)
    {
        $config         = $this->getConfigHelper();
        $order          = $payment->getOrder();
        $orderId        = $order->getRealOrderId();
        $billingAddress = $order->getBillingAddress();
        if (empty($billingAddress)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('Billing address is empty.')
                )
            );
        }
        $transactionType = $config->getTransactionType();
        $cardName = (!empty($payment->getCcOwner())) ?
            $payment->getCcOwner() :
            $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
        return [
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => DataBuilder::getCurrencyIsoCode($order->getOrderCurrencyCode()),
            'TransactionType'  => $transactionType,
            'OrderID'          => $orderId,
            'OrderDescription' => $order->getRealOrderId() . ': New order',
            'CardName'         => $cardName,
            'CardNumber'       => $payment->getCcNumber(),
            'ExpMonth'         => $payment->getCcExpMonth(),
            'ExpYear'          => substr($payment->getCcExpYear(), -2),
            'CV2'              => $payment->getCcCid(),
            'IssueNumber'      => '',
            'Address1'         => $billingAddress->getStreetLine(1),
            'Address2'         => $billingAddress->getStreetLine(2),
            'Address3'         => $billingAddress->getStreetLine(3),
            'Address4'         => $billingAddress->getStreetLine(4),
            'City'             => $billingAddress->getCity(),
            'State'            => $billingAddress->getRegionCode(),
            'PostCode'         => $billingAddress->getPostcode(),
            'CountryCode'      => DataBuilder::getCountryIsoCode($billingAddress-> getCountryId()),
            'EmailAddress'     => $order->getCustomerEmail(),
            'PhoneNumber'      => $billingAddress->getTelephone(),
            'IPAddress'        => $order->getRemoteIp(),
        ];
    }

    /**
     * Processes initial transaction (Card Details Transaction)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    protected function processInitialTransaction($payment, $amount)
    {
        $config            = $this->getConfigHelper();
        $order             = $payment->getOrder();
        $orderId           = $order->getRealOrderId();
        $transactionType   = $config->getTransactionType();
        $trxData           = $this->buildInitialTransactionData($payment, $amount);
        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);

        try {
            $errorMessage = null;
            $response     = $psgw->performCardDetailsTxn($trxData);
            $status       = $response['StatusCode'];
            if ($status !== false) {
                $payment
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending($status === TransactionResultCode::INCOMPLETE)
                    ->setIsTransactionClosed(false)
                    ->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $response);
                switch ($status) {
                    case TransactionResultCode::SUCCESS:
                        $isTransactionFailed = false;
                        break;
                    case TransactionResultCode::INCOMPLETE:
                        if ($this->is3dsSupported()) {
                            $isTransactionFailed = empty($response['ACSURL']) ||
                                empty($response['PaReq']) ||
                                empty($response['CrossReference']);
                            $errorMessage = __('Transaction failed. ACSURL, PaReq or CrossReference is empty.');
                        } else {
                            $isTransactionFailed = true;
                            $errorMessage = __('Please ensure you are using a Paymentsense MOTO account for MOTO ') .
                                __('transactions (this will have a different merchant id to your ECOM account).');
                        }
                        break;
                    default:
                        $isTransactionFailed = true;
                        $errorMessage = 'Transaction failed. Payment Gateway Message: ' . $response['Message'];
                }
            } else {
                $isTransactionFailed = true;
                $errorMessage = 'Transaction failed. ' . $response['Message'];
            }

            if ($isTransactionFailed) {
                $this->getCheckoutSession()->setPaymentsenseCheckoutErrorMessage($errorMessage);
                $this->getModuleHelper()->throwWebapiException($errorMessage);
            }
            if ($status === TransactionResultCode::INCOMPLETE) {
                $this->getCheckoutSession()->setPaymentsenseAcsUrl($response['ACSURL']);
                $this->getCheckoutSession()->setPaymentsensePaReq($response['PaReq']);
                $this->getCheckoutSession()->setPaymentsenseMD($response['CrossReference']);
            } else {
                $this->getCheckoutSession()->setPaymentsenseAcsUrl(null);
                $this->getCheckoutSession()->setPaymentsense3dsResponseMessage(null);
                $this->getCheckoutSession()->setPaymentsensePaReq(null);
                $this->getCheckoutSession()->setPaymentsenseMD(null);
                if ($this->_canUseCheckout && ($status === TransactionResultCode::SUCCESS)) {
                    $order->setCanSendNewEmailFlag(true);
                }
            }

            $this->getLogger()->info(
                $transactionType . ' transaction ' . $response['CrossReference'] .
                ' has been performed with status code "' . $response['StatusCode'] . '".'
            );
        } catch (\Exception $e) {
            $logInfo = $transactionType . ' transaction for order #' . $orderId .
                ' failed with message "' . $e->getMessage() . '"';
            $this->getLogger()->warning($logInfo);
            $this->getCheckoutSession()->setPaymentsenseCheckoutErrorMessage($e->getMessage());
            $this->getModuleHelper()->throwWebapiException($e->getMessage());
        }
        return $this;
    }

    /**
     * Processes the 3-D Secure response from the ACS
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $postData The POST variables received from the ACS
     * @return array Array containing StatusCode and Message
     */
    public function process3dsResponse($order, $postData)
    {
        $orderId = $order->getRealOrderId();
        $config  = $this->getConfigHelper();
        $status  = null;
        $message = "Invalid response received from the ACS.";
        if (array_key_exists('PaRes', $postData) && array_key_exists('MD', $postData)) {
            $trxData = [
                'MerchantID'     => $config->getMerchantId(),
                'Password'       => $config->getPassword(),
                'CrossReference' => $postData['MD'],
                'PaRES'          => $postData['PaRes'],
            ];

            $objectManager     = $this->getModuleHelper()->getObjectManager();
            $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
            $psgw              = new Psgw($zendClientFactory);

            try {
                $this->getLogger()->info('Preparing 3-D Secure authentication for order #' . $orderId);
                $response = $psgw->perform3dsAuthTxn($trxData);
                $status   = $response['StatusCode'];

                $this->getLogger()->info(
                    '3-D Secure authentication transaction ' . $response['CrossReference'] .
                    ' has been performed with status code "' . $response['StatusCode'] . '".'
                );

                $isTransactionFailed = $status !== TransactionResultCode::SUCCESS;
                $payment             = $order->getPayment();
                $payment
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending(false)
                    ->setIsTransactionClosed($isTransactionFailed)
                    ->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $response);

                $message = $isTransactionFailed
                    ? '3-D Secure Authentication failed. Payment Gateway Message: ' . $response['Message']
                    : '';

                $this->getModuleHelper()->setOrderState($order, $status, $message);

                $response['OrderID']         = $orderId;
                $response['Amount']          = $order->getTotalDue() * 100;
                $response['TransactionType'] = $config->getTransactionType();

                $this->updatePayment($order, $response);
                if (!$isTransactionFailed) {
                    $this->sendNewOrderEmail($order);
                }
            } catch (\Exception $e) {
                $logInfo = 'An error occurred while processing 3-D Secure Authentication response for order #' .
                    $orderId . ': "' . $e->getMessage() . '"';
                $this->getLogger()->warning($logInfo);
                $message = 'An error occurred while retrieving the status of your payment. ' .
                    'Please contact us quoting order #' . $orderId . '.';
                $this->getModuleHelper()->setOrderState($order, TransactionResultCode::INCOMPLETE);
            }
        }

        return [
            'StatusCode' => $status,
            'Message'    => $message
        ];
    }

    /**
     * Determines method availability
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) &&
            $this->getConfigHelper()->isMethodAvailable($this->getCode()) &&
            $this->getModuleHelper()->isStoreSecure();
    }

    /**
     * Determines whether the processing of 3-D Secure enrolled cards is supported
     *
     * @return bool
     */
    public function is3dsSupported()
    {
        return $this->_canUseCheckout;
    }

    /**
     * Gets configuration data for a given field
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $result = parent::getConfigData($field, $storeId);
        if ('cctypes' == $field) {
            $result = trim(str_replace('AE', '', $result), ',');
            if (parent::getConfigData('allow_amex', $storeId)) {
                $result = 'AE,' .$result ;
            }
        }
        return $result;
    }
}
