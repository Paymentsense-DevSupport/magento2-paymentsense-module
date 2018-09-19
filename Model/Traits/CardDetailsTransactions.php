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

namespace Paymentsense\Payments\Model\Traits;

use Paymentsense\Payments\Model\Psgw\TransactionType;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;

/**
 * Trait for post-processing of Card Details Transactions
 */
trait CardDetailsTransactions
{
    /**
     * Updates payment info and registers Card Details Transactions
     *
     * @param \Magento\Sales\Model\Order $order
     * @param array $response An array containing transaction response data from the gateway
     *
     * @throws \Exception
     */
    public function updatePayment($order, $response)
    {
        $transactionID     = $response['CrossReference'];
        $payment           = $order->getPayment();
        $lastTransactionId = $payment->getLastTransId();
        $payment->setMethod($this->getCode());
        $payment->setLastTransId($transactionID);
        $payment->setTransactionId($transactionID);
        $payment->setParentTransactionId($lastTransactionId);
        $payment->setShouldCloseParentTransaction(true);
        $payment->setIsTransactionPending($response['StatusCode'] !== TransactionResultCode::SUCCESS);
        $payment->setIsTransactionClosed($response['TransactionType'] === TransactionType::SALE);
        $this->getModuleHelper()->setPaymentTransactionAdditionalInfo($payment, $response);
        if ($response['StatusCode'] === TransactionResultCode::SUCCESS) {
            if ($response['TransactionType'] === TransactionType::SALE) {
                $payment->registerCaptureNotification($response['Amount'] / 100);
            } else {
                $payment->registerAuthorizationNotification($response['Amount'] / 100);
            }
        }
        $order->save();
    }
}
