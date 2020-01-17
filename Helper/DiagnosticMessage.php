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

namespace Paymentsense\Payments\Helper;

/**
 * Diagnostic Message helper
 *
 * @package Paymentsense\Payments\Helper
 */
class DiagnosticMessage extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * CSS class names
     */
    const SUCCESS_CLASS_NAME = 'success-text';
    const WARNING_CLASS_NAME = 'warning-text';
    const ERROR_CLASS_NAME   = 'error-text';

    /**
     * Message types
     */
    const MESSAGE_TYPE_STATUS      = 'status';
    const MESSAGE_TYPE_CONNECTION  = 'connection';
    const MESSAGE_TYPE_SETTINGS    = 'settings';
    const MESSAGE_TYPE_SYSTEM_TIME = 'stime';

    /**
     * Gets the payment method status
     *
     * @param bool $configured Specifies whether the payment method is configured
     * @param bool $secure Specifies whether the payment method is secure
     *
     * @return array
     */
    public function getStatusMessage($configured, $secure)
    {
        switch (true) {
            case ! $configured:
                $result = $this->buildErrorStatusMessage(
                    __('Unavailable (Payment method not configured)')
                );
                break;
            case ! $secure:
                $result = $this->buildErrorStatusMessage(
                    __('Unavailable (SSL/TLS not configured)')
                );
                break;
            default:
                $result = $this->buildSuccessStatusMessage(
                    __('Enabled')
                );
                break;
        }
        return $result;
    }

    /**
     * Gets the gateway connection status
     *
     * @param bool $connectionSuccessful Specifies whether the connection to the gateway is successful
     *
     * @return array
     */
    public function getConnectionMessage($connectionSuccessful)
    {
        if ($connectionSuccessful) {
            $result = $this->buildSuccessConnectionMessage(
                __('Successful')
            );
        } else {
            $result = $this->buildErrorConnectionMessage(
                __('Unavailable (No Connection to the gateway. Please check outbound port 4430).')
            );
        }
        return $result;
    }

    /**
     * Builds a localised success status message
     *
     * @param  string $text
     * @return array
     */
    public function buildSuccessStatusMessage($text)
    {
        return $this->buildMessage($text, self::SUCCESS_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised warning status message
     *
     * @param  string $text
     * @return array
     */
    public function buildWarningStatusMessage($text)
    {
        return $this->buildMessage($text, self::WARNING_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised error status message
     *
     * @param  string $text
     * @return array
     */
    public function buildErrorStatusMessage($text)
    {
        return $this->buildMessage($text, self::ERROR_CLASS_NAME, self::MESSAGE_TYPE_STATUS);
    }

    /**
     * Builds a localised success connection message
     *
     * @param  string $text
     * @return array
     */
    public function buildSuccessConnectionMessage($text)
    {
        return $this->buildMessage($text, self::SUCCESS_CLASS_NAME, self::MESSAGE_TYPE_CONNECTION);
    }

    /**
     * Builds a localised warning connection message
     *
     * @param  string $text
     * @return array
     */
    public function buildWarningConnectionMessage($text)
    {
        return $this->buildMessage($text, self::WARNING_CLASS_NAME, self::MESSAGE_TYPE_CONNECTION);
    }

    /**
     * Builds a localised error connection message
     *
     * @param  string $text
     * @return array
     */
    public function buildErrorConnectionMessage($text)
    {
        return $this->buildMessage($text, self::ERROR_CLASS_NAME, self::MESSAGE_TYPE_CONNECTION);
    }

    /**
     * Builds a localised success settings message
     *
     * @param  string $text
     * @return array
     */
    public function buildSuccessSettingsMessage($text)
    {
        return $this->buildMessage($text, self::SUCCESS_CLASS_NAME, self::MESSAGE_TYPE_SETTINGS);
    }

    /**
     * Builds a localised warning settings message
     *
     * @param  string $text
     * @return array
     */
    public function buildWarningSettingsMessage($text)
    {
        return $this->buildMessage($text, self::WARNING_CLASS_NAME, self::MESSAGE_TYPE_SETTINGS);
    }

    /**
     * Builds a localised error settings message
     *
     * @param  string $text
     * @return array
     */
    public function buildErrorSettingsMessage($text)
    {
        return $this->buildMessage($text, self::ERROR_CLASS_NAME, self::MESSAGE_TYPE_SETTINGS);
    }

    /**
     * Builds a localised error system time message
     *
     * @param  int $seconds
     * @return array
     */
    public function buildErrorSystemTimeMessage($seconds)
    {
        return $this->buildMessage(
            sprintf(
                __(
                    'The system time is out of sync with the gateway with %+d seconds. Please check your system time.'
                ),
                $seconds
            ),
            self::ERROR_CLASS_NAME,
            self::MESSAGE_TYPE_SYSTEM_TIME
        );
    }

    /**
     * Gets the text message from a status message
     *
     * @param  array $arr
     * @return string
     */
    public function getStatusTextMessage($arr)
    {
        return $arr[self::MESSAGE_TYPE_STATUS . 'Text'];
    }

    /**
     * Gets the text message from a connection message
     *
     * @param  array $arr
     * @return string
     */
    public function getConnectionTextMessage($arr)
    {
        return $arr[self::MESSAGE_TYPE_CONNECTION . 'Text'];
    }

    /**
     * Gets the text message from a settings message
     *
     * @param  array $arr
     * @return string
     */
    public function getSettingsTextMessage($arr)
    {
        return $arr[self::MESSAGE_TYPE_SETTINGS . 'Text'];
    }

    /**
     * Builds a localised message
     *
     * @param  string $text
     * @param  string $className
     * @param  string $messageType
     * @return array
     */
    private function buildMessage($text, $className, $messageType)
    {
        return [
            $messageType . 'Text'      => $text,
            $messageType . 'ClassName' => $className
        ];
    }
}
