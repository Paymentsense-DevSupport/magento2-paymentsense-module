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

namespace Paymentsense\Payments\Model\Psgw;

/**
 * Paymentsense Gateway Class
 */
class Psgw
{
    /**
     * @var int $trxMaxAttempts Number of attempts to perform a transaction
     */
    protected $trxMaxAttempts = 3;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory $clientFactory
     */
    protected $clientFactory;

    /**
     * @param \Magento\Framework\HTTP\ZendClientFactory $clientFactory
     */
    public function __construct(
        \Magento\Framework\HTTP\ZendClientFactory $clientFactory
    ) {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Sets the number of attempts to perform a transaction
     *
     * @param int $trxMaxAttempts Number of attempts to perform a transaction
     */
    public function setTrxMaxAttempts($trxMaxAttempts)
    {
        $this->trxMaxAttempts = $trxMaxAttempts;
    }

    /**
     * Performs card details transactions (SALE, PREAUTH)
     *
     * @param  array    $trxData The transaction data
     * @return array
     */
    public function performCardDetailsTxn($trxData)
    {
        $headers = [
            'SOAPAction:https://www.thepaymentgateway.net/CardDetailsTransaction',
            'Content-Type: text/xml; charset=utf-8',
            'Accept: text/xml, application/xml, */*',
            'Accept-Encoding: gzip, deflate',
            'Connection: close',
        ];

        // @codingStandardsIgnoreStart
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                     <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                         <soap:Body>
                             <CardDetailsTransaction xmlns="https://www.thepaymentgateway.net/">
                                 <PaymentMessage>
                                     <MerchantAuthentication MerchantID="' . $trxData['MerchantID'] . '" Password="' . $trxData['Password'] . '" />
                                     <TransactionDetails Amount="' . $trxData['Amount'] . '" CurrencyCode="' . $trxData['CurrencyCode'] . '">
                                         <MessageDetails TransactionType="' . $trxData['TransactionType'] . '" />
                                         <OrderID>' . $trxData['OrderID'] . '</OrderID>
                                         <OrderDescription>' . $trxData['OrderDescription'] . '</OrderDescription>
                                         <TransactionControl>
                                            <EchoCardType>TRUE</EchoCardType>
                                            <EchoAVSCheckResult>TRUE</EchoAVSCheckResult>
                                            <EchoCV2CheckResult>TRUE</EchoCV2CheckResult>
                                            <EchoAmountReceived>TRUE</EchoAmountReceived>
                                            <DuplicateDelay>20</DuplicateDelay>
                                         </TransactionControl>
                                     </TransactionDetails>
                                     <CardDetails>
                                        <CardName>' . $trxData['CardName'] . '</CardName>
                                        <CardNumber>' . $trxData['CardNumber'] . '</CardNumber>
                                        <ExpiryDate Month="' . $trxData['ExpMonth'] . '" Year="' . $trxData['ExpYear'] . '" />
                                        <CV2>' . $trxData['CV2'] . '</CV2>
                                        <IssueNumber>' . $trxData['IssueNumber'] . '</IssueNumber>
                                     </CardDetails>
                                     <CustomerDetails>
                                         <BillingAddress>
                                            <Address1>' . $trxData['Address1'] . '</Address1>
                                            <Address2>' . $trxData['Address2'] . '</Address2>
                                            <Address3>' . $trxData['Address3'] . '</Address3>
                                            <Address4>' . $trxData['Address4'] . '</Address4>
                                            <City>' . $trxData['City'] . '</City>
                                            <State>' . $trxData['State'] . '</State>
                                            <PostCode>' . $trxData['PostCode'] . '</PostCode>
                                            <CountryCode>' . $trxData['CountryCode'] . '</CountryCode>
                                         </BillingAddress>
                                         <EmailAddress>' . $trxData['EmailAddress'] . '</EmailAddress>
                                         <PhoneNumber>' . $trxData['PhoneNumber'] . '</PhoneNumber>
                                         <CustomerIPAddress>' . $trxData['IPAddress'] . '</CustomerIPAddress>
                                     </CustomerDetails>
                                 </PaymentMessage>
                             </CardDetailsTransaction>
                         </soap:Body>
                     </soap:Envelope>';
        // @codingStandardsIgnoreEnd

        return $this->executeTransaction($headers, $xml);
    }

    /**
     * Performs 3-D Secure Authentication
     *
     * @param  array $trxData The transaction data
     * @return array
     */
    public function perform3dsAuthTxn($trxData)
    {
        $headers = [
            'SOAPAction:https://www.thepaymentgateway.net/ThreeDSecureAuthentication',
            'Content-Type: text/xml; charset=utf-8',
            'Accept: text/xml, application/xml, */*',
            'Accept-Encoding: gzip, deflate',
            'Connection: close',
        ];

        // @codingStandardsIgnoreStart
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                     <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                        <soap:Body>
                            <ThreeDSecureAuthentication xmlns="https://www.thepaymentgateway.net/">
                                <ThreeDSecureMessage>
                                    <MerchantAuthentication MerchantID="' . $trxData['MerchantID'] . '" Password="' . $trxData['Password'] . '" />
                                    <ThreeDSecureInputData CrossReference="' . $trxData['CrossReference'] . '">
                                        <PaRES>' . $trxData['PaRES'] . '</PaRES>
                                    </ThreeDSecureInputData>
                                </ThreeDSecureMessage>
                            </ThreeDSecureAuthentication>
                        </soap:Body>
                     </soap:Envelope>';
        // @codingStandardsIgnoreEnd

        return $this->executeTransaction($headers, $xml);
    }

    /**
     * Performs cross reference transactions (COLLECTION, REFUND, VOID)
     *
     * @param  array $trxData The transaction data
     * @return array
     */
    public function performCrossRefTxn($trxData)
    {
        $headers = [
            'SOAPAction:https://www.thepaymentgateway.net/CrossReferenceTransaction',
            'Content-Type: text/xml; charset=utf-8',
            'Accept: text/xml, application/xml, */*',
            'Accept-Encoding: gzip, deflate',
            'Connection: close',
        ];

        $transactionDetails = '';
        if ($trxData['Amount'] != '') {
            $transactionDetails .= ' Amount="' . $trxData['Amount'] . '"';
        }
        if ($trxData['CurrencyCode'] != '') {
            $transactionDetails .= ' CurrencyCode="' . $trxData['CurrencyCode'] . '"';
        }

        // @codingStandardsIgnoreStart
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                     <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                         <soap:Body>
                             <CrossReferenceTransaction xmlns="https://www.thepaymentgateway.net/">
                                 <PaymentMessage>
                                     <MerchantAuthentication MerchantID="' . $trxData['MerchantID'] . '" Password="' . $trxData['Password'] . '" />
                                     <TransactionDetails' . $transactionDetails . '>
                                         <MessageDetails TransactionType="' . $trxData['TransactionType'] . '" NewTransaction="FALSE" CrossReference="' . $trxData['CrossReference'] . '" />
                                         <OrderID>' . $trxData['OrderID'] . '</OrderID>
                                         <OrderDescription>' . $trxData['OrderDescription'] . '</OrderDescription>
                                         <TransactionControl>
                                             <EchoCardType>FALSE</EchoCardType>
                                             <EchoAVSCheckResult>FALSE</EchoAVSCheckResult>
                                             <EchoCV2CheckResult>FALSE</EchoCV2CheckResult>
                                             <EchoAmountReceived>FALSE</EchoAmountReceived>
                                             <DuplicateDelay>10</DuplicateDelay>
                                             <AVSOverridePolicy>BPPF</AVSOverridePolicy>
                                             <ThreeDSecureOverridePolicy>FALSE</ThreeDSecureOverridePolicy>
                                         </TransactionControl>
                                     </TransactionDetails>
                                 </PaymentMessage>
                             </CrossReferenceTransaction>
                         </soap:Body>
                     </soap:Envelope>';
        // @codingStandardsIgnoreEnd

        return $this->executeTransaction($headers, $xml);
    }

    /**
     * Performs GetGatewayEntryPoints transaction
     *
     * @param  array $trxData The transaction data
     * @return array
     */
    public function performGetGatewayEntryPointsTxn($trxData)
    {
        $headers = [
            'SOAPAction:https://www.thepaymentgateway.net/GetGatewayEntryPoints',
            'Content-Type: text/xml; charset=utf-8',
            'Accept: text/xml, application/xml, */*',
            'Accept-Encoding: gzip, deflate',
            'Connection: close',
        ];

        // @codingStandardsIgnoreStart
        $xml = '<?xml version="1.0" encoding="utf-8"?>
                     <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                         <soap:Body>
                             <GetGatewayEntryPoints xmlns="https://www.thepaymentgateway.net/">
                                 <GetGatewayEntryPointsMessage>
                                     <MerchantAuthentication MerchantID="' . $trxData['MerchantID'] . '" Password="' . $trxData['Password'] . '" />
                                 </GetGatewayEntryPointsMessage>
                             </GetGatewayEntryPoints>
                         </soap:Body>
                     </soap:Envelope>';
        // @codingStandardsIgnoreEnd

        return $this->executeTransaction($headers, $xml);
    }

    /**
     * Performs transactions to the Paymentsense gateway
     *
     * @param array $headers Request Headers
     * @param string $xml Request Body
     * @return array
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore Generic.Metrics.CyclomaticComplexity, Generic.Metrics.NestingLevel
    public function executeTransaction($headers, $xml)
    {
        $gatewayId            = 1;
        $trxAttempt           = 0;
        $validResponse        = false;
        $trxAttemptsExhausted = false;
        $responseHeaders      = [];

        // Initial message. Will be replaced by the gateway message or cURL error message.
        $trxMessage = 'The communication with the Payment Gateway failed. Check outbound connection.';

        $trxStatusCode     = false;
        $trxDetail         = false;
        $trxCrossReference = false;
        $responseBody      = '';
        while (! $validResponse && ! $trxAttemptsExhausted) {
            $trxAttempt++;
            if ($trxAttempt > $this->trxMaxAttempts) {
                $trxAttempt = 1;
                $gatewayId++;
            }

            $url = GatewayEndpoints::getPaymentGatewayUrl($gatewayId);
            if (is_string($url)) {
                $data = [
                    'url'     => $url,
                    'method'  => 'POST',
                    'headers' => $headers,
                    'xml'     => $xml,
                ];

                try {
                    $response = $this->executeHttpRequest($data);
                    if ($response->getStatus() === \Zend\Http\Response::STATUS_CODE_200) {
                        $responseHeaders[$url] = $response->getHeaders();
                        $responseBody          = $response->getBody();

                        if (!empty($responseBody)) {
                            $trxStatusCode = $this->getXmlValue('StatusCode', $responseBody, '[0-9]+');
                            if (is_numeric($trxStatusCode)) {
                                $trxMessage    = $this->getXmlValue('Message', $responseBody, '.+');
                                $validResponse = ! $this->shouldRetryTxn($trxStatusCode, $trxMessage);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Swallows the exceptions thrown by Zend_Http_Client. No action is required.
                    unset($e);
                }
            } else {
                $trxAttemptsExhausted = true;
            }
        };

        if ($validResponse) {
            $trxMessage        = $this->getXmlValue('Message', $responseBody, '.+');
            $trxDetail         = $this->getXmlValue('Detail', $responseBody, '.+');
            $trxCrossReference = $this->getXmlCrossReference($responseBody);
            if (TransactionResultCode::DUPLICATE === $trxStatusCode) {
                $prevTrxResult = $this->getXmlPreviousTransactionResult($responseBody);
                if (is_string($prevTrxResult)) {
                    $prevTrxStatusCode = $this->getXmlValue('StatusCode', $prevTrxResult, '.+');
                    $prevTrxMessage    = $this->getXmlValue('Message', $prevTrxResult, '.+');
                    if (is_numeric($prevTrxStatusCode)) {
                        $trxStatusCode = $prevTrxStatusCode;
                        $trxMessage    = $prevTrxMessage;
                    }
                }
            }
        }

        $result = [
            'StatusCode'      => $trxStatusCode,
            'Message'         => $trxMessage,
            'Detail'          => $trxDetail,
            'CrossReference'  => $trxCrossReference,
            'ACSURL'          => $this->getXmlValue('ACSURL', $responseBody, '.+'),
            'PaReq'           => $this->getXmlValue('PaReq', $responseBody, '.+'),
            'ResponseHeaders' => $responseHeaders
        ];

        return $result;
    }

    /**
     * Determines whether the transaction should be retried.
     * Cross reference transactions having response "Couldn't find previous transaction" should retry.
     *
     * @param string $trxStatusCode
     * @param string $trxMessage
     * @return bool
     */
    public function shouldRetryTxn($trxStatusCode, $trxMessage)
    {
        return (TransactionResultCode::FAILED === $trxStatusCode) &&
            ("Couldn't find previous transaction" === $trxMessage);
    }

    /**
     * Performs HTTP requests by using Zend Http Client
     *
     * @param array $data Data
     * @return \Zend_Http_Response|false
     *
     * @throws \Zend_Http_Client_Exception
     */
    public function executeHttpRequest($data)
    {
        $config = [
            'adapter'      => 'Zend_Http_Client_Adapter_Curl',
            'ssltransport' => 'tls',
            'strict'       => false,
            'persistent'   => true,
            'timeout'      => 10
        ];

        $client = new \Zend_Http_Client($data['url'], $config);
        $client->setRawData($data['xml']);
        $client->setHeaders($data['headers']);
        return $client->request($data['method']);
    }

    /**
     * Gets the value of a XML element from a XML document
     *
     * @param string $xml_element XML element.
     * @param string $xml XML document.
     * @param string $pattern Regular expression pattern.
     * @return string|false
     */
    private function getXmlValue($xml_element, $xml, $pattern)
    {
        $result = $xml_element . ' Not Found';
        if (preg_match('#<' . $xml_element . '>(' . $pattern . ')</' . $xml_element . '>#iU', $xml, $matches)) {
            $result = $matches[1];
        }
        return $result;
    }

    /**
     * Gets the value of the CrossReference element from a XML document
     *
     * @param string $xml XML document.
     * @return string
     */
    private function getXmlCrossReference($xml)
    {
        $result = 'No Data Found';
        if (preg_match('#<TransactionOutputData CrossReference="(.+)">#iU', $xml, $matches)) {
            $result = $matches[1];
        }
        return $result;
    }

    /**
     * Gets the value of the PreviousTransactionResult element from a XML document
     *
     * @param string $xml XML document.
     * @return string|false
     */
    private function getXmlPreviousTransactionResult($xml)
    {
        $result = false;
        if (preg_match('#<PreviousTransactionResult>(.+)</PreviousTransactionResult>#iU', $xml, $matches)) {
            $result = $matches[1];
        }
        return $result;
    }
}
