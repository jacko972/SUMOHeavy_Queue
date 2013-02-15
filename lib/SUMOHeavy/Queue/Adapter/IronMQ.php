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
    /**
     * IronMQ Projects URI
     */
    const PROJECTS_URI = 'http://mq-aws-us-east-1.iron.io/1/projects';

    /**
     * Get a http client instance
     *
     * @param string $path
     * @return Zend_Http_Client
     */
    protected function prepareHttpClient($path)
    {
        return $this->getHttpClient()
                    ->setUri(self::PROJECTS_URI . $path);
    }

    /**
     * Parse response object and check for errors
     *
     * @param Zend_Http_Response $response
     * @throws RuntimeException
     * @return stdClass
     */
    protected function _parseResponse(Zend_Http_Response $response)
    {
        if ($response->isError()) {
            switch ($response->getStatus()) {
                case 400:
                    throw new RuntimeException(
                        'Bad Request: Invalid JSON '
                        . '(can\'t be parsed or has wrong types).'
                    );
                case 401:
                    throw new RuntimeException(
                        'Unauthorized: '
                        . 'The OAuth token is either not provided or invalid'
                    );
                    break;
                case 404:
                    throw new RuntimeException(
                        'Not Found: The resource, project, '
                        . 'or endpoint being requested doesn\'t exist.'
                    );
                case 405:
                    throw new RuntimeException(
                        'Invalid HTTP method: A GET, POST, DELETE, or PUT '
                        . 'was sent to an endpoint that doesn\'t'
                        . 'support that particular verb.'

                    );
                case 500:
                    throw new RuntimeException(
                        'Not Acceptable: Required fields are missing.'
                    );
                case 503:
                    throw new RuntimeException(
                        'Service Unavailable. '
                        . 'Clients should implement exponential backoff '
                        . 'to retry the request.'
                    );
                    break;
                default:
                    throw new RuntimeException(
                        'Unknown error during request to IronMQ server'
                    );
            }
        }

        return Zend_Json::decode($response->getBody());
    }

    /**
     * Returns http client object
     *
     * @return Zend_Http_Client
     */
    public function getHttpClient()
    {
        if (null === $this->_client) {
            $this->_client = new Zend_Http_Client();

            $headers = array(
                'Content-Type' => 'application/json',
                'Authorization' => "OAuth {$this->_options['token']}",
            );
            $this->_client->setMethod(Zend_Http_Client::GET)
                ->setHeaders($headers);
        }

        return $this->_client;
    }

    private function _inArrayR($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $item) {
            if (
                (
                    $strict ? $item === $needle : $item == $needle)
                        || (is_array($item)
                            && $this->_inArrayR($needle, $item, $strict)
                )
            ) {
                return true;
            }
        }

        return false;
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
        if (empty($this->_queues)) {
            $this->getQueues();
        }

        $exists = $this->_inArrayR($name, $this->_queues) ? true : false;
        return $exists;
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
        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$name}"
        )
            ->setMethod(Zend_Http_Client::DELETE)
            ->request();

        return $this->_parseResponse($response);
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
        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues"
        )
            ->setMethod(Zend_Http_Client::GET)
            ->request();

        return $this->_parseResponse($response);
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

        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$queueName}"
        )
            ->setMethod(Zend_Http_Client::GET)
            ->request();

        $responseBody = $this->_parseResponse($response);
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

        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$queueName}/messages"
        )
            ->setMethod(Zend_Http_Client::POST)
            ->setRawData(Zend_Json::encode($messageArray))
            ->request();

        return $this->_parseResponse($response);
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

        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$queueName}/messages"
        )
            ->setParameterGet('n', $maxMessages)
            ->setParameterGet('timeout', $timeout)
            ->setMethod(Zend_Http_Client::GET)
            ->request();

        $responseBody = $this->_parseResponse($response);
        $messages = $responseBody['messages'];


        $options = array (
          'queue'   => $queue,
          'data'    => $messages,
        );

        return new Zend_Queue_Message($options);
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
        $queueName = $this->getQueue()->getOption('name');
        $messages = $message->toArray();
        foreach ($messages as $message) {
            $messageId = $message['id'];
            $response = $this->prepareHttpClient(
                "/{$this->_options['project_id']}/queues/"
                . "{$queueName}/messages/{$messageId}"
            )
                ->setMethod(Zend_Http_Client::DELETE)
                ->request();
            $this->_parseResponse($response);
        }

        return true;
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
