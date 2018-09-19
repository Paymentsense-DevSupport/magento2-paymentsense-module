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

namespace Paymentsense\Payments\Helper;

/**
 * Logger
 *
 * Log files reside in the /var/log/paymentsense directory
 *
 * @package Paymentsense\Payments\Helper
 */
class Logger extends \Magento\Payment\Model\Method\Logger
{
    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $handler = new \Magento\Framework\Logger\Handler\Base(
            new \Magento\Framework\Filesystem\Driver\File(),
            BP . '/var/log/paymentsense/',
            $name . '.log'
        );
        $logger = new \Monolog\Logger($name, [$handler]);
        parent::__construct($logger);
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}
