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

namespace Paymentsense\Payments\Helper;

use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;

/**
 * Helper common for all payment methods
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $_paymentData;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $_configFactory;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Paymentsense\Payments\Model\ConfigFactory $configFactory
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Paymentsense\Payments\Model\ConfigFactory $configFactory,
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    ) {
        $this->_objectManager = $objectManager;
        $this->_paymentData = $paymentData;
        $this->_storeManager = $storeManager;
        $this->_configFactory = $configFactory;
        $this->_localeResolver = $localeResolver;
        $this->_scopeConfig = $context->getScopeConfig();
        parent::__construct($context);
    }

    /**
     * Gets an instance of the Magento Object Manager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    public function getObjectManager()
    {
        return $this->_objectManager;
    }

    /**
     * Gets an instance of the Magento Store Manager
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return $this->_storeManager;
    }

    /**
     * Gets an instance of the Config Factory Class
     *
     * @return \Paymentsense\Payments\Model\ConfigFactory
     */
    protected function getConfigFactory()
    {
        return $this->_configFactory;
    }

    /**
     * Gets an instance of the Magento UrlBuilder
     *
     * @return \Magento\Framework\UrlInterface
     */
    public function getUrlBuilder()
    {
        return $this->_urlBuilder;
    }

    /**
     * Gets an instance of the Magento Scope Config
     *
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected function getScopeConfig()
    {
        return $this->_scopeConfig;
    }

    /**
     * Gets an instance of the Magento Core Locale Object
     *
     * @return \Magento\Framework\Locale\ResolverInterface
     */
    protected function getLocaleResolver()
    {
        return $this->_localeResolver;
    }

    /**
     * Builds URL for store
     *
     * @param string $moduleCode
     * @param string $controller
     * @param string|null $queryParams
     * @param bool|null $secure
     * @param int|null $storeId
     * @return string
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getUrl($moduleCode, $controller, $queryParams = null, $secure = null, $storeId = null)
    {
        list($route, $module) = explode('_', $moduleCode);
        $path = sprintf('%s/%s/%s', $route, $module, $controller);
        $store = $this->getStoreManager()->getStore($storeId);
        $params = [
            '_store' => $store,
            '_secure' => ($secure === null) ? $this->isStoreSecure($storeId) : $secure
        ];
        if (isset($queryParams) && is_array($queryParams)) {
            $params = array_merge($params, $queryParams);
        }
        return $this->getUrlBuilder()->getUrl($path, $params);
    }

    /**
     * Gets the return URL where the customer will be redirected from the Hosted Payment Form
     *
     * @return string|false
     */
    public function getHpfCallbackUrl()
    {
        try {
            $store = $this->getStoreManager()->getStore();
            $params = [
                '_store' => $store,
                '_secure' => $this->isStoreSecure()
            ];
            $result = $this->getUrlBuilder()->getUrl('paymentsense/hosted/index', $params);
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets an instance of a Method Object
     *
     * @param string $methodCode
     * @return \Paymentsense\Payments\Model\Config
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMethodConfig($methodCode)
    {
        $parameters = [
            'params' => [
                $methodCode,
                $this->getStoreManager()->getStore()->getId()
            ]
        ];
        $config = $this->getConfigFactory()->create($parameters);
        $config->setMethodCode($methodCode);
        return $config;
    }

    /**
     * Creates Webapi Exception
     *
     * @param \Magento\Framework\Phrase|string $phrase
     * @return \Magento\Framework\Webapi\Exception
     */
    public function createWebapiException($phrase)
    {
        if (!($phrase instanceof \Magento\Framework\Phrase)) {
            $phrase = new \Magento\Framework\Phrase($phrase);
        }
        return new \Magento\Framework\Webapi\Exception($phrase);
    }

    /**
     * Throws Webapi Exception
     *
     * @param \Magento\Framework\Phrase|string $phrase
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function throwWebapiException($phrase)
    {
        // @codingStandardsIgnoreLine
        throw $this->createWebapiException($phrase);
    }

    /**
     * Checks whether the store is secure
     *
     * @param $storeId
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isStoreSecure($storeId = null)
    {
        $store = $this->getStoreManager()->getStore($storeId);
        return $store->isCurrentlySecure();
    }

    /**
     * Sets the AdditionalInfo to the payment transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array|string $info
     */
    public function setPaymentTransactionAdditionalInfo($payment, $info)
    {
        $payment->setTransactionAdditionalInfo(Transaction::RAW_DETAILS, $info);
    }

    /**
     * Gets the AdditionalInfo to the payment transaction
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction $transaction
     * @param string $element Info element
     * @return array|string|null
     */
    public function getPaymentTransactionAdditionalInfo($transaction, $element = null)
    {
        $result = null;
        $info = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
        if (is_array($info)) {
            if (isset($element)) {
                if (array_key_exists($element, $info)) {
                    $result = $info[$element];
                }
            } else {
                $result = $info;
            }
        }
        return $result;
    }

    /**
     * Updates Order Status and State
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $state
     */
    public function setOrderStatusByState($order, $state)
    {
        $order
            ->setState($state)
            ->setStatus($order->getConfig()->getStateDefaultStatus($state));
    }

    /**
     * Sets Order State based on the transaction result code returned by the payment gateway
     *
     * @param \Magento\Sales\Model\Order $order
     * @param string $status
     * @param string $message
     *
     * @throws \Exception
     */
    public function setOrderState($order, $status, $message = '')
    {
        switch ($status) {
            case TransactionResultCode::SUCCESS:
                $this->setOrderStatusByState($order, Order::STATE_PROCESSING);
                $order->save();
                break;

            case TransactionResultCode::INCOMPLETE:
            case TransactionResultCode::REFERRED:
                $this->setOrderStatusByState($order, Order::STATE_PENDING_PAYMENT);
                $order->save();
                break;

            case TransactionResultCode::DECLINED:
            case TransactionResultCode::FAILED:
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->cancel();
                }
                $order
                    ->registerCancellation($message)
                    ->setCustomerNoteNotify(true)
                    ->save();
                break;
            default:
                $order->save();
                break;
        }
    }

    /**
     * Gets payment transaction
     *
     * @param string $fieldValue
     * @param string $fieldName
     * @return null|\Magento\Sales\Model\Order\Payment\Transaction
     */
    public function getPaymentTransaction($fieldValue, $fieldName)
    {
        $transaction = null;
        if (!empty($fieldValue)) {
            $transactionObj = $this->getObjectManager()->create(Transaction::class);
            $transaction = $transactionObj->load($fieldValue, $fieldName);
            if (!$transaction->getId()) {
                $transaction = null;
            }
        }
        return $transaction;
    }

    /**
     * Searches for a transaction by transaction types
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array $transactionTypes
     * @return \Magento\Sales\Model\Order\Payment\Transaction
     */
    private function lookUpTransaction($payment, $transactionTypes)
    {
        $transaction = null;
        $lastTransactionId = $payment->getLastTransId();
        $transaction = $this->getPaymentTransaction($lastTransactionId, 'txn_id');
        while (isset($transaction)) {
            if (in_array($transaction->getTxnType(), $transactionTypes)) {
                break;
            }
            $transaction = $this->getPaymentTransaction($transaction->getParentId(), 'transaction_id');
        }
        return $transaction;
    }

    /**
     * Searches for an Authorisation transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return null|\Magento\Sales\Model\Order\Payment\Transaction
     */
    public function lookUpAuthorisationTransaction($payment)
    {
        return $this->lookUpTransaction($payment, [Transaction::TYPE_AUTH]);
    }

    /**
     * Searches for a Capture transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return null|\Magento\Sales\Model\Order\Payment\Transaction
     */
    public function lookUpCaptureTransaction($payment)
    {
        return $this->lookUpTransaction($payment, [Transaction::TYPE_CAPTURE]);
    }

    /**
     * Gets an array of all globally allowed currency codes
     *
     * @return array
     */
    private function getGloballyAllowedCurrencyCodes()
    {
        $allowedCurrencyCodes = $this->getScopeConfig()->getValue(
            \Magento\Directory\Model\Currency::XML_PATH_CURRENCY_ALLOW
        );
        return array_map(
            function ($item) {
                return trim($item);
            },
            explode(',', $allowedCurrencyCodes)
        );
    }

    /**
     * Builds Select Options for the Allowed Currencies in the admin panel
     *
     * @param array $availableCurrenciesOptions
     * @return array
     */
    public function getGloballyAllowedCurrenciesOptions($availableCurrenciesOptions)
    {
        $allowedCurrenciesOptions = [];
        $allowedGlobalCurrencyCodes = $this->getGloballyAllowedCurrencyCodes();
        foreach ($availableCurrenciesOptions as $availableCurrencyOptions) {
            if (in_array($availableCurrencyOptions['value'], $allowedGlobalCurrencyCodes)) {
                $allowedCurrenciesOptions[] = $availableCurrencyOptions;
            }
        }
        return $allowedCurrenciesOptions;
    }

    /**
     * Gets the filtered currencies
     *
     * @param array $allowedLocalCurrencies
     * @return array
     */
    private function getFilteredCurrencies($allowedLocalCurrencies)
    {
        $result = [];
        $allowedGlobalCurrencyCodes = $this->getGloballyAllowedCurrencyCodes();
        foreach ($allowedLocalCurrencies as $allowedLocalCurrency) {
            if (in_array($allowedLocalCurrency, $allowedGlobalCurrencyCodes)) {
                $result[] = $allowedLocalCurrency;
            }
        }
        return $result;
    }

    /**
     * Checks whether the payment method is available for a given currency
     *
     * @param string $methodCode
     * @param string $currencyCode
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isCurrencyAllowed($methodCode, $currencyCode)
    {
        $methodConfig = $this->getMethodConfig($methodCode);
        if ($methodConfig->areSpecificCurrenciesEnabled()) {
            $allowedMethodCurrencies = $this->getFilteredCurrencies(
                $methodConfig->getAllowedCurrencies()
            );
        } else {
            $allowedMethodCurrencies = $this->getGloballyAllowedCurrencyCodes();
        }
        return in_array($currencyCode, $allowedMethodCurrencies);
    }

    /**
     * Determines whether the format of the merchant ID matches the ABCDEF-1234567 format
     *
     * @param string $methodCode
     * @return bool
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isMerchantIdFormatValid($methodCode)
    {
        $methodConfig = $this->getMethodConfig($methodCode);
        return (bool)preg_match('/^[a-zA-Z]{6}-[0-9]{7}$/', $methodConfig->getMerchantId());
    }

    /**
     * Retrieves the hostname from an URL
     *
     * @param string $url URL
     * @return string
     */
    public function getHostname($url)
    {
        // @codingStandardsIgnoreLine
        $parts = parse_url($url);
        return isset($parts['host']) ? strtolower($parts['host']) : '';
    }

    /**
     * Builds a pair of a local and a remote timestamp
     *
     * @param \DateTime $remoteDateTime
     * @return array
     */
    public function buildDateTimePair($remoteDateTime)
    {
        $localDateTime = date_create();
        return [$localDateTime, $remoteDateTime];
    }

    /**
     * Calculates an order/display amount due
     *
     * @param \Magento\Sales\Model\Order $order
     * @param float $amount
     * @return float|false
     *
     */
    public function calculateOrderAmountDue($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalDue()) {
            $orderAmount = $order->getTotalDue();
            $baseAmount = $order->getBaseTotalDue();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($baseAmount > 0)) {
                $rate = $orderAmount / $baseAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }

    /**
     * Calculates an order/display amount paid
     *
     * @param \Magento\Sales\Model\Order $order
     * @param float $amount
     * @return float|false
     *
     */
    public function calculateOrderAmountPaid($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalPaid()) {
            $orderAmount = $order->getTotalPaid();
            $baseAmount = $order->getBaseTotalPaid();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($baseAmount > 0)) {
                $rate = $orderAmount / $baseAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }

    /**
     * Calculates a base amount
     *
     * @param \Magento\Sales\Model\Order $order
     * @param float $amount
     * @return float|false
     *
     */
    public function calculateBaseAmount($order, $amount)
    {
        $result = false;
        if ($order->getBaseTotalDue()) {
            $orderAmount = $order->getTotalDue();
            $baseAmount = $order->getBaseTotalDue();
            if (is_numeric($orderAmount) && is_numeric($baseAmount) && ($orderAmount > 0)) {
                $rate = $baseAmount / $orderAmount;
                $result = round($amount * $rate, 2);
            }
        }
        return $result;
    }

    /**
     * Converts boolean to string
     *
     * @param boolean $value
     * @return string
     */
    public function getBool($value)
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Converts HTML entities to their corresponding characters and replaces the chars that are not supported
     * by the gateway with supported ones
     *
     * @param string $data
     * @return string
     */
    public function filterUnsupportedChars($data)
    {
        $data = $this->htmlDecode($data);
        return str_replace(
            ['"', '\'', '\\', '<', '>', '[', ']', '#', '&'],
            ['`', '`',  '/',  '(', ')', '(', ')', '',  ''],
            $data
        );
    }

    /**
     * Converts HTML entities to their corresponding characters
     *
     * @param string $data
     * @return string
     */
    public function htmlDecode($data)
    {
        return str_replace(
            ['&quot;', '&apos;', '&#039;', '&amp;', '&num;', '&#35;'],
            ['"',      '\'',     '\'',     '&',     '#',     '#'],
            $data
        );
    }

    /**
     * Applies the gateway's restrictions on the length of selected alphanumeric fields sent to the HPF
     *
     * @param array $data
     * @return array
     */
    public function applyLengthRestrictions($data)
    {
        $result = [];
        $maxLengths = [
            'OrderDescription' => 256,
            'CustomerName'     => 100,
            'Address1'         => 100,
            'Address2'         => 50,
            'Address3'         => 50,
            'Address4'         => 50,
            'City'             => 50,
            'State'            => 50,
            'PostCode'         => 50,
            'EmailAddress'     => 100,
            'PhoneNumber'      => 30
        ];
        foreach ($data as $key => $value) {
            $result[$key] = array_key_exists($key, $maxLengths)
                ? substr($value, 0, $maxLengths[$key])
                : $value;
        }
        return $result;
    }
}
