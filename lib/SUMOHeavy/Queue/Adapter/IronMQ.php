<?php
/**
 * IronMQ Adapter
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
 * Notice: The MailGun logo and name are trademarks of MailGun, Inc
 *
 * @category    SUMOHeavy
 * @package     SUMOHeavy_Queue
 * @author      Robert Brodie <robert@sumoheavy.com>
 * @copyright   Copyright (c) 2010 - 2013 SUMO Heavy Industries, LLC
 * @license     http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class SUMOHeavy_Queue_Adapter_IronMQ
    extends Zend_Queue_Adapter_AdapterAbstract
{
    CONST projects_uri = 'http://mq-aws-us-east-1.iron.io/1/projects';

    private function _setHeaders()
    {
        $headers = array(
            'Authorization' => "OAuth {$this->_options['token']}",
            'Content-Type' => 'application/json',
        );

        return $headers;
    }

    /**
     * Does a queue already exist?
     *
     * Use isSupported('isExists') to determine if an adapter can test for
     * queue existance.
     *
     * @param  string $name Queue name
     * @return boolean
     */
    public function isExists($name)
    {
        var_dump($this->getQueues());

        if (empty($this->_queues)) {
            $this->getQueues();
        }

        return in_array($name, $this->_queues);
    }

    /**
     * Create a new queue
     *
     * Visibility timeout is how long a message is left in the queue
     * "invisible" to other readers.  If the message is acknowleged (deleted)
     * before the timeout, then the message is deleted.  However, if the
     * timeout expires then the message will be made available to other queue
     * readers.
     *
     * @param  string  $name Queue name
     * @param  integer $timeout Default visibility timeout
     * @throws Zend_Queue_Exception
     * @return boolean
     */
    public function create($name, $timeout = null)
    {
        throw new Zend_Queue_Exception(
            'create() is not supported in, '
            . get_class($this)
            . ' use send() to create a queue'
        );
    }

    /**
     * Delete a queue and all of its messages
     *
     * Return false if the queue is not found, true if the queue exists.
     *
     * @param  string $name Queue name
     * @return boolean
     */
    public function delete($name)
    {
        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues/{$name}"
        );
        $client->setHeaders($this->_setHeaders());
        $client->request("DELETE");
    }

    /**
     * Get an array of all available queues
     *
     * Not all adapters support getQueues(); use isSupported('getQueues')
     * to determine if the adapter supports this feature.
     *
     * @return array
     */
    public function getQueues()
    {
        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues"
        );
        $client->setHeaders($this->_setHeaders());
        $response = $client->request("GET");
        return Zend_Json::decode($response->getBody());
    }

    /**
     * Return the approximate number of messages in the queue
     *
     * @param  Zend_Queue|null $queue
     * @return integer
     */
    public function count(Zend_Queue $queue = null)
    {
        if (!isset($queue)) {
            $queueName = $this->getQueue()->getName();
        } else {
            $queueName = $queue->getName();
        }

        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues/{$queueName}"
        );
        $client->setHeaders($this->_setHeaders());
        $response = $client->request("GET");
        $responseBody = Zend_Json::decode($response->getBody());
        return $responseBody['size'];
    }

    /**
     * Send a message to the queue
     *
     * @param  mixed $message Message to send to the active queue
     * @param  Zend_Queue|null $queue
     * @return Zend_Queue_Message
     */
    public function send($message, Zend_Queue $queue = null)
    {
        if (!isset($queue)) {
            $queueName = $this->getQueue()->getName();
        } else {
            $queueName = $queue->getName();
        }

        $messageArray = array (
            'messages' => array (
                array (
                    'body' => $message
                )
            )
        );
        $jsonMessage = Zend_Json::encode($messageArray);

        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues/{$queueName}/messages"
        );
        $client->setHeaders($this->_setHeaders());
        $client->setRawData($jsonMessage);
        $client->request("POST");
    }

    /**
     * Get messages in the queue
     *
     * @param  integer|null $maxMessages Maximum number of messages to return
     * @param  integer|null $timeout Visibility timeout for these messages
     * @param  Zend_Queue|null $queue
     * @return Zend_Queue_Message_Iterator
     */
    public function receive(
        $maxMessages = null, $timeout = null, Zend_Queue $queue = null)
    {
        if (!isset($queue)) {
            $queueName = $this->getQueue()->getName();
        } else {
            $queueName = $queue->getName();
        }

        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues/{$queueName}/messages"
        );
        $client->setHeaders($this->_setHeaders());

        if (isset($maxMessages)) {
            $client->setParameterGet('n', $maxMessages);
        }

        if (isset($timeout)) {
            $client->setParameterGet('timeout', $timeout);
        }

        $response = $client->request("GET");
        return Zend_Json::decode($response->getBody());
    }

    /**
     * Delete a message from the queue
     *
     * Return true if the message is deleted, false if the deletion is
     * unsuccessful.
     *
     * @param  Zend_Queue_Message $message
     * @return boolean
     */
    public function deleteMessage(Zend_Queue_Message $message)
    {
        $adapter = new Zend_Http_Client_Adapter_Curl();
        $client = new Zend_Http_Client();
        $client->setAdapter($adapter);
        $client->setUri(
            self::projects_uri
            . "/{$this->_options['project_id']}/queues/"
            . "{$queueName}/messages/{$messageId}"
        );
        $client->setHeaders($this->_setHeaders());
        $client->request("DELETE");
    }

    /**
     * Return a list of queue capabilities functions
     *
     * $array['function name'] = true or false
     * true is supported, false is not supported.
     *
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'create'        => false,
            'delete'        => true,
            'send'          => true,
            'receive'       => true,
            'deleteMessage' => true,
            'getQueues'     => true,
            'count'         => true,
            'isExists'      => true,
        );
    }
}
