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

/**
 * Provides data for the form redirecting to the Hosted Payment Form
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class DataProvider extends \Paymentsense\Payments\Controller\CheckoutAction
{
    /**
     * Handles ajax requests and provides the form data for redirecting to the Hosted Payment Form
     * Generates application/json response containing the form data in JSON format
     */
    public function execute()
    {
        $order = $this->getOrder();
        if (isset($order)) {
            $hosted = $this->getObjectManager()->create(Hosted::class);
            $data = $hosted->buildFormData($order);
            $this->getResponse()
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode($data));
        } else {
            $this->redirectToCheckoutOnePageSuccess();
        }
    }
}
