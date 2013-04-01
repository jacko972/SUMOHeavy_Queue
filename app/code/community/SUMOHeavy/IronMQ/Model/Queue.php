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
class SUMOHeavy_IronMQ_Model_Queue extends Mage_Core_Model_Abstract
{
    /**
     * Queues available to Magento
     * @var array
     */
    private $_queues = array();

    private $_token;

    private $_projectId;

    public function __construct()
    {
        $this->_setup();
        $this->_getAvailableQueues();
    }

    private function _getAvailableQueues()
    {
        /**
         * Get selected queues from the admin
         * @var string
         */
        $queueList = Mage::getStoreConfig('ironmq/general/queues');

        /**
         * Get all queues
         * @var array
         */
        $queues = Mage::getModel('sumoheavy_ironmq/system_config_source_queue')->toOptionArray();

        /**
         * Explode queues into an array (they're comma-separated)
         * @var array
         */
        $allowedQueueIds = explode(',', $queueList);

        /**
         * Create an empty queue to store allowed queues in
         * @var array
         */
        $allowedQueues = array();

        // Get the names for allowed queues
        foreach ($queues as $queue) {
            if (in_array($queue['value'], $allowedQueueIds)) {
                $allowedQueues[] = $queue['label'];
            }
        }

        $this->setQueues($allowedQueues);
    }

    private function _setup($token = null)
    {
        $this->_token = Mage::getStoreConfig('ironmq/general/token');
        $this->_projectId = Mage::getStoreConfig('ironmq/general/project_id');
    }

    private function _getToken()
    {
        return $this->_token;
    }

    private function _getProjectId()
    {
        return $this->_projectId;
    }

    private function _isQueueAvailable($queueName)
    {
        if (in_array($queueName, $this->_queues)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set queues
     * @param array $queues
     */
    public function setQueues(array $queues)
    {
        $this->_queues = $queues;
    }

    /**
     * Get available queues
     * @return array
     */
    public function getQueues()
    {
        return $this->_queues;
    }

    /**
     * Get an individual queue
     * @param $queueName
     * @return SUMOHeavy_Queue_Adapter_IronMQ
     */
    private function _getQueue($queueName)
    {
        $token = $this->_getToken();
        $projectId = $this->_getProjectId();

        $queueOptions = array(
            'name' => $queueName
        );

        $zendQueue = new Zend_Queue('Array', $queueOptions);

        $options = array(
            'token' => $token,
            'project_id' => $projectId
        );

        $queue = new SUMOHeavy_Queue_Adapter_IronMQ($options, $zendQueue);

        return $queue;
    }

    /**
     * Get the number of messages in a queue
     * @param $queueName
     * @return int
     */
    public function count($queueName)
    {
        if ($this->_isQueueAvailable($queueName)) {
            $queue = $this->_getQueue($queueName);
            return $queue->count();
        } else {
            Mage::log("Queue {$queueName} is not available to Magento.");
        }
    }

    /**
     * Send a message to a queue
     * @param $queueName
     * @param $message
     * @return int
     */
    public function send($queueName, $message)
    {
        if ($this->_isQueueAvailable($queueName)) {
            $queue = $this->_getQueue($queueName);
            $queue->send($message);
            return true;
        } else {
            Mage::log("Queue {$queueName} is not available to Magento.");
            return false;
        }
    }

    /**
     * Receive messages from queue
     * @param $queueName
     * @param null $maxMessages
     * @param null $timeout
     * @return bool
     */
    public function receive($queueName, $maxMessages = null, $timeout = null)
    {
        if ($this->_isQueueAvailable($queueName)) {
            $queue = $this->_getQueue($queueName);
            $queue->receive($maxMessages, $timeout, $queueName);
            return true;
        } else {
            Mage::log("Queue {$queueName} is not available to Magento.");
            return false;
        }
    }
}