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

namespace Paymentsense\Payments\Controller\Hosted;

use Paymentsense\Payments\Model\Method\Hosted;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;
use Paymentsense\Payments\Model\Psgw\TransactionStatus;

/**
 * Handles the response from the payment gateway
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class Index extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * Handles the result from the Payment Gateway
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $message = '';
        if (!$this->getRequest()->isPost()) {
            return;
        }
        $postData = $this->getPostData();
        $hosted = $this->getObjectManager()->create(Hosted::class);
        $transactionStatus = TransactionStatus::INVALID;
        if ($hosted->isHashDigestValid($postData)) {
            $message = $postData['Message'];
            switch ($postData['StatusCode']) {
                case TransactionResultCode::SUCCESS:
                    $transactionStatus = TransactionStatus::SUCCESS;
                    break;
                case TransactionResultCode::DUPLICATE:
                    if (TransactionResultCode::SUCCESS === $postData['PreviousStatusCode']) {
                        if (array_key_exists('PreviousMessage', $postData)) {
                            $message = $postData['PreviousMessage'];
                        }
                        $transactionStatus = TransactionStatus::SUCCESS;
                    } else {
                        $transactionStatus = TransactionStatus::FAILED;
                    }
                    break;
                case TransactionResultCode::REFERRED:
                case TransactionResultCode::DECLINED:
                case TransactionResultCode::FAILED:
                    $transactionStatus = TransactionStatus::FAILED;
                    break;
            }
            $hosted->updatePayment($postData);
        }
        $this->processActions($transactionStatus, $message);
    }

    /**
     * Processes actions based on the transaction status
     *
     * @param string $transactionStatus
     * @param string $message Message
     */
    private function processActions($transactionStatus, $message)
    {
        switch ($transactionStatus) {
            case TransactionStatus::SUCCESS:
                $this->executeSuccessAction();
                break;
            case TransactionStatus::FAILED:
                $this->executeFailureAction($message);
                break;
            case TransactionStatus::INVALID:
                $this->getResponse()->setHttpResponseCode(
                    \Magento\Framework\Webapi\Exception::HTTP_UNAUTHORIZED
                );
                break;
        }
    }
}
