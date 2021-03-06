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

namespace Paymentsense\Payments\Model;

use Magento\Store\Model\ScopeInterface;

/**
 * Implementation of Payment Model Method ConfigInterface
 * Used for retrieving configuration data
 */
class Config implements \Magento\Payment\Model\Method\ConfigInterface
{
    const MODULE_NAME = 'Paymentsense Module for Magento 2 Open Source';

    /**
     * Current payment method code
     *
     * @var string
     */
    protected $_methodCode;
    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;
    /**
     * Current store id
     *
     * @var int
     */
    protected $_storeId;
    /**
     * @var string
     */
    protected $pathPattern;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Module\ModuleListInterface
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Module\ModuleListInterface $moduleListInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->moduleList   = $moduleListInterface;
    }

    /**
     * Gets an instance of the Magento ScopeConfig
     *
     * @return \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected function getScopeConfig()
    {
        return $this->_scopeConfig;
    }

    /**
     * Gets payment method code
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->_methodCode;
    }

    /**
     * Gets module name
     *
     * @return string
     */
    public function getModuleName()
    {
        return self::MODULE_NAME;
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    public function getModuleInstalledVersion()
    {
        return $this->moduleList->getOne('Paymentsense_Payments')['setup_version'];
    }

    /**
     * Gets module HTTP user agent
     * Used for performing cURL requests
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->getModuleName() . ' v.' . $this->getModuleInstalledVersion();
    }

    /**
     * Sets store ID
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->_storeId = (int) $storeId;
        return $this;
    }

    /**
     * Gets payment configuration value
     *
     * @param string $key
     * @param null $storeId
     * @return null|string
     */
    public function getValue($key, $storeId = null)
    {
        $value = '';
        $underscored = strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", $key));
        $path = $this->getConfigPath($underscored);
        if ($path !== null) {
            $value = $this->getScopeConfig()->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $storeId ?: $this->_storeId
            );
        }
        return $value;
    }

    /**
     * Sets payment method code
     *
     * @param string $methodCode
     * @return void
     */
    public function setMethodCode($methodCode)
    {
        $this->_methodCode = $methodCode;
    }

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     * @return void
     */
    public function setPathPattern($pathPattern)
    {
        $this->pathPattern = $pathPattern;
    }

    /**
     * Gets config path by field name
     *
     * @param string $fieldName
     * @return string
     */
    private function getConfigPath($fieldName)
    {
        if ($this->pathPattern) {
            return sprintf($this->pathPattern, $this->_methodCode, $fieldName);
        }

        return "payment/{$this->_methodCode}/{$fieldName}";
    }

    /**
     * Checks whether the payment gateway credentials are configured
     *
     * @param string $methodCode
     * @return bool
     */
    public function isMethodConfigured($methodCode)
    {
        return !empty($this->getMerchantId()) &&
            !empty($this->getPassword()) &&
            !empty($this->getTransactionType()) &&
            ($methodCode != Method\Hosted::CODE || !empty($this->getPresharedKey()));
    }

    /**
     * Checks whether the payment method is available for checkout
     *
     * @param string $methodCode
     * @return bool
     */
    public function isMethodAvailable($methodCode)
    {
        return $this->isMethodActive($methodCode) && $this->isMethodConfigured($methodCode);
    }

    /**
     * Checks whether the payment method is active
     *
     * @param string $methodCode
     * @return bool
     */
    public function isMethodActive($methodCode)
    {
        return $this->isChecked($methodCode, 'active');
    }

    /**
     * Checks whether configuration checkbox is checked
     *
     * @param string $methodCode
     * @param string $name
     * @return bool
     */
    public function isChecked($methodCode, $name)
    {
        $methodCode = $methodCode ?: $this->_methodCode;
        return $this->getScopeConfig()->isSetFlag(
            "payment/{$methodCode}/{$name}",
            ScopeInterface::SCOPE_STORE,
            $this->_storeId
        );
    }

    /**
     * Gets payment gateway Merchant ID
     *
     * @return null|string
     */
    public function getMerchantId()
    {
        return $this->getValue('merchant_id');
    }

    /**
     * Gets payment gateway Password
     *
     * @return null|string
     */
    public function getPassword()
    {
        return $this->getValue('password');
    }

    /**
     * Gets payment gateway Pre-shared Key
     *
     * @return null|string
     */
    public function getPresharedKey()
    {
        return $this->getValue('preshared_key');
    }

    /**
     * Gets payment gateway Hash Method
     *
     * @return null|string
     */
    public function getHashMethod()
    {
        return $this->getValue('hash_method');
    }

    /**
     * Gets Checkout Page Title
     *
     * @return null|string
     */
    public function getCheckoutTitle()
    {
        return $this->getValue('title');
    }

    /**
     * Gets Transaction Type
     *
     * @return string
     */
    public function getTransactionType()
    {
        return $this->getValue('transaction_type');
    }

    /**
     * Gets Result Delivery Method
     *
     * @return string
     */
    public function getResultDeliveryMethod()
    {
        return $this->getValue('result_delivery_method');
    }

    /**
     * Gets Email Address Editable
     *
     * @return string
     */
    public function getEmailAddressEditable()
    {
        return $this->getValue('email_address_editable');
    }

    /**
     * Gets Phone Number Editable
     *
     * @return string
     */
    public function getPhoneNumberEditable()
    {
        return $this->getValue('phone_number_editable');
    }

    /**
     * Gets Address1 Mandatory
     *
     * @return string
     */
    public function getAddress1Mandatory()
    {
        return $this->getValue('address1_mandatory');
    }

    /**
     * Gets City Mandatory
     *
     * @return string
     */
    public function getCityMandatory()
    {
        return $this->getValue('city_mandatory');
    }

    /**
     * Gets State Mandatory
     *
     * @return string
     */
    public function getStateMandatory()
    {
        return $this->getValue('state_mandatory');
    }

    /**
     * Gets Postcode Mandatory
     *
     * @return string
     */
    public function getPostcodeMandatory()
    {
        return $this->getValue('postcode_mandatory');
    }

    /**
     * Gets Country Mandatory
     *
     * @return string
     */
    public function getCountryMandatory()
    {
        return $this->getValue('country_mandatory');
    }

    /**
     * Gets New Order Status
     *
     * @return null|string
     */
    public function getOrderStatusNew()
    {
        return $this->getValue('order_status');
    }

    /**
     * Gets Payment Currency
     *
     * @return string
     */
    public function getPaymentCurrency()
    {
        return $this->getValue('payment_currency');
    }

    /**
     * Determines whether the payment currency is the base currency
     *
     * @return bool
     */
    public function isBaseCurrency()
    {
        return 'BASE' === $this->getPaymentCurrency();
    }

    /**
     * Determines whether specific currencies are enabled
     *
     * @return bool
     */
    public function areSpecificCurrenciesEnabled()
    {
        return $this->isChecked($this->_methodCode, 'allow_specific_currency');
    }

    /**
     * Gets Allowed Currencies
     *
     * @return array
     */
    public function getAllowedCurrencies()
    {
        return array_map(
            'trim',
            explode(
                ',',
                $this->getValue('specific_currencies')
            )
        );
    }

    /**
     * Gets Log Level
     *
     * @return int
     */
    public function getLogLevel()
    {
        return (int) $this->getValue('log_level');
    }

    /**
     * Gets Port 4430 is NOT open on my server
     *
     * @return string
     */
    public function getPort4430NotOpen()
    {
        return $this->getValue('port_4430_not_open');
    }
}
