<?php

/**
 * Example: create a topic + subscription, send a message, receive it with
 * peek-lock, then acknowledge it (delete) so it doesn't get redelivered.
 *
 * Run: php examples/01-send-and-receive-message.php
 * (requires AZURE_SERVICE_BUS_CONNECTION_STRING — see bootstrap.php)
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use MageTips\WindowsAzure\Common\ServiceException;
use MageTips\WindowsAzure\ServiceBus\Models\BrokeredMessage;
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMode;
use MageTips\WindowsAzure\ServiceBus\Models\RuleInfo;
use MageTips\WindowsAzure\ServiceBus\Models\SubscriptionInfo;
use MageTips\WindowsAzure\ServiceBus\Models\TopicInfo;

const TOPIC_NAME = 'examples-orders-export';
const SUBSCRIPTION_NAME = 'examples-defaultsub';

$serviceBus = example_service_bus();

// 1. Make sure the topic and subscription exist. Creating something that
//    already exists throws a ServiceException, so this is written to be safe
//    to run more than once.
try {
    $serviceBus->createTopic(new TopicInfo(TOPIC_NAME));
    echo 'Created topic "' . TOPIC_NAME . "\"\n";
} catch (ServiceException $e) {
    echo 'Topic "' . TOPIC_NAME . "\" already exists, continuing.\n";
}

try {
    $serviceBus->createSubscription(TOPIC_NAME, new SubscriptionInfo(SUBSCRIPTION_NAME));
    $serviceBus->createRule(TOPIC_NAME, SUBSCRIPTION_NAME, new RuleInfo('$Default'));
    echo 'Created subscription "' . SUBSCRIPTION_NAME . "\"\n";
} catch (ServiceException $e) {
    echo 'Subscription "' . SUBSCRIPTION_NAME . "\" already exists, continuing.\n";
}

// 2. Send a message. Dummy order payload — swap for whatever your application sends.
$orderPayload = json_encode([
    'orderId' => 100234,
    'customer' => 'Jane Doe',
    'total' => 129.99,
    'items' => [
        ['sku' => 'WIDGET-01', 'qty' => 2],
        ['sku' => 'GADGET-07', 'qty' => 1],
    ],
]);

$message = new BrokeredMessage($orderPayload);
$message->setContentType('application/json');
$message->setProperty('EventType', 'order.exported');

$serviceBus->sendTopicMessage(TOPIC_NAME, $message);
echo "Sent message: {$orderPayload}\n";

// 3. Receive it back with peek-lock (the mode that supports acknowledge/retry).
$options = new ReceiveMessageOptions();
$options->setReceiveMode(ReceiveMode::PEEK_LOCK);

$received = $serviceBus->receiveSubscriptionMessage(TOPIC_NAME, SUBSCRIPTION_NAME, $options);

if ($received === null) {
    echo "No message available (it may already have been consumed by another receiver).\n";
    exit(0);
}

$body = (string) $received->getBody();
echo "Received message (delivery #{$received->getDeliveryCount()}): {$body}\n";

// 4. Acknowledge it. Do this after successful processing; call unlockMessage()
//    instead if processing failed and the message should be retried.
$serviceBus->deleteMessage($received);
echo "Acknowledged (deleted) the message.\n";
