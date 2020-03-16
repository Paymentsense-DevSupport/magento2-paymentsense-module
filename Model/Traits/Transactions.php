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

namespace Paymentsense\Payments\Model\Traits;

use DateTime;
use Paymentsense\Payments\Model\Psgw\Psgw;
use Paymentsense\Payments\Model\Psgw\TransactionType;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Paymentsense\Payments\Model\Psgw\HpfResponses;
use Magento\Sales\Model\Order;

/**
 * Trait for processing transactions
 */
trait Transactions
{
    /**
     * System time threshold.
     * Specifies the maximal difference between the local time and the gateway time
     * in seconds where the system time is considered Ok.
     */
    protected $_systemTimeThreshold = 300;

    /**
     * @var array $dateTimePairs Pairs of local and remote timestamps
     * Used for the determination of the system time status
     */
    protected $_dateTimePairs = [];

    /**
     * @var \Paymentsense\Payments\Model\Config
     */
    protected $_configHelper;
    /**
     * @var \Paymentsense\Payments\Helper\Data
     */
    protected $_moduleHelper;
    /**
     * @var \Paymentsense\Payments\Helper\IsoCodes
     */
    protected $_isoCodes;
    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $_actionContext;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     * @var \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
     */
    protected $_transactionManager;

    /**
     * Gets an instance of the Config Helper Object
     *
     * @return \Paymentsense\Payments\Model\Config
     */
    public function getConfigHelper()
    {
        return $this->_configHelper;
    }

    /**
     * Gets an instance of the Module Helper Object
     *
     * @return \Paymentsense\Payments\Helper\Data
     */
    public function getModuleHelper()
    {
        return $this->_moduleHelper;
    }

    /**
     * Gets an instance of the ISO Codes Helper Object
     *
     * @return \Paymentsense\Payments\Helper\IsoCodes
     */
    public function getIsoCodesHelper()
    {
        return $this->_isoCodes;
    }

    /**
     * Gets an instance of the Magento Action Context
     *
     * @return \Magento\Framework\App\Action\Context
     */
    protected function getActionContext()
    {
        return $this->_actionContext;
    }

    /**
     * Gets an instance of the Magento Core Message Manager
     *
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->getActionContext()->getMessageManager();
    }

    /**
     * Gets an instance of the Magento Core Store Manager Object
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        return$this->_storeManager;
    }

    /**
     * Gets an instance of an URL
     *
     * @return \Magento\Framework\UrlInterface
     */
    protected function getUrlBuilder()
    {
        return $this->_urlBuilder;
    }

    /**
     * Gets an instance of the Magento Core Checkout Session
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    /**
     * Gets an instance of the Magento Transaction Manager
     *
     * @return \Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface
     */
    protected function getTransactionManager()
    {
        return $this->_transactionManager;
    }

    /**
     * Performs a Reference Transaction (COLLECTION, REFUND, VOID)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param array $request Transaction request data
     * @return array
     */
    protected function processReferenceTransaction(\Magento\Payment\Model\InfoInterface $payment, $request)
    {
        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);
        $response          = $psgw->performCrossRefTxn($request);

        $this->getLogger()->info(
            'Reference transaction ' . $response['CrossReference'] .
            ' has been performed with status code "' . $response['StatusCode'] . '".'
        );

        if ($response['StatusCode'] !== false) {
            $payment
                ->setTransactionId($response['CrossReference'])
                ->setParentTransactionId($request['CrossReference'])
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionPending(false)
                ->setIsTransactionClosed(true);
            $this->getModuleHelper()->setPaymentTransactionAdditionalInfo(
                $payment,
                array_merge(
                    [
                        'Amount'       => $request['Amount'],
                        'CurrencyCode' => $request['CurrencyCode']
                    ],
                    $response
                )
            );
            $payment->save();
        }
        return $response;
    }

    /**
     * Performs COLLECTION
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @param \Magento\Sales\Model\Order\Payment\Transaction $authTransaction
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function performCollection(\Magento\Payment\Model\InfoInterface $payment, $amount, $authTransaction)
    {
        $config  = $this->getConfigHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Collection';

        if (!$this->isTransactionInBaseCurrency($authTransaction)) {
            $amount = $this->getModuleHelper()->calculateOrderAmountDue($order, $amount);
        }

        $request = [
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => $this->getIsoCodesHelper()->getCurrencyIsoCode($this->getPaymentCurrencyCode($order)),
            'TransactionType'  => TransactionType::COLLECTION,
            'CrossReference'   => $authTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        ];

        $response = $this->processReferenceTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('COLLECTION transaction failed. ') .
                    (($response['StatusCode'] !== false) ? __('Payment gateway message: ') : '') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Performs REFUND
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @param \Magento\Sales\Model\Order\Payment\Transaction $captureTransaction
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function performRefund(\Magento\Payment\Model\InfoInterface $payment, $amount, $captureTransaction)
    {
        $config  = $this->getConfigHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Refund';

        if (!$this->isTransactionInBaseCurrency($captureTransaction)) {
            $amount = $this->getModuleHelper()->calculateOrderAmountPaid($order, $amount);
        }

        $request = [
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => $amount * 100,
            'CurrencyCode'     => $this->getIsoCodesHelper()->getCurrencyIsoCode($this->getPaymentCurrencyCode($order)),
            'TransactionType'  => TransactionType::REFUND,
            'CrossReference'   => $captureTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        ];

        $response = $this->processReferenceTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('REFUND transaction failed. ') .
                    (($response['StatusCode'] !== false) ? __('Payment gateway message: ') : '') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Performs VOID
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Sales\Model\Order\Payment\Transaction $referenceTransaction
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function performVoid(\Magento\Payment\Model\InfoInterface $payment, $referenceTransaction)
    {
        $config  = $this->getConfigHelper();
        $order   = $payment->getOrder();
        $orderId = $order->getRealOrderId();
        $reason  = 'Void';
        $request = [
            'MerchantID'       => $config->getMerchantId(),
            'Password'         => $config->getPassword(),
            'Amount'           => '',
            'CurrencyCode'     => '',
            'TransactionType'  => TransactionType::VOID,
            'CrossReference'   => $referenceTransaction->getTxnId(),
            'OrderID'          => $orderId,
            'OrderDescription' => $orderId . ': ' . $reason,
        ];

        $response = $this->processReferenceTransaction($payment, $request);

        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $this->getMessageManager()->addSuccessMessage($response['Message']);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase(
                    __('VOID transaction failed. ') .
                    (($response['StatusCode'] !== false) ? __('Payment gateway message: ') : '') .
                    $response['Message']
                )
            );
        }

        return $this;
    }

    /**
     * Refund handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();
        $this->getLogger()->info(
            sprintf('Preparing REFUND transaction for order #%s', $orderId)
        );
        $captureTransaction = $this->getModuleHelper()->lookUpCaptureTransaction($payment);
        if (isset($captureTransaction)) {
            try {
                $this->performRefund($payment, $amount, $captureTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'REFUND transaction for order #' . $orderId .
                ' cannot be finished (No Capture Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Capture handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();
        $this->getLogger()->info(
            sprintf('Preparing COLLECTION transaction for order #%s', $orderId)
        );

        $authTransaction = $this->getModuleHelper()->lookUpAuthorisationTransaction($payment);
        if (isset($authTransaction)) {
            try {
                $this->performCollection($payment, $amount, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'COLLECTION transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Void handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        $errorMessage = '';
        $order        = $payment->getOrder();
        $orderId      = $order->getIncrementId();

        $this->getLogger()->info(
            sprintf('Preparing VOID transaction for order #%s', $orderId)
        );

        $authTransaction = $this->getModuleHelper()->lookUpAuthorisationTransaction($payment);
        if (isset($authTransaction)) {
            try {
                $this->performVoid($payment, $authTransaction);
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
            }
        } else {
            $errorMessage = 'VOID transaction for order #' . $orderId .
                ' cannot be finished (No Authorize Transaction exists)';
        }

        if ($errorMessage !== '') {
            $this->getLogger()->warning($errorMessage);
            $this->getModuleHelper()->throwWebapiException($errorMessage);
        }

        return $this;
    }

    /**
     * Cancel handler
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     *
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Updates payment info and registers Card Details Transactions
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $response Transaction response data received from the gateway
     * @param array $request Transaction request data sent to the gateway
     * @param bool $registerNotification Determines whether to register a notification
     *
     * @throws \Exception
     */
    public function updatePayment($order, $response, $request, $registerNotification)
    {
        $config            = $this->getConfigHelper();
        $transactionID     = $response['CrossReference'];
        $payment           = $order->getPayment();
        $lastTransactionId = $payment->getLastTransId();
        $payment->setMethod($this->getCode());
        $payment->setLastTransId($transactionID);
        $payment->setTransactionId($transactionID);
        $payment->setParentTransactionId($lastTransactionId);
        $payment->setShouldCloseParentTransaction(true);
        $payment->setIsTransactionPending($response['StatusCode'] !== TransactionResultCode::SUCCESS);
        $payment->setIsTransactionClosed($config->getTransactionType() === TransactionType::SALE);
        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            $amount = isset($response['Amount']) ? $response['Amount'] : $request['Amount'];
            $currencyCode = isset($response['CurrencyCode']) ? $response['CurrencyCode'] : $request['CurrencyCode'];
            $this->getModuleHelper()->setPaymentTransactionAdditionalInfo(
                $payment,
                array_merge(
                    [
                        'Amount'       => $amount,
                        'CurrencyCode' => $currencyCode
                    ],
                    $response
                )
            );
            $this->registerTransaction($payment, $amount / 100, $registerNotification);
        }
        $order->save();
    }

    /**
     * Registers a payment transaction
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount Transaction amount
     * @param bool $registerNotification Determines whether to register a notification
     */
    public function registerTransaction($payment, $amount, $registerNotification)
    {
        $config = $this->getConfigHelper();
        $order = $payment->getOrder();
        $transactionID = $payment->getLastTransId();
        $transactionType = $config->getTransactionType();
        $commentMessage = sprintf(
            'A %s transaction in the %s currency of the amount of %.2f %s is performed.',
            $transactionType,
            $config->getPaymentCurrency(),
            $amount,
            $this->getPaymentCurrencyCode($order)
        );
        $this->getLogger()->info($commentMessage);
        $payment->addTransactionCommentsToOrder($transactionID, $commentMessage);
        if (!$config->isBaseCurrency()) {
            $amount = $this->getModuleHelper()->calculateBaseAmount($order, $amount);
        }
        if ($registerNotification) {
            if ($transactionType === TransactionType::SALE) {
                $payment->registerCaptureNotification($amount);
            } else {
                $payment->registerAuthorizationNotification($amount);
            }
        }
    }

    /**
     * Performs GetGatewayEntryPoints transaction
     *
     * @return array
     */
    public function performGetGatewayEntryPointsTxn()
    {
        $config            = $this->getConfigHelper();
        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);
        $request           = [
            'MerchantID' => $config->getMerchantId(),
            'Password'   => $config->getPassword(),
        ];

        $psgw->setTrxMaxAttempts(1);
        $result = $psgw->performGetGatewayEntryPointsTxn($request);

        foreach ($result['ResponseHeaders'] as $url => $header) {
            if (array_key_exists('Date', $header)) {
                $this->addDateTimePair($url, $header['Date']);
            }
        }

        return $result;
    }

    /**
     * Checks whether the plugin can connect to the gateway by performing GetGatewayEntryPoints transaction
     *
     * @return bool
     */
    public function canConnect()
    {
        $response = $this->performGetGatewayEntryPointsTxn();
        return false !== $response['StatusCode'];
    }

    /**
     * Configures the availability of the cross reference transactions (COLLECTION, REFUND, VOID)
     * based on the configuration setting "Port 4430 is NOT open on my server"
     */
    public function configureCrossRefTxnAvailability()
    {
        $port4430IsNotOpen = (bool) $this->getConfigHelper()->getPort4430NotOpen();
        $this->_canCapture = $this->_canRefund = $this->_canVoid = ! $port4430IsNotOpen;
    }

    /**
     * Determines whether the payment transaction is in the base currency
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction $transaction Payment transaction
     * @return bool
     */
    public function isTransactionInBaseCurrency($transaction)
    {
        $trxCurrencyCode = $this->getModuleHelper()->getPaymentTransactionAdditionalInfo(
            $transaction,
            'CurrencyCode'
        );
        $baseCurrencyCode = $this->getIsoCodesHelper()->getCurrencyIsoCode(
            $transaction->getOrder()->getBaseCurrencyCode()
        );
        return $trxCurrencyCode == $baseCurrencyCode;
    }

    /**
     * Checks whether the gateway settings are valid by performing a request to the Hosted Payment Form
     *
     * @return string
     */
    public function checkGatewaySettings()
    {
        $result          = HpfResponses::HPF_RESP_NO_RESPONSE;
        $responseHeaders = null;
        try {
            $objectManager     = $this->getModuleHelper()->getObjectManager();
            $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
            $psgw              = new Psgw($zendClientFactory);
            $fields            = $this->buildHpfFields();
            $postData          = http_build_query($fields['elements']);
            $headers           = [
                'User-Agent: ' . $this->getConfigHelper()->getUserAgent(),
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-UK,en;q=0.5',
                'Accept-Encoding: identity',
                'Connection: close',
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($postData)
            ];
            $data              = [
                'url'     => $psgw->getPaymentFormUrl(),
                'method'  => 'POST',
                'headers' => $headers,
                'xml'     => $postData
            ];

            $response        = $psgw->executeHttpRequest($data);
            $responseHeaders = $response->getHeaders();

            if ($responseHeaders && array_key_exists('Date', $responseHeaders)) {
                $this->addDateTimePair($psgw->getPaymentFormUrl(), $responseHeaders['Date']);
            }

            $responseBody    = $response->getBody();
            if ($responseBody) {
                $hpf_err_msg = $this->getHpfErrorMessage($response);
                if (is_string($hpf_err_msg)) {
                    switch (true) {
                        case $this->contains($hpf_err_msg, HpfResponses::HPF_RESP_HASH_INVALID):
                            $result = HpfResponses::HPF_RESP_HASH_INVALID;
                            break;
                        case $this->contains($hpf_err_msg, HpfResponses::HPF_RESP_MID_MISSING):
                            $result = HpfResponses::HPF_RESP_MID_MISSING;
                            break;
                        case $this->contains($hpf_err_msg, HpfResponses::HPF_RESP_MID_NOT_EXISTS):
                            $result = HpfResponses::HPF_RESP_MID_NOT_EXISTS;
                            break;
                        default:
                            $result = HpfResponses::HPF_RESP_NO_RESPONSE;
                    }
                } else {
                    $result = HpfResponses::HPF_RESP_OK;
                }
            }
        } catch (\Exception $e) {
            $this->getLogger()->error(
                'An error occurred while checking gateway settings through an HPF request: ' . $e->getMessage()
            );
            $result = HpfResponses::HPF_RESP_NO_RESPONSE;
        }

        return $result;
    }

    /**
     * Builds the fields for the Hosted Payment Form as an associative array
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return array An associative array containing the Required Input Variables for the API of the Hosted Payment Form
     *
     * @throws \Exception
     */
    public function buildHpfFields($order = null)
    {
        $config            = $this->getConfigHelper();
        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);
        $fields            = $order ? $this->buildPaymentFields($order) : $this->buildSamplePaymentFields();

        $fields = array_map(
            function ($value) {
                return $value === null ? '' : $this->getModuleHelper()->filterUnsupportedChars($value);
            },
            $fields
        );

        $fields = $this->getModuleHelper()->applyLengthRestrictions($fields);

        $data  = 'MerchantID=' . $config->getMerchantId();
        $data .= '&Password=' . $config->getPassword();

        foreach ($fields as $key => $value) {
            $data .= '&' . $key . '=' . $value;
        }

        $gatewayHashMethod = ($this instanceof \Paymentsense\Payments\Model\Method\Hosted)
            ? $config->getHashMethod()
            : 'SHA1';

        $additionalFields = [
            'HashDigest' => $this->calculateHashDigest($data, $gatewayHashMethod, $config->getPresharedKey()),
            'MerchantID' => $config->getMerchantId(),
        ];

        $fields = array_merge($additionalFields, $fields);

        if ($order) {
            $orderId = $order->getRealOrderId();
            $this->getLogger()->info(
                'Preparing Hosted Payment Form redirect with ' . $config->getTransactionType() .
                ' transaction for order #' . $orderId
            );

            $this->getModuleHelper()->setOrderStatusByState($order, Order::STATE_PENDING_PAYMENT);
            $order->save();
        }

        return [
            'url'      => $psgw->getPaymentFormUrl(),
            'elements' => $fields
        ];
    }

    /**
     * Builds the redirect form action URL and the variables for the Hosted Payment Form
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return array
     */
    public function buildPaymentFields($order)
    {
        $moduleHelper = $this->getModuleHelper();
        $config = $this->getConfigHelper();
        $isoHelper = $this->getIsoCodesHelper();
        $orderId = $order->getRealOrderId();
        $billingAddress = $order->getBillingAddress();
        return [
            'Amount'                    => $this->getPaymentTotalDue($order) * 100,
            'CurrencyCode'              => $isoHelper->getCurrencyIsoCode($this->getPaymentCurrencyCode($order)),
            'OrderID'                   => $orderId,
            'TransactionType'           => $config->getTransactionType(),
            'TransactionDateTime'       => date('Y-m-d H:i:s P'),
            'CallbackURL'               => $moduleHelper->getHpfCallbackUrl(),
            'OrderDescription'          => $order->getRealOrderId() . ': New order',
            'CustomerName'              => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            'Address1'                  => $billingAddress->getStreetLine(1),
            'Address2'                  => $billingAddress->getStreetLine(2),
            'Address3'                  => $billingAddress->getStreetLine(3),
            'Address4'                  => $billingAddress->getStreetLine(4),
            'City'                      => $billingAddress->getCity(),
            'State'                     => $billingAddress->getRegionCode(),
            'PostCode'                  => $billingAddress->getPostcode(),
            'CountryCode'               => $isoHelper->getCountryIsoCode($billingAddress-> getCountryId()),
            'EmailAddress'              => $order->getCustomerEmail(),
            'PhoneNumber'               => $billingAddress->getTelephone(),
            'EmailAddressEditable'      => $moduleHelper->getBool($config->getEmailAddressEditable()),
            'PhoneNumberEditable'       => $moduleHelper->getBool($config->getPhoneNumberEditable()),
            'CV2Mandatory'              => 'true',
            'Address1Mandatory'         => $moduleHelper->getBool($config->getAddress1Mandatory()),
            'CityMandatory'             => $moduleHelper->getBool($config->getCityMandatory()),
            'PostCodeMandatory'         => $moduleHelper->getBool($config->getPostcodeMandatory()),
            'StateMandatory'            => $moduleHelper->getBool($config->getStateMandatory()),
            'CountryMandatory'          => $moduleHelper->getBool($config->getCountryMandatory()),
            'ResultDeliveryMethod'      => $config->getResultDeliveryMethod(),
            'ServerResultURL'           => ('SERVER' === $config->getResultDeliveryMethod())
                ? $moduleHelper->getHpfCallbackUrl()
                : '',
            'PaymentFormDisplaysResult' => 'false'
        ];
    }

    /**
     * Builds the redirect form action URL and the variables for the Hosted Payment Form
     *
     * @return array
     */
    public function buildSamplePaymentFields()
    {
        $config = $this->getConfigHelper();
        return [
            'Amount'                    => 100,
            'CurrencyCode'              => $this->getIsoCodesHelper()->getCurrencyIsoCode(null),
            'OrderID'                   => 'TEST-' . rand(1000000, 9999999),
            'TransactionType'           => $config->getTransactionType(),
            'TransactionDateTime'       => date('Y-m-d H:i:s P'),
            'CallbackURL'               => $this->getModuleHelper()->getHpfCallbackUrl(),
            'OrderDescription'          => '',
            'CustomerName'              => '',
            'Address1'                  => '',
            'Address2'                  => '',
            'Address3'                  => '',
            'Address4'                  => '',
            'City'                      => '',
            'State'                     => '',
            'PostCode'                  => '',
            'CountryCode'               => '',
            'EmailAddress'              => '',
            'PhoneNumber'               => '',
            'EmailAddressEditable'      => 'true',
            'PhoneNumberEditable'       => 'true',
            'CV2Mandatory'              => 'true',
            'Address1Mandatory'         => 'false',
            'CityMandatory'             => 'false',
            'PostCodeMandatory'         => 'false',
            'StateMandatory'            => 'false',
            'CountryMandatory'          => 'false',
            'ResultDeliveryMethod'      => 'POST',
            'ServerResultURL'           => '',
            'PaymentFormDisplaysResult' => 'false'
        ];
    }

    /**
     * Checks whether a string contains a needle.
     *
     * @param string $string the string.
     * @param string $needle the needle.
     *
     * @return bool
     */
    public function contains($string, $needle)
    {
        return false !== strpos($string, $needle);
    }

    /**
     * Determines whether the response message is about invalid merchant credentials
     *
     * @param string $msg Message.
     * @return bool
     */
    public function merchantCredentialsInvalid($msg)
    {
        return $this->contains($msg, 'Input variable errors')
            || $this->contains($msg, 'Invalid merchant details');
    }

    /**
     * Gets the error message from the Hosted Payment Form response (span id lbErrorMessageLabel)
     *
     * @param string $data HTML document.
     *
     * @return string
     */
    protected function getHpfErrorMessage($data)
    {
        $result = null;
        if (preg_match('/<span.*lbErrorMessageLabel[^>]*>(.*?)<\/span>/si', $data, $matches)) {
            $result = strip_tags($matches[1]);
        }
        return $result;
    }

    /**
     * Calculates the hash digest.
     * Supported hash methods: SHA1, HMACMD5, HMACSHA1, HMACSHA256 and HMACSHA512
     *
     * @param string $data Data to be hashed.
     * @param string $hashMethod Hash method.
     * @param string $key Secret key to use for generating the hash.
     * @return string
     */
    public function calculateHashDigest($data, $hashMethod, $key)
    {
        $result     = '';
        $includeKey = $hashMethod == 'SHA1';
        if ($includeKey) {
            $data = 'PreSharedKey=' . $key . '&' . $data;
        }
        switch ($hashMethod) {
            case 'MD5':
                // No longer supported. The use of the md5 function is forbidden by Magento.
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
            case 'HMACSHA256':
                $result = hash_hmac('sha256', $data, $key);
                break;
            case 'HMACSHA512':
                $result = hash_hmac('sha512', $data, $key);
                break;
        }
        return $result;
    }

    /**
     * Adds a pair of a local and a remote timestamp
     *
     * @param string $url Remote URL
     * @param string $dateTime Timestamp
     */
    public function addDateTimePair($url, $dateTime)
    {
        $hostname                        = $this->getModuleHelper()->getHostname($url);
        $dateTime                        = DateTime::createFromFormat('D, d M Y H:i:s e', $dateTime);
        $this->_dateTimePairs[$hostname] = $this->getModuleHelper()->buildDateTimePair($dateTime);
    }

    /**
     * Gets the pairs of local and remote timestamps
     *
     * @return array
     */
    public function getDateTimePairs()
    {
        return $this->_dateTimePairs;
    }

    /**
     * Gets the system time status
     *
     * @return string
     */
    public function getSystemTimeStatus()
    {
        $timeDiff = $this->getSystemTimeDiff();
        if (is_numeric($timeDiff)) {
            $result = abs($timeDiff) <= $this->getSystemTimeThreshold()
                ? 'OK'
                : sprintf('Out of sync with %+d seconds', $timeDiff);
        } else {
            $result = 'Unknown';
        }

        return $result;
    }

    /**
     * Gets the difference between the system time and the gateway time in seconds
     *
     * @return string
     */
    public function getSystemTimeDiff()
    {
        $result = null;
        $dateTimePairs = $this->getDateTimePairs();
        if ($dateTimePairs) {
            $dateTimePair = array_shift($dateTimePairs);
            $result = $this->calculateDateDiff($dateTimePair);
        }

        return $result;
    }

    /**
     * Calculates the difference between DateTimes in seconds
     *
     * @param array $dateTimePair
     * @return int
     */
    public function calculateDateDiff($dateTimePair)
    {
        list($localDateTime, $remoteDateTime) = $dateTimePair;
        return $localDateTime->format('U') - $remoteDateTime->format('U');
    }

    /**
     * Gets the system time threshold
     *
     * @return int
     */
    public function getSystemTimeThreshold()
    {
        return $this->_systemTimeThreshold;
    }

    /**
     * Gets Payment Currency Code
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return string|null
     */
    public function getPaymentCurrencyCode($order)
    {
        $config = $this->getConfigHelper();
        return $config->isBaseCurrency() ? $order->getBaseCurrencyCode() : $order->getOrderCurrencyCode();
    }

    /**
     * Gets Payment Total Due
     *
     * @param  \Magento\Sales\Model\Order $order
     * @return float|null
     */
    public function getPaymentTotalDue($order)
    {
        $config = $this->getConfigHelper();
        return $config->isBaseCurrency() ? $order->getBaseTotalDue() : $order->getTotalDue();
    }
}
