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

namespace Paymentsense\Payments\Model\Psgw;

/**
 * Data Builder used for building data for the transaction requests.
 *
 * Used for ISO codes and boolean values
 */
class DataBuilder extends IsoCodes
{
    /**
     * Converts boolean to string
     *
     * @param boolean $value
     * @return string
     */
    public static function getBool($value)
    {
        return $value ? 'true' : 'false';
    }
}
