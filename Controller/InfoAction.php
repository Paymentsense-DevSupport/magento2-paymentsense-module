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

namespace Paymentsense\Payments\Controller;

/**
 * Abstract module information action class
 */
abstract class InfoAction extends ReportAction
{
    /**
     * Handles the module information request
     */
    public function execute()
    {
        $extendedInfoRequest = 'true' === $this->getRequest()->getParam('extended_info');
        $outputFormat        = $this->getRequest()->getParam('output');
        $info                = $this->method->getInfo($extendedInfoRequest);
        if ($extendedInfoRequest) {
            $settingsMessage     = $this->method->getSettingsMessage(true);
            $systemTimeStatus    = $this->method->getSystemTimeStatus();
            $settingsMessageInfo = [
                'System Time'              => $systemTimeStatus,
                'Gateway settings message' => $settingsMessage
            ];
            $info = array_merge($info, $settingsMessageInfo);
        }
        $this->outputInfo($info, $outputFormat);
    }
}
