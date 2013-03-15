# IronMQ Zend_Queue Adapter

This module allows the use of IronMQ as a Zend_Queue.

## Usage

```php
$options = array(
    'token' => 'YOUR-IRONMQ-TOKEN',
    'project_id' => 'YOUR-IRONMQ-PROJECT-ID'
);

$queueOptions = array(
    'name' => 'YOUR-IRONMQ-QUEUE'
);

$zendQueue = new Zend_Queue('Array', $queueOptions);
$queue = new SUMOHeavy_Queue_Adapter_IronMQ($options, $zendQueue);

$queue->setProjectUri('http://mq-aws-us-east-1.iron.io/1/custom-project-uri'); // Set a custom project URI

$queue->send("HELLO"); // Send a message to the queue
$queue->getQueues()); // Get the messages in a queue
$queue->receive(2)); // Receive 2 messages
$queue->delete('my_queue'); // Delete the my_queue queue
$queue->isExists('my_queue')); // Check if my_queue exists
$queue->deleteMessage($queue->receive(2))); // Receive 2 messages and delete them
$queue->count()); // Count messages in queue
$queue->updateQueue($subscribers)); // Update queue
$queue->addSubscribersToQueue($subscribers)); // Add subscribers to queue
```
