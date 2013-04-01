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
class SUMOHeavy_IronMQ_Adminhtml_QueueController
    extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction()
    {
        $this->_title($this->__('Manage Queue'))->_title($this->__('Queues'));

        $this->loadLayout()
            ->_setActiveMenu('system')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Manage Queue'),
                Mage::helper('adminhtml')->__('Manage Queue')
            );

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }

    /**
     * Delete multiple queues
     */
    public function massDeleteAction()
    {
        $queueNames = $this->getRequest()->getParam('queue');
        if (!is_array($queueNames)) {
            Mage::getSingleton('adminhtml/session')
                ->addError(
                    Mage::helper('sumoheavy_ironmq')->__('Please select queue(s)')
                );
        } else {
            try {
                foreach ($queueNames as $queueName) {
                    $token = Mage::getStoreConfig('ironmq/general/token');
                    $projectId = Mage::getStoreConfig('ironmq/general/project_id');

                    $queueOptions = array(
                        'name' => $queueName
                    );

                    $zendQueue = new Zend_Queue('Array', $queueOptions);

                    $options = array(
                        'token' => $token,
                        'project_id' => $projectId
                    );

                    $queue = new SUMOHeavy_Queue_Adapter_IronMQ($options, $zendQueue);
                    $queue->delete($queueName);

                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d queue(s) were deleted.',
                        count($queueNames)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')
                    ->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }
}