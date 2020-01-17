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
abstract class ReportAction extends CsrfAwareAction
{
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
     * @var \Paymentsense\Payments\Model\Method\Hosted|\Paymentsense\Payments\Model\Method\Direct|\Paymentsense\Payments\Model\Method\Moto
     */
    protected $method;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Payment\Model\Method\AbstractMethod $method
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        $method
    ) {
        $this->method = $method;
        parent::__construct($context, $logger);
    }

    /**
     * Outputs module information
     *
     * @param array $info Module information
     * @param string $outputFormat Output format
     */
    protected function outputInfo($info, $outputFormat)
    {
        $contentType = array_key_exists($outputFormat, $this->contentTypes)
            ? $this->contentTypes[$outputFormat]
            : self::TYPE_TEXT_PLAIN;

        switch ($contentType) {
            case self::TYPE_APPLICATION_JSON:
                $body = json_encode($info);
                break;
            case self::TYPE_TEXT_PLAIN:
            default:
                $body = $this->method->convertArrayToString($info);
                break;
        }

        $this->getResponse()
            ->setHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store', true)
            ->setHeader('Pragma', 'no-cache', true)
            ->setHeader('Content-Type', $contentType)
            ->setBody($body);
    }
}
