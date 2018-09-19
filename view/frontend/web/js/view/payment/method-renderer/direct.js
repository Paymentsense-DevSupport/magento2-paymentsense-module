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

define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/action/place-order',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (
        $,
        Component,
        customerData,
        placeOrderAction,
        customer,
        additionalValidators,
        url
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Paymentsense_Payments/payment/method/direct/form'
            },

            getCode: function () {
                return 'paymentsense_direct';
            },

            isActive: function () {
                return true;
            },

            validate: function () {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },

            placeOrder: function (data, event) {
                
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('paymentsense/direct/index'));
            }
        });
    }
);
