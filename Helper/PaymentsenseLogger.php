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

namespace Paymentsense\Payments\Helper;

/**
 * Paymentsense Logger
 *
 * @package Paymentsense\Payments\Helper
 */
class PaymentsenseLogger extends \Monolog\Logger
{
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod $method Payment method
     */
    protected $method = null;

    /**
     * @param string             $name       The logging channel
     * @param HandlerInterface[] $handlers   Optional stack of handlers
     * @param callable[]         $processors Optional array of processors
     * @param mixed              $method     Payment method
     */
    public function __construct($name, array $handlers = [], array $processors = [], $method = null)
    {
        parent:: __construct($name, $handlers, $processors);
        $this->method = $method;
    }

    /**
     * Gets Log level
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getLogLevel()
    {
        return $this->method->getConfigHelper()->getLogLevel();
    }

    /**
     * Logs error messages to the Paymentsense log
     *
     * Requires Log Level 1 or higher
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return boolean Whether the record has been processed
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function error($message, array $context = [])
    {
        $result = false;
        if ($this->getLogLevel()>=1) {
            $result = $this->addRecord(static::ERROR, $message, $context);
        }
        return $result;
    }

    /**
     * Logs warning messages to the Paymentsense log
     *
     * Requires Log Level 2 or higher
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return boolean Whether the record has been processed
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function warning($message, array $context = [])
    {
        $result = false;
        if ($this->getLogLevel()>=2) {
            $result = $this->addRecord(static::WARNING, $message, $context);
        }
        return $result;
    }

    /**
     * Logs info messages to the Paymentsense log
     *
     * Requires Log Level 3 or higher
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return boolean Whether the record has been processed
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function info($message, array $context = [])
    {
        $result = false;
        if ($this->getLogLevel()>=3) {
            $result = $this->addRecord(static::INFO, $message, $context);
        }
        return $result;
    }

    /**
     * Logs debug messages to the Paymentsense log
     *
     * Does not depend on the Log Level configuration.
     * For debugging only. Do not use in production.
     *
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return boolean Whether the record has been processed
     */
    public function debug($message, array $context = [])
    {
        return $this->addRecord(static::DEBUG, $message, $context);
    }
}
