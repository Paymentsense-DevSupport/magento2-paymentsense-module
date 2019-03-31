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
 * Logger
 *
 * Log files reside in the /var/log/paymentsense directory. Failback is the system log.
 *
 * @package Paymentsense\Payments\Helper
 */
class Logger extends \Magento\Payment\Model\Method\Logger
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param mixed $method
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct($method)
    {
        if ($method instanceof \Magento\Payment\Model\Method\AbstractMethod) {
            $name = $method->getCode();
            $handler = new \Magento\Framework\Logger\Handler\Base(
                new \Magento\Framework\Filesystem\Driver\File(),
                BP . '/var/log/paymentsense/',
                $name . '.log'
            );
            $logger = new PaymentsenseLogger($name, [$handler], [], $method);
        } else {
            // Failback
            $name = 'paymentsense';
            $handler = new \Magento\Framework\Logger\Handler\Base(
                new \Magento\Framework\Filesystem\Driver\File(),
                BP . '/var/log/system.log'
            );
            $logger = new \Monolog\Logger($name, [$handler]);
            $logger->error(
                'An error occurred while trying to initialise logger helper: Invalid payment method.'
            );
        }
        parent::__construct($logger);
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
