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
 * Abstract action class defining actions and implementing a logger
 */
abstract class Action extends \Magento\Framework\App\Action\Action
{
    const ACSRESPONSE     = 'acsresponse';
    const THREEDSCANCEL   = '3dscancel';
    const THREEDSERROR    = '3dserror';
    const THREEDSCOMPLETE = '3dscomplete';

    /**
     * @var \Magento\Framework\App\Action\Context
     */
    protected $_context;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->_context = $context;
        $this->_logger = $logger;
    }

    /**
     * Gets an instance of the Magento Controller Action
     *
     * @return \Magento\Framework\App\Action\Context
     */
    protected function getContext()
    {
        return $this->_context;
    }

    /**
     * Gets an instance of the Magento Object Manager
     *
     * @return \Magento\Framework\ObjectManagerInterface
     */
    protected function getObjectManager()
    {
        return $this->_objectManager;
    }

    /**
     * Gets an instance of the Magento global Message Manager
     *
     * @return \Magento\Framework\Message\ManagerInterface
     */
    protected function getMessageManager()
    {
        return $this->getContext()->getMessageManager();
    }

    /**
     * Gets an instance of the Magento global Logger
     *
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        return $this->_logger;
    }

    /**
     * Gets an array of the Submitted POST HTTP Request
     *
     * @return array
     */
    protected function getPostData()
    {
        return $this->getRequest()->getPostValue();
    }

    /**
     * Gets an array of the Submitted Get HTTP Request
     *
     * @return array
     */
    protected function getQueryData()
    {
        return $this->getRequest()->getQueryValue();
    }
}
