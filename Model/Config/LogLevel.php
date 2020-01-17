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

namespace Paymentsense\Payments\Model\Config;

/**
 * Log Level Model Source
 *
 * @package Paymentsense\Payments\Model\Config
 */
class LogLevel implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Builds the options for the select control in the admin panel
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '1', 'label' => 'Error messages'],
            ['value' => '2', 'label' => 'Error and warning messages'],
            ['value' => '3', 'label' => 'Error, warnings and info messages']
        ];
    }
}
