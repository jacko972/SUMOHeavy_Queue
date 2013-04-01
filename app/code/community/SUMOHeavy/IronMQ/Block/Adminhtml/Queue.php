<?php
/**
 * IronMQ
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@sumoheavy.com so we can send you a copy immediately.
 *
 *
 * @category    SUMOHeavy
 * @package     SUMOHeavy_IronMQ
 * @author      Robert Brodie <robert@sumoheavy.com>
 * @copyright   Copyright (c) 2010 - 2013 SUMO Heavy Industries, LLC
 * @license     http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class SUMOHeavy_IronMQ_Block_Adminhtml_Queue
    extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_queue';
        $this->_blockGroup = 'sumoheavy_ironmq';
        $this->_headerText = Mage::helper('sumoheavy_ironmq')->__('Manage Queues');

        parent::__construct();
        $this->removeButton('add');
    }
}