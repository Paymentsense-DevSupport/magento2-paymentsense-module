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

namespace Paymentsense\Payments\Model\Traits;

use Paymentsense\Payments\Model\Psgw\Psgw;
use Paymentsense\Payments\Model\Psgw\TransactionResultCode;

/**
 * Base method with module information
 */
trait BaseInfoMethod
{
    use BaseMethod;

    /**
     * Gets module information
     *
     * @param boolean $extendedInfoRequest
     *
     * @return array
     */
    public function getInfo($extendedInfoRequest)
    {
        $info = [
            'Module Name'              => $this->getModuleName(),
            'Module Installed Version' => $this->getModuleInstalledVersion(),
        ];
        if ($extendedInfoRequest) {
            $extendedInfo = [
                'Module Latest Version'     => $this->getModuleLatestVersion(),
                'Magento Version'           => $this->getMagentoVersion(),
                'PHP Version'               => $this->getPHPVersion(),
                'Connectivity on port 4430' => $this->getConnectivityStatus()
            ];
            $info = array_merge($info, $extendedInfo);
        }
        return $info;
    }

    /**
     * Gets file checksums
     *
     * @param array $fileList
     * @param string $scope
     *
     * @return array
     */
    public function getChecksums($fileList, $scope)
    {
        return [
            'Checksums' => $this->getFileChecksums($fileList, $scope)
        ];
    }

    /**
     * Converts an array to string
     *
     * @param array  $arr An associative array.
     * @param string $ident Identation.
     * @return string
     */
    public function convertArrayToString($arr, $ident = '')
    {
        $result       = '';
        $identPattern = '  ';
        foreach ($arr as $key => $value) {
            if ('' !== $result) {
                $result .= PHP_EOL;
            }

            if (is_array($value)) {
                $value = PHP_EOL . $this->convertArrayToString($value, $ident . $identPattern);
            }

            $result .= $ident . $key . ': ' . $value;
        }
        return $result;
    }

    /**
     * Gets module name
     *
     * @return string
     */
    private function getModuleName()
    {
        return $this->getConfigHelper()->getModuleName();
    }

    /**
     * Gets module HTTP user agent
     * Used for performing cURL requests
     *
     * @return string
     */
    private function getUserAgent()
    {
        return $this->getConfigHelper()->getUserAgent();
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    private function getModuleInstalledVersion()
    {
        return $this->getConfigHelper()->getModuleInstalledVersion();
    }

    /**
     * Gets module latest version
     *
     * @return string
     */
    private function getModuleLatestVersion()
    {
        $result = 'N/A';

        $objectManager     = $this->getModuleHelper()->getObjectManager();
        $zendClientFactory = new \Magento\Framework\HTTP\ZendClientFactory($objectManager);
        $psgw              = new Psgw($zendClientFactory);

        $headers = [
            'User-Agent: ' . $this->getUserAgent(),
            'Content-Type: text/plain; charset=utf-8',
            'Accept: text/plain, */*',
            'Accept-Encoding: identity',
            'Connection: close'
        ];

        $data = [
            'url'     => 'https://api.github.com/repos/'.
                'Paymentsense-DevSupport/magento2-paymentsense-module/releases/latest',
            'method'  => 'GET',
            'headers' => $headers,
            'xml'     => ''
        ];

        try {
            $response = $psgw->executeHttpRequest($data);
            $responseBody = $response->getBody();
            if ($responseBody) {
                $jsonObject = json_decode($responseBody);
                if (is_object($jsonObject) && property_exists($jsonObject, 'tag_name')) {
                    // @codingStandardsIgnoreLine
                    $result = $jsonObject->tag_name;
                }
            }
        } catch (\Exception $e) {
            $result = 'N/A';
        }

        return $result;
    }

    /**
     * Gets Magento version
     *
     * @return string
     */
    private function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Gets PHP version
     *
     * @return string
     */
    private function getPHPVersion()
    {
        return phpversion();
    }

    /**
     * Gets the gateway connectivity status
     *
     * @return string
     */
    private function getConnectivityStatus()
    {
        $response = $this->performGetGatewayEntryPointsTxn();
        if (TransactionResultCode::SUCCESS === $response['StatusCode']) {
            $result = 'Successful';
        } else {
            $result = $response['Message'];
        }
        return $result;
    }

    /**
     * Gets file checksums
     *
     * @param array $fileList
     * @param string $scope
     *
     * @return array
     */
    private function getFileChecksums($fileList, $scope)
    {
        $result = false;
        if (is_array($fileList)) {
            switch ($scope) {
                case 'module':
                    $directory = $this->getModuleDirectory();
                    break;
                case 'platform':
                    $directory = $this->getPlatformDirectory();
                    break;
                default:
                    $directory = false;
                    break;
            }
            if (!empty($directory)) {
                $result = [];
                foreach ($fileList as $key => $filename) {
                    $file = $directory . '/' . $filename;
                    // @codingStandardsIgnoreLine
                    $result[$key] = is_file($file)
                        ? sha1_file($file)
                        : null;
                }
            }
        }
        return $result;
    }

    /**
     * Gets module directory
     *
     * @return string
     */
    private function getModuleDirectory()
    {
        return $this->moduleReader->getModuleDir('', 'Paymentsense_Payments');
    }

    /**
     * Gets platform directory
     *
     * @return string
     */
    private function getPlatformDirectory()
    {
        $objectManager = $this->getModuleHelper()->getObjectManager();
        $directoryList = $objectManager->get('\Magento\Framework\App\Filesystem\DirectoryList');
        return $directoryList->getPath('base');
    }
}
