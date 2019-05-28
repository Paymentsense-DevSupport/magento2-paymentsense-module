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

namespace Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset\Base;

use Magento\Backend\Model\Auth\Session;

/**
 * Base renderer for all payment methods in the admin panel
 *
 * @package Paymentsense\Payments\Block\Adminhtml\System\Config\Fieldset\Base
 */
abstract class Payment extends \Magento\Config\Block\System\Config\Form\Fieldset
{
    /**
     * @var \Paymentsense\Payments\Model\Method\Hosted|\Paymentsense\Payments\Model\Method\Direct|\Paymentsense\Payments\Model\Method\Moto
     */
    protected $method;

    public $_urlHelper;

    /**
     * @param \Magento\Backend\Block\Context $context
     * @param \Magento\Backend\Model\Auth\Session $authSession
     * @param \Magento\Framework\Url $urlHelper
     * @param \Magento\Framework\View\Helper\Js $jsHelper
     */
    public function __construct(
        \Magento\Backend\Block\Context $context,
        Session $authSession,
        \Magento\Framework\View\Helper\Js $jsHelper,
        \Magento\Framework\Url $urlHelper
    ) {
        $this->_urlHelper = $urlHelper;
        parent::__construct($context, $authSession, $jsHelper);
    }

    /**
     * Retrieves the payment card logos CSS class
     *
     * @return string
     */
    public function getPaymentCardLogosCssClass()
    {
        return $this->method->getConfigData('allow_amex') ? "card-logos" : "card-logos-no-amex";
    }

    /**
     * Adds custom css class
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getFrontendClass($element)
    {
        return parent::_getFrontendClass($element) . ' with-button';
    }

    /**
     * Returns header title part of the html for the payment method
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getHeaderTitleHtml($element)
    {
        $htmlId = $element->getHtmlId();
        $initialStatus = $this->method->isEnabled() ? __('Testing...') : __('Disabled');
        $initialConnection = $this->method->isEnabled() ? __('Testing...') : __('Unknown');
        $initialSettings = $this->method->isEnabled() ? __('Testing...') : __('Unknown');
        $html = '<div class="config-heading">';
        $html .= '<div class="button-container"><button type="button"' .
            ' class="button action-configure' .
            '" id="' .
            $htmlId .
            '-head" onclick="togglePaymentsenseMethod.call(this, \'' .
            $htmlId .
            "', '" .
            $this->getUrl(
                'adminhtml/*/state'
            ) . '\'); return false;"><span class="state-closed">' . __(
                'Configure'
            ) . '</span><span class="state-opened">' . __(
                'Close'
            ) . '</span></button>';

        $html .= '</div>';
        $html .= '<div class="heading"><strong>' . $element->getLegend() . '</strong>';

        if ($element->getComment()) {
            $html .= '<span>' . $element->getComment() . '</span>';
        }
        $html .= '<div class="config-alt ' . $this->getPaymentCardLogosCssClass() . '"></div>';
        $html .= '<div class="statusDiv">';
        $html .= '<span>' . __('Payment Method Status:') . ' </span>';
        $html .= '<span id="' . $htmlId . '-status">' . $initialStatus . '</span>';
        $html .= '</div><div class="connectionDiv">';
        $html .= '<span>' . __('Connection to Gateway Servers:') . ' </span>';
        $html .= '<span id="' . $htmlId . '-connection">' . $initialConnection . '</span>';
        $html .= '</div><div class="settingsDiv">';
        $html .= '<span>' . __('Gateway Settings:') . ' </span>';
        $html .= '<span id="' . $htmlId . '-settings">' . $initialSettings . '</span>';
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Returns header comment part of the html for the payment method
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        return '';
    }

    /**
     * Gets collapsed state on-load
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return bool
     */
    protected function _isCollapseState($element)
    {
        return false;
    }

    /**
     * Adds JavaScript code
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    // @codingStandardsIgnoreLine
    protected function _getExtraJs($element)
    {
        $htmlId = $element->getHtmlId();
        $code = $this->method->getCode();
        $route = str_replace('_', '/', $code) . '/status';
        $statusUrl = $this->_urlHelper->getUrl($route);

        $script = '';
        if ($this->method->isEnabled()) {
            $script = "
            getStatus();

            /**
             * Gets payment method status and connection
             */
            function getStatus()
            {
                var paymentMethodStatusUrl = '" . $statusUrl . "';
                $.ajax({
                    url: paymentMethodStatusUrl,
                    type: \"GET\",
                    success: showInfo,
                    error: showUnknownInfo
                });
            }

            /**
             * Shows the status, connection and settings
             */
            function showInfo(data)
            {
                if (data.statusText != null) {
                    var statusSpan = document.getElementById('" . $htmlId . "-status');
                    var connectionSpan = document.getElementById('" . $htmlId . "-connection');
                    var settingsSpan = document.getElementById('" . $htmlId . "-settings');
                    statusSpan.innerHTML = data.statusText;
                    statusSpan.className = data.statusClassName;
                    connectionSpan.innerHTML = data.connectionText;
                    connectionSpan.className = data.connectionClassName;
                    settingsSpan.innerHTML = data.settingsText;
                    settingsSpan.className = data.settingsClassName;
                } else {
                    showUnknownInfo(data);
                }
            }

            /**
             * Shows an unknown status, connection and settings
             */
            function showUnknownInfo(data)
            {
                var statusSpan = document.getElementById('" . $htmlId . "-status');
                var connectionSpan = document.getElementById('" . $htmlId . "-connection');
                var settingsSpan = document.getElementById('" . $htmlId . "-settings');
                statusSpan.innerHTML = '" . __('Unknown') . "';
                connectionSpan.innerHTML = '" . __('Unknown') . "';
                settingsSpan.innerHTML = '" . __('Unknown') . "';
            }            
            ";
        }

        $script = "require(['jquery', 'prototype'], function($){
            window.togglePaymentsenseMethod = function (id, url) {
                Fieldset.toggleCollapse(id, url);
            }" . $script . "
        });";
        return $this->_jsHelper->getScript($script);
    }
}
