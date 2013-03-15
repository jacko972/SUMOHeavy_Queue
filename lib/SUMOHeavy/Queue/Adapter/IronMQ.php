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
     * Find a string in a multi-dimensional array
     *
     * @param $needle
     * @param $haystack
     * @param bool $strict
     * @return bool
     */
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

    /**re
     * List Message Queues
     *
     * Get a list of all queues in a project.
     * By default, 30 queues are listed at a time.
     * To see more, use the page parameter or the per_page parameter.
     * Up to 100 queues may be listed on a single page.
     *
     * @param integer|null $perPage
     * @param integer|null $page
     * @return array
     */
    public function getQueues($page = null, $perPage = null)
    {
        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues"
        )
            ->setMethod(Zend_Http_Client::GET)
            ->setParameterGet('page', $page)
            ->setParameterGet('per_page', $perPage)
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
     * Add Messages to a Queue
     *
     * This call adds or pushes messages onto the queue.
     *
     * Query options include:
     *
     * 'timeout'    => (int)
     *      optional - After timeout (in seconds), item will be placed
     *      back onto queue. You must delete the message from the queue
     *      to ensure it does not go back onto the queue.
     *      Default is 60 seconds. Maximum is 86,400 seconds (24 hours).
     *
     * 'delay'      => (int)
     *      optional - The item will not be available on the queue until
     *      this many seconds have passed.
     *      Default is 0 seconds. Maximum is 604,800 seconds (7 days).
     *
     * 'expiresIn'  => (int)
     *      optional - How long in seconds to keep the item on the queue
     *      before it is deleted.
     *      Default is 604,800 seconds (7 days).
     *      Maximum is 2,592,000 seconds (30 days).
     *
     * @param   mixed $message Message to send to the active queue
     * @param   Zend_Queue|null $queue
     * @param   null $timeout
     * @param   null $delay
     * @param   null $expiresIn
     * @return  Zend_Queue_Message
     */
    public function send(
        $message, Zend_Queue $queue = null, $timeout = null,
            $delay = null, $expiresIn = null)
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
            ->setParameterGet('timeout', $timeout)
            ->setParameterGet('delay', $delay)
            ->setParameterGet('expires_in', $expiresIn)
            ->setMethod(Zend_Http_Client::POST)
            ->setRawData(Zend_Json::encode($messageArray))
            ->request();

        return $this->_parseResponse($response);
    }

    /**
     * Get Messages from a Queue
     *
     * This call gets/reserves messages from the queue. The messages
     * will not be deleted, but will be reserved until the timeout expires.
     * If the timeout expires before the messages are deleted, the messages
     * will be placed back onto the queue. As a result, be sure to delete
     * the messages after you’re done with them.
     *
     * 'maxMessages'    => (int)
     *      optional - The maximum number of messages to get.
     *      Default is 1. Maximum is 100.
     *
     * 'timeout'        => (int)
     *      optional - After timeout (in seconds), item will be
     *      placed back onto queue. You must delete the message
     *      from the queue to ensure it does not go back onto the queue.
     *      If not set, value from POST is used.
     *      Default is 60 seconds, maximum is 86,400 seconds (24 hours).
     *
     * @param  integer|null $maxMessages Maximum number of messages to return
     * @param  integer|null $timeout Visibility timeout for these messages
     * @param  Zend_Queue|null $queue
     * @return Zend_Queue_Message
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
     * Delete a Message from a queue
     *
     * This call will delete the message.
     * Be sure you call this after you’re done with a message
     * or it will be placed back on the queue.
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
     * This allows you to change the properties of a queue
     * including setting subscribers and
     * the push type if you want it to be a push queue.
     *
     * Query options include:
     *
     * 'subscribers'    => (url)
     *      optional - An array of subscriber hashes containing a "url" field.
     *      This set of subscribers will replace the existing subscribers.
     *      To add or remove subscribers, see the add subscribers endpoint
     *      or the remove subscribers endpoint.
     *
     * 'pushType'       => (multicast|unicast)
     *      optional - Either multicast to push to all subscribers
     *      or unicast to push to one and only one subscriber.
     *      Default is “multicast”.
     *
     * 'retries'        => (int)
     *      optional - How many times to retry on failure. Default is 3.
     *
     * 'retriesDelay'   => (int)
     *      optional - Delay between each retry in seconds. Default is 60.
     *
     * @param array     $subscribers
     * @param string    $pushType
     * @param integer   $retries
     * @param integer   $retriesDelay
     * @return \stdClass
     */
    public function updateQueue(
        array $subscribers = null, $pushType = null,
            $retries = null, $retriesDelay = null)
    {
        if (!isset($queue)) {
            $queueName = $this->getQueue()->getName();
        } else {
            $queueName = $queue->getName();
        }

        $urls = array();
        foreach ($subscribers as $subscriber) {
            $urls[] = array (
                'url' => $subscriber
            );
        }

        $postData = array (
            'subscribers'    => $urls,
            'push_type'     => $pushType,
            'retries'       => $retries,
            'retries_delay' => $retriesDelay
        );

        $postDataJson = Zend_Json::encode($postData);

        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$queueName}"
        )
            ->setMethod(Zend_Http_Client::POST)
            ->setRawData($postDataJson)
            ->request();

        var_dump($postDataJson);
        return $this->_parseResponse($response);
    }

    /**
     * Add subscribers (HTTP endpoints) to a queue.
     * This is for Push Queues only.
     *
     * Query options include:
     *
     * 'subscribers'    => (url)
     *      optional - An array of subscriber hashes containing a "url" field.
     *
     * @param array $subscribers
     * @return \stdClass
     */
    public function addSubscribersToQueue(array $subscribers = null)
    {
        if (!isset($queue)) {
            $queueName = $this->getQueue()->getName();
        } else {
            $queueName = $queue->getName();
        }

        $urls = array();
        foreach ($subscribers as $subscriber) {
            $urls[] = array (
                'url' => $subscriber
            );
        }

        $subscriberArray = array (
            'subscribers' => $urls
        );

        $subscribersJson = Zend_Json::encode($subscriberArray);

        $response = $this->prepareHttpClient(
            "/{$this->_options['project_id']}/queues/{$queueName}/subscribers"
        )
            ->setMethod(Zend_Http_Client::PUT)
            ->setRawData($subscribersJson)
            ->request();

        var_dump($subscribersJson);

        return $this->_parseResponse($response);
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
