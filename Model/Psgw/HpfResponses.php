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

namespace Paymentsense\Payments\Model\Psgw;

/**
 * Hosted Payment Form Responses
 */
final class HpfResponses
{
    const HPF_RESP_OK             = 'OK';
    const HPF_RESP_HASH_INVALID   = 'HashDigest does not match';
    const HPF_RESP_MID_MISSING    = 'MerchantID is missing';
    const HPF_RESP_MID_NOT_EXISTS = 'Merchant doesn\'t exist';
    const HPF_RESP_NO_RESPONSE    = '';
}
