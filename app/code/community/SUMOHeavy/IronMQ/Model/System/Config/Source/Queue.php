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
class SUMOHeavy_IronMQ_Model_System_Config_Source_Queue
{
    public function toOptionArray()
    {
        $queueList = array();
        $token = Mage::getStoreConfig('ironmq/general/token');
        $projectId = Mage::getStoreConfig('ironmq/general/project_id');

        if ($token && $projectId) {
            $options = array(
                'token' => $token,
                'project_id' => $projectId
            );

            try {
                $queueAdapter = new SUMOHeavy_Queue_Adapter_IronMQ($options);
                $queues = $queueAdapter->getQueues();
            } catch (Exception $e) {
                Mage::logException($e);
            }

            $c = 0;
            foreach ($queues as $queue) {
                $queueList[] = array(
                    'value' => $c,
                    'label' => $queue['name']
                );

                $c++;
            }
        }

        return $queueList;
    }
}