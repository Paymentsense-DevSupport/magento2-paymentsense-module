<?php
/*
 * Copyright (C) 2019 Paymentsense Ltd.
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
 * @copyright   2019 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Paymentsense\Payments\Block\Frontend;

use Paymentsense\Payments\Controller\Action;

/**
 * ACS Text Messages block
 *
 * Used for localised text messages shown during the interaction with the ACS (Access Control Server)
 * Messages appear on the acsrequest template
 */
class Acs extends \Magento\Framework\View\Element\Template
{
    /**
     * Retrieves Cancel Order text
     *
     * @return string|false
     */
    public function getCancelOrderText()
    {
        try {
            $result = __('Cancel order & restore cart');
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Retrieves Common Error text
     *
     * @return string|false
     */
    public function getCommonErrorText()
    {
        try {
            $result = __('An error occurred while processing 3-D Secure authentication.');
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Retrieves Continue text
     *
     * @return string|false
     */
    public function getContinueText()
    {
        try {
            $result = __('Continue to the payment methods');
        } catch (\Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Gets the data for the ACS request form
     *
     * @return string JSON-encoded array
     */
    public function getAcsData()
    {
        return json_encode(
            [
                'loader'          => $this->getViewFileUrl('images/loader-2.gif'),
                'errorText'       => $this->getCommonErrorText(),
                'continueText'    => $this->getContinueText(),
                'cancelText'      => $this->getCancelOrderText(),
                'dataProviderUrl' => $this->getUrl('paymentsense/direct/dataprovider'),
                'cancelActionUrl' => $this->getUrl('paymentsense/direct', ['action' => Action::THREEDSCANCEL]),
                'errorActionUrl'  => $this->getUrl('paymentsense/direct', ['action' => Action::THREEDSERROR])
            ]
        );
    }
}
