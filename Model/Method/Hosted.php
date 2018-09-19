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

use Paymentsense\Payments\Model\Psgw\GatewayEndpoints;
use Paymentsense\Payments\Model\Psgw\DataBuilder;
use Paymentsense\Payments\Model\Traits\BaseMethod;

/**
 * Hosted payment method model
 *
 * @package Paymentsense\Payments\Model\Method
 */
class Hosted extends \Magento\Payment\Model\Method\AbstractMethod
{
    use BaseMethod;

    const CODE = 'paymentsense_hosted';

    protected $_code = self::CODE;
    protected $_canOrder                    = true;
    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = true;
    protected $_canCancelInvoice            = true;
    protected $_canVoid                     = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canAuthorize                = true;
    protected $_isInitializeNeeded          = false;
    protected $_canUseCheckout              = true;
    protected $_canUseInternal              = false;

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
     * Gets the payment action on payment complete
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return \Magento\Payment\Model\Method\AbstractMethod::ACTION_ORDER;
    }

    /**
     * Determines method availability
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return parent::isAvailable($quote) && $this->getConfigHelper()->isMethodAvailable($this->getCode());
    }

    /**
     * Builds the redirect form action URL and the variables for the Hosted Payment Form
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return array
     */
    public function buildFormData($order)
    {
        $billingAddress = $order->getBillingAddress();
        $config = $this->getConfigHelper();
        $orderId = $order->getRealOrderId();
        $fields = [
            'Amount'                    => $order->getTotalDue() * 100,
            'CurrencyCode'              => DataBuilder::getCurrencyIsoCode($order->getOrderCurrencyCode()),
            'OrderID'                   => $orderId,
            'TransactionType'           => $config->getTransactionType(),
            'TransactionDateTime'       => date('Y-m-d H:i:s P'),
            'CallbackURL'               => $this->getModuleHelper()->getHostedFormCallbackUrl(),
            'OrderDescription'          => $order->getRealOrderId() . ': New order',
            'CustomerName'              => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'Address1'                  => $billingAddress->getStreetLine(1),
            'Address2'                  => $billingAddress->getStreetLine(2),
            'Address3'                  => $billingAddress->getStreetLine(3),
            'Address4'                  => $billingAddress->getStreetLine(4),
            'City'                      => $billingAddress->getCity(),
            'State'                     => $billingAddress->getRegionCode(),
            'PostCode'                  => $billingAddress->getPostcode(),
            'CountryCode'               => DataBuilder::getCountryIsoCode($billingAddress-> getCountryId()),
            'EmailAddress'              => $order->getCustomerEmail(),
            'PhoneNumber'               => $billingAddress->getTelephone(),
            'EmailAddressEditable'      => DataBuilder::getBool($config->getEmailAddressEditable()),
            'PhoneNumberEditable'       => DataBuilder::getBool($config->getPhoneNumberEditable()),
            'CV2Mandatory'              => 'true',
            'Address1Mandatory'         => DataBuilder::getBool($config->getAddress1Mandatory()),
            'CityMandatory'             => DataBuilder::getBool($config->getCityMandatory()),
            'PostCodeMandatory'         => DataBuilder::getBool($config->getPostcodeMandatory()),
            'StateMandatory'            => DataBuilder::getBool($config->getStateMandatory()),
            'CountryMandatory'          => DataBuilder::getBool($config->getCountryMandatory()),
            'ResultDeliveryMethod'      => 'POST',
            'ServerResultURL'           => '',
            'PaymentFormDisplaysResult' => 'false'
        ];

        $fields = array_map(function ($value) {
            return $value === null ? '' : $value;
        }, $fields);

        $data  = 'MerchantID=' . $config->getMerchantId();
        $data .= '&Password=' . $config->getPassword();

        foreach ($fields as $key => $value) {
            $data .= '&' . $key . '=' . $value;
        };

        $additionalFields = [
            'HashDigest' => $this->calculateHashDigest($data, $config->getHashMethod(), $config->getPresharedKey()),
            'MerchantID' => $config->getMerchantId(),
        ];

        $fields = array_merge($additionalFields, $fields);

        $this->getLogger()->debug($this->getConfigTransactionType() . ' transaction for order #' . $orderId);

        return [
            'url'      => GatewayEndpoints::getPaymentFormUrl(),
            'elements' => $fields
        ];
    }

    /**
     * Calculates the hash digest.
     * Supported hash methods: MD5, SHA1, HMACMD5, HMACSHA1
     *
     * @param string $data Data to be hashed.
     * @param string $hashMethod Hash method.
     * @param string $key Secret key to use for generating the hash.
     * @return string
     */
    public function calculateHashDigest($data, $hashMethod, $key)
    {
        $result     = '';
        $includeKey = in_array($hashMethod, [ 'MD5', 'SHA1' ], true);
        if ($includeKey) {
            $data = 'PreSharedKey=' . $key . '&' . $data;
        }
        switch ($hashMethod) {
            case 'MD5':
                // @codingStandardsIgnoreLine
                $result = md5($data);
                break;
            case 'SHA1':
                $result = sha1($data);
                break;
            case 'HMACMD5':
                $result = hash_hmac('md5', $data, $key);
                break;
            case 'HMACSHA1':
                $result = hash_hmac('sha1', $data, $key);
                break;
        }
        return $result;
    }

    /**
     * Checks whether the hash digest received from the payment gateway is valid
     *
     * @param array $postData POST Data
     * @return bool
     */
    public function isHashDigestValid($postData)
    {
        $config = $this->getConfigHelper();
        $result = false;
        $data   = $this->buildPostString($postData);
        if ($data) {
            $hashDigestReceived   = $postData['HashDigest'];
            $hashDigestCalculated = $this->calculateHashDigest(
                $data,
                $config->getHashMethod(),
                $config->getPresharedKey()
            );
            $result = strToUpper($hashDigestReceived) === strToUpper($hashDigestCalculated);
        }
        return $result;
    }

    /**
     * Builds a string containing the expected fields from the POST request received from the payment gateway
     *
     * @param array $postData POST Data
     * @return bool
     */
    public function buildPostString($postData)
    {
        $config = $this->getConfigHelper();
        $result = 'MerchantID=' . $config->getMerchantId() . '&Password=' . $config->getPassword();
        $fields = [
            'StatusCode',
            'Message',
            'PreviousStatusCode',
            'PreviousMessage',
            'CrossReference',
            'Amount',
            'CurrencyCode',
            'OrderID',
            'TransactionType',
            'TransactionDateTime',
            'OrderDescription',
            'CustomerName',
            'Address1',
            'Address2',
            'Address3',
            'Address4',
            'City',
            'State',
            'PostCode',
            'CountryCode',
            'EmailAddress',
            'PhoneNumber',
        ];
        foreach ($fields as $field) {
            $result .= '&' . $field . '=' . $postData[$field];
        }
        return $result;
    }
}
