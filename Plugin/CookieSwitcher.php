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

namespace Paymentsense\Payments\Plugin;

use Magento\Framework\Stdlib\Cookie\PhpCookieManager;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;

/**
 * CookieSwitcher class used for switching the "samesite" cookie attribute for eligible cookies to None
 */
class CookieSwitcher
{
    /**
     * Eligible cookies for switching
     */
    const COOKIES = [
        'PHPSESSID',
        'form_key',
        'private_content_version',
        'X-Magento-Vary'
    ];

    /**
     * Action running before the SetPublicCookie method of the Magento PhpCookieManager class
     *
     * @param PhpCookieManager          $phpCookieManager
     * @param string                    $cookieName
     * @param string                    $cookieValue
     * @param PublicCookieMetadata|null $publicCookieMetadata
     *
     * @return array
     */
    public function beforeSetPublicCookie(
        PhpCookieManager $phpCookieManager,
        $cookieName,
        $cookieValue,
        PublicCookieMetadata $publicCookieMetadata = null
    ) {
        if ($this->isCookieEligible($cookieName) &&
            $this->isSwitchSupported($publicCookieMetadata) &&
            $this->isCookieSecure($publicCookieMetadata)
        ) {
            $this->switchCookie($publicCookieMetadata);
        }

        return [
            $cookieName,
            $cookieValue,
            $publicCookieMetadata
        ];
    }

    /**
     * Determines whether the cookie is eligible for switching
     *
     * @param string $cookieName Cookie key
     *
     * @return bool
     */
    private function isCookieEligible($cookieName)
    {
        return in_array($cookieName, self::COOKIES);
    }

    /**
     * Determines whether the methods required for the switch are available in the Magento PublicCookieMetadata class
     *
     * @param PublicCookieMetadata $publicCookieMetadata
     *
     * @return bool
     */
    private function isSwitchSupported($publicCookieMetadata)
    {
        return is_callable([$publicCookieMetadata, 'setSameSite'])
            && is_callable([$publicCookieMetadata, 'getSecure']);
    }

    /**
     * Determines whether the cookie is only available under HTTPS (i.e. whether the "secure" cookie attribute is true)
     *
     * @param string $publicCookieMetadata Cookie name
     *
     * @return bool
     */
    private function isCookieSecure($publicCookieMetadata)
    {
        return $publicCookieMetadata->getSecure();
    }

    /**
     * Switches the "samesite" cookie attribute to None
     *
     * @param PublicCookieMetadata $publicCookieMetadata
     */
    private function switchCookie($publicCookieMetadata)
    {
        try {
            $publicCookieMetadata->setSameSite('None');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Swallows possible NoSuchEntityException exceptions. No action is required.
        }
    }
}
