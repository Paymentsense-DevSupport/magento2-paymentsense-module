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

namespace Paymentsense\Payments\Controller\Hosted;

/**
 * Handles the module information request
 *
 * @package Paymentsense\Payments\Controller\Hosted
 */
class Info extends \Paymentsense\Payments\Controller\InfoAction
{
    /**
     * @var \Paymentsense\Payments\Model\Method\Hosted
     */
    protected $method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Paymentsense\Payments\Model\Method\Hosted $method
     */
    // @codingStandardsIgnoreStart
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Paymentsense\Payments\Model\Method\Hosted $method
    ) {
        parent::__construct($context, $logger, $method);
    }
    // @codingStandardsIgnoreEnd
}
