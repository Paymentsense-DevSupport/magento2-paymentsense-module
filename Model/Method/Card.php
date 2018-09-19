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
        $this->_configHelper    = $this->getModuleHelper()->getMethodConfig($this->getCode());
    }

    /**
     * Gets the logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
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
        $transactionTypeActions = [
            TransactionType::PREAUTH =>
                \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE,
            TransactionType::SALE    =>
                \Magento\Payment\Model\Method\AbstractMethod::ACTION_AUTHORIZE_CAPTURE,
        ];
        $transactionType = $this->getConfigTransactionType();
        if (!array_key_exists($transactionType, $transactionTypeActions)) {
            $this->getModuleHelper()->throwWebapiException(
                sprintf(
                    'Transaction Type (%s) not supported yet',
                    $transactionType
                )
            );
        }
        return $transactionTypeActions[$transactionType];
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
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();
        $this->getLogger()->debug('PREAUTH transaction for order #' . $orderId);
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
                $this->getLogger()->debug('COLLECTION transaction for order #' . $orderId);
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            // New order
            $this->getLogger()->debug($this->getConfigTransactionType() . ' transaction for order #' . $orderId);
            return $this->processInitialTransaction($payment, $amount);
        }

        if ($errorMessage !== '') {
            $this->getLogger()->error($errorMessage);
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
            throw new \Magento\Framework\Exception\LocalizedException(__('Billing address is empty.'));
        }
        $transactionType = $this->getConfigTransactionType();
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
        $order             = $payment->getOrder();
        $orderId           = $order->getRealOrderId();
        $transactionType   = $this->getConfigTransactionType();
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
                    ->setTransactionAdditionalInfo(
                        \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                        $response
                    );
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
                            $errorMessage = __('The card is enrolled in a 3-D Secure scheme. ') .
                                __('This payment method does not support processing of cards enrolled in a ' .
                                    '3-D Secure scheme.');
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
            }
        } catch (\Exception $e) {
            $logInfo = $transactionType . ' transaction for order #' . $orderId .
                ' failed with message "' . $e->getMessage() . '"';
            $this->getLogger()->error($logInfo);
            $this->getCheckoutSession()->setPaymentsenseCheckoutErrorMessage($e->getMessage());
            $this->getModuleHelper()->throwWebapiException($e->getMessage());
        }
        return $this;
    }

    /**
     * Processes the 3-D Secure response from the ACS
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $postData
     * @return string
     */
    public function process3dsResponse($order, $postData)
    {
        $orderId = $order->getRealOrderId();
        $config  = $this->getConfigHelper();
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
                $this->getLogger()->debug('3-D Secure authentication for order #' . $orderId);
                $response            = $psgw->perform3dsAuthTxn($trxData);
                $status              = $response['StatusCode'];
                $isTransactionFailed = ($status !== TransactionResultCode::SUCCESS);
                $payment             = $order->getPayment();
                $payment
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending(false)
                    ->setIsTransactionClosed($isTransactionFailed)
                    ->setTransactionAdditionalInfo(
                        \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                        $response
                    );

                if ($isTransactionFailed) {
                    $message = '3-D Secure Authentication failed. Payment Gateway Message: ' . $response['Message'];
                    $this->getModuleHelper()->setOrderState($order, $status, $message);
                } else {
                    $message = '';
                    $this->getModuleHelper()->setOrderState($order, $status);
                }

                $response['OrderID']         = $orderId;
                $response['Amount']          = $order->getTotalDue() * 100;
                $response['TransactionType'] = $config->getTransactionType();

                $this->updatePayment($response);
            } catch (\Exception $e) {
                $logInfo = '3-D Secure Authentication for order #' . $orderId .
                    ' failed with message "' . $e->getMessage() . '"';
                $message = $logInfo;
                $this->getLogger()->error($logInfo);
            }
        }

        return $message;
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
