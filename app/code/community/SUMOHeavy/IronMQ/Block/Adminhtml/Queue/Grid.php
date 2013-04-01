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
class SUMOHeavy_IronMQ_Block_Adminhtml_Queue_Grid
    extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('queueGrid');
        $this->setDefaultSort('queue_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * Get available queues (marked in admin) into a collection
     * @return this
     */
    protected function _prepareCollection()
    {
        $queueModel = Mage::getModel('sumoheavy_ironmq/queue');
        $queues = $queueModel->getQueues();

        $collection = new Varien_Data_Collection();
        foreach ($queues as $queue) {
            $data = new Varien_Object();
            $data->setQueue($queue);
            $data->setCount($queueModel->count($queue));
            $collection->addItem($data);
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'queue', array(
                'header'    => Mage::helper('sumoheavy_ironmq')->__('Queue'),
                'align'     => 'left',
                'width'     => '700px',
                'index'     => 'queue',
            )
        );

        $this->addColumn(
            'count', array(
                'header'    => Mage::helper('sumoheavy_ironmq')->__('Message Count'),
                'align'     => 'right',
                'width'     => '50px',
                'index'     => 'count'
            )
        );

        return parent::_prepareColumns();
    }

    /**
     * @return SUMOHeavy_FlexSlider_Block_Adminhtml_Slideshow_Grid
     */
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('queue');
        $this->getMassactionBlock()->setFormFieldName('queue');

        $this->getMassactionBlock()->addItem(
            'delete', array(
                'label'     => Mage::helper('sumoheavy_ironmq')->__('Delete'),
                'url'       => $this->getUrl('*/*/massDelete'),
                'confirm'   => Mage::helper('sumoheavy_ironmq')
                                ->__('Are you sure?'),
            )
        );

        return $this;
    }
}