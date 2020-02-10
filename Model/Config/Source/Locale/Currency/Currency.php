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

namespace Paymentsense\Payments\Model\Config\Source\Locale\Currency;

/**
 * Currency source model
 */
class Currency extends \Magento\Config\Model\Config\Source\Locale\Currency
{
    /**
     * @var \Paymentsense\Payments\Helper\Data
     */
    protected $_moduleHelper;

    /**
     * @param \Magento\Framework\Locale\ListsInterface $localeLists
     * @param \Paymentsense\Payments\Helper\Data $moduleHelper
     */
    public function __construct(
        \Magento\Framework\Locale\ListsInterface $localeLists,
        \Paymentsense\Payments\Helper\Data $moduleHelper
    ) {
        parent::__construct($localeLists);
        $this->_moduleHelper = $moduleHelper;
    }

    /**
     * Gets an instance of the Module Helper
     *
     * @return \Paymentsense\Payments\Helper\Data
     */
    protected function getModuleHelper()
    {
        return $this->_moduleHelper;
    }

    /**
     * Builds the options array for the multiselect control in the admin panel
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = parent::toOptionArray();
        return $this->getModuleHelper()->getGloballyAllowedCurrenciesOptions($options);
    }
}
