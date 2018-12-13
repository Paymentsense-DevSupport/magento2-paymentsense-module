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

namespace Paymentsense\Payments\Controller\Info;

/**
 * Handles the module information request
 *
 * @package Paymentsense\Payments\Controller\Info
 */
class Index extends \Paymentsense\Payments\Controller\CsrfAwareAction
{
    const MODULE_NAME = 'Paymentsense Module for Magento 2 Open Source';

    const TYPE_APPLICATION_JSON = 'application/json';
    const TYPE_TEXT_PLAIN       = 'text/plain';

    /**
     * Supported content types of the output of the module information
     *
     * @var array
     */
    protected $contentTypes = [
        'json' => self::TYPE_APPLICATION_JSON,
        'text' => self::TYPE_TEXT_PLAIN
    ];

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Module\ModuleListInterface
     * @param \Magento\Framework\App\ProductMetadataInterface
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleListInterface,
        \Magento\Framework\App\ProductMetadataInterface $productMetadataInterface
    ) {
        $this->moduleList      = $moduleListInterface;
        $this->productMetadata = $productMetadataInterface;
        parent::__construct($context, $logger);
    }

    /**
     * Handles the request
     */
    public function execute()
    {
        $info = [
            'Module Name'              => $this->getModuleName(),
            'Module Installed Version' => $this->getModuleInstalledVersion(),
            'Magento Version'          => $this->getMagentoVersion(),
            'PHP Version'              => $this->getPHPVersion()
        ];
        $this->outputInfo($info);
    }

    /**
     * Outputs module information
     *
     * @param array $info
     */
    private function outputInfo($info)
    {
        $output = $this->getRequest()->getParam('output');
        $contentType = array_key_exists($output, $this->contentTypes)
            ? $this->contentTypes[$output]
            : self::TYPE_TEXT_PLAIN;

        switch ($contentType) {
            case self::TYPE_APPLICATION_JSON:
                $body = json_encode($info);
                break;
            case self::TYPE_TEXT_PLAIN:
            default:
                $body = $this->convertArrayToString($info);
                break;
        }

        $this->getResponse()
            ->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Content-Type', $contentType)
            ->setBody($body);
    }

    /**
     * Converts an array to string
     *
     * @param array $arr
     * @return string
     */
    private function convertArrayToString($arr)
    {
        $result = '';
        foreach ($arr as $key => $value) {
            if ($result !== '') {
                $result .= PHP_EOL;
            }
            $result .= $key . ': ' . $value;
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
        return self::MODULE_NAME;
    }

    /**
     * Gets module installed version
     *
     * @return string
     */
    private function getModuleInstalledVersion()
    {
        return $this->moduleList->getOne('Paymentsense_Payments')['setup_version'];
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
}
