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

namespace Paymentsense\Payments\Controller;

/**
 * Conditional inclusion of the proper CsrfAwareAction class based on the existence of the CsrfAwareActionInterface
 * interface and the PHP version
 *
 * Magento versions 2.3 and above use the CsrfAwareActionWithCsrfSupport.php while earlier Magento versions use the
 * CsrfAwareActionWithoutCsrfSupport.php file
 */
if (interface_exists('\Magento\Framework\App\CsrfAwareActionInterface')
    && version_compare(phpversion(), '7.1', '>=')
) {
    // @codingStandardsIgnoreLine
    require_once __DIR__ . '/CsrfAwareActionWithCsrfSupport.php';
} else {
    // @codingStandardsIgnoreLine
    require_once __DIR__ . '/CsrfAwareActionWithoutCsrfSupport.php';
}
