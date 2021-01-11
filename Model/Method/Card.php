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

namespace Paymentsense\Payments\Model\Method;

use Paymentsense\Payments\Model\Psgw\Psgw;
use Paymentsense\Payments\Model\Psgw\TransactionType;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Paymentsense\Payments\Model\Psgw\HpfResponses;
use Paymentsense\Payments\Model\Traits\BaseInfoMethod;
use Magento\Checkout\Model\Session;

/**
 * Abstract Card class used by the Direct and MOTO payment methods
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
abstract class Card extends \Magento\Payment\Model\Method\Cc
{
    use BaseInfoMethod;

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
     * @var \Paymentsense\Payments\Helper\DiagnosticMessage
     */
    protected $_messageHelper;

    /**
     * @var OrderSender
     */
    protected $_orderSender;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    protected $moduleReader;

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
     * @param \Paymentsense\Payments\Helper\IsoCodes $isoCodes
     * @param \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface
     * @param \Magento\Framework\Module\Dir\Reader $moduleReader
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
        Session $checkoutSession,
        \Paymentsense\Payments\Helper\Data $moduleHelper,
        \Paymentsense\Payments\Helper\IsoCodes $isoCodes,
        \Paymentsense\Payments\Helper\DiagnosticMessage $messageHelper,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
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
        $this->_isoCodes        = $isoCodes;
        $this->_orderSender     = $orderSender;
        $this->_configHelper    = $this->getModuleHelper()->getMethodConfig($this->getCode());
        $this->_messageHelper   = $messageHelper;
        $this->productMetadata  = $productMetadataInterface;
        $this->moduleReader     = $moduleReader;
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
     *
     * @throws \Magento\Framework\Exception\LocalizedException
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
                $message = sprintf('Error: Transaction type "%s" is not supported', $transactionType);
                $this->getLogger()->error($message);
                $this->getModuleHelper()->throwWebapiException(
                    sprintf(__('Error: Transaction type "%s" is not supported'), $transactionType)
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
    // @codingStandardsIgnoreLine
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->getLogger()->info('ACTION_AUTHORIZE has been triggered.');
        $order = $payment->getOrder();
        if ($this->_canUseCheckout) {
            $order->setCanSendNewEmailFlag(false);
        }
        $orderId = $order->getIncrementId();
        $this->getLogger()->info('Preparing PREAUTH transaction for order #' . $orderId);
        return $this->processInitialTransaction($payment);
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
            return $this->processInitialTransaction($payment);
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
     * @return array
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function buildInitialTransactionData($payment)
    {
        $config         = $this->getConfigHelper();
        $isoHelper      = $this->getIsoCodesHelper();
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
            'Amount'           => $this->getPaymentTotalDue($order) * 100,
            'CurrencyCode'     => $isoHelper->getCurrencyIsoCode($this->getPaymentCurrencyCode($order)),
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
            'CountryCode'      => $isoHelper->getCountryCode($billingAddress-> getCountryId()),
            'EmailAddress'     => $order->getCustomerEmail(),
            'PhoneNumber'      => $billingAddress->getTelephone(),
            'IPAddress'        => $order->getRemoteIp(),
        ];
    }

    /**
     * Processes initial transaction (Card Details Transaction)
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return $this
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    protected function processInitialTransaction($payment)
    {
        $config            = $this->getConfigHelper();
        $order             = $payment->getOrder();
        $orderId           = $order->getRealOrderId();
        $transactionType   = $config->getTransactionType();
        $fields            = $this->buildInitialTransactionData($payment);

        $request = array_map(
            function ($value) {
                return $value === null ? '' : $this->getModuleHelper()->filterUnsupportedChars($value);
            },
            $fields
        );

        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);
        try {
            $errorMessage = null;
            $response     = $psgw->performCardDetailsTxn($request);
            $status       = $response['StatusCode'];
            if ($status !== false) {
                $payment
                    ->setMethod($this->getCode())
                    ->setTransactionId($response['CrossReference'])
                    ->setIsTransactionPending($status === TransactionResultCode::INCOMPLETE)
                    ->setIsTransactionClosed(false);
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
                switch ($status) {
                    case TransactionResultCode::SUCCESS:
                        $isTransactionFailed = false;
                        $this->updatePayment($order, $response, $request, false);
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
     *
     * @throws \Exception
     */
    public function process3dsResponse($order, $postData)
    {
        $orderId = $order->getRealOrderId();
        $config  = $this->getConfigHelper();
        $status  = null;
        $message = "Invalid response received from the ACS.";
        if (array_key_exists('PaRes', $postData) && array_key_exists('MD', $postData)) {
            $request = [
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
                $response = $psgw->perform3dsAuthTxn($request);
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
                    ->setIsTransactionClosed($isTransactionFailed);

                $this->getModuleHelper()->setPaymentTransactionAdditionalInfo($payment, $response);

                $message = $isTransactionFailed
                    ? '3-D Secure Authentication failed. Payment Gateway Message: ' . $response['Message']
                    : '';

                $this->getModuleHelper()->setOrderState($order, $status, $message);
                $response['OrderID']         = $orderId;
                $response['Amount']          = $this->getPaymentTotalDue($order) * 100;
                $response['TransactionType'] = $config->getTransactionType();

                $initialTransaction = $this->getModuleHelper()->getPaymentTransaction($postData['MD'], 'txn_id');
                $request = $this->getModuleHelper()->getPaymentTransactionAdditionalInfo($initialTransaction);
                $this->updatePayment($order, $response, $request, true);
                if (! $isTransactionFailed) {
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
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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

    /**
     * Gets the gateway settings message
     *
     * @param bool $textFormat Specifies whether the format of the message is text
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity
    public function getSettingsMessage($textFormat)
    {
        $result = [];
        try {
            $merchantIdFormatValid = $this->getModuleHelper()->isMerchantIdFormatValid($this->getCode());
        } catch (\Exception $e) {
            $merchantIdFormatValid = false;
        }
        if (! $merchantIdFormatValid) {
            $result = $this->_messageHelper->buildErrorSettingsMessage(
                __(
                    'Gateway MerchantID is invalid. '
                    . 'Please make sure the Gateway MerchantID matches the ABCDEF-1234567 format.'
                )
            );
        } else {
            $merchantCredentialsValid = null;
            $ggepResult = $this->performGetGatewayEntryPointsTxn();
            $trxStatusCode = $ggepResult['StatusCode'];
            if (TransactionResultCode::SUCCESS === $trxStatusCode) {
                $merchantCredentialsValid = true;
            } elseif (TransactionResultCode::FAILED === $trxStatusCode) {
                if ($this->merchantCredentialsInvalid($ggepResult['Message'])) {
                    $merchantCredentialsValid = false;
                }
            }
            if (true === $merchantCredentialsValid) {
                $result = $this->_messageHelper->buildSuccessSettingsMessage(
                    __(
                        'Gateway MerchantID and Gateway Password are valid.'
                    )
                );
            } else {
                $hpfResult = $this->checkGatewaySettings();
                switch ($hpfResult) {
                    case HpfResponses::HPF_RESP_MID_MISSING:
                    case HpfResponses::HPF_RESP_MID_NOT_EXISTS:
                        $result = $this->_messageHelper->buildErrorSettingsMessage(
                            __(
                                'Gateway MerchantID is invalid.'
                            )
                        );
                        break;
                    case HpfResponses::HPF_RESP_HASH_INVALID:
                        if (false === $merchantCredentialsValid) {
                            $result = $this->_messageHelper->buildErrorSettingsMessage(
                                __(
                                    'Gateway Password is invalid.'
                                )
                            );
                        } else {
                            $result = $this->_messageHelper->buildWarningSettingsMessage(
                                __(
                                    'The gateway settings cannot be validated at this time.'
                                )
                            );
                        }
                        break;
                    case HpfResponses::HPF_RESP_NO_RESPONSE:
                        if (false === $merchantCredentialsValid) {
                            $result = $this->_messageHelper->buildErrorSettingsMessage(
                                __(
                                    'Gateway MerchantID or/and Gateway Password are invalid.'
                                )
                            );
                        } else {
                            $result = $this->_messageHelper->buildWarningSettingsMessage(
                                __(
                                    'The gateway settings cannot be validated at this time.'
                                )
                            );
                        }
                        break;
                }
            }
        }

        if ($textFormat) {
            $result = $this->_messageHelper->getSettingsTextMessage($result);
        }

        return $result;
    }
}
