<?php

/**
 * Example: force a message into the dead-letter sub-queue by exceeding
 * MaxDeliveryCount, then read it back and requeue it onto the original topic.
 *
 * Run: php examples/02-dead-letter-handling.php
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
const SUBSCRIPTION_NAME = 'examples-flaky-sub';
const MAX_DELIVERY_COUNT = 2;

$serviceBus = example_service_bus();

// 1. Set up a topic + subscription with a low MaxDeliveryCount, so it's quick
//    to demonstrate auto-dead-lettering. In production this is usually 5-10.
try {
    $serviceBus->createTopic(new TopicInfo(TOPIC_NAME));
} catch (ServiceException $e) {
    // already exists
}

try {
    $subscriptionInfo = new SubscriptionInfo(SUBSCRIPTION_NAME);
    $subscriptionInfo->setMaxDeliveryCount(MAX_DELIVERY_COUNT);
    $serviceBus->createSubscription(TOPIC_NAME, $subscriptionInfo);
    $serviceBus->createRule(TOPIC_NAME, SUBSCRIPTION_NAME, new RuleInfo('$Default'));
    echo 'Created subscription "' . SUBSCRIPTION_NAME . '" with MaxDeliveryCount=' . MAX_DELIVERY_COUNT . "\n";
} catch (ServiceException $e) {
    echo 'Subscription "' . SUBSCRIPTION_NAME . "\" already exists, continuing.\n";
}

// 2. Send a message that we're going to deliberately fail to process.
$payload = json_encode(['orderId' => 100999, 'note' => 'this message will be dead-lettered']);
$message = new BrokeredMessage($payload);
$serviceBus->sendTopicMessage(TOPIC_NAME, $message);
echo "Sent message: {$payload}\n";

// 3. Simulate repeated processing failures: receive, then abandon (unlock)
//    the message each time, without deleting it. Once delivery count exceeds
//    MaxDeliveryCount, Azure moves it to the subscription's dead-letter
//    sub-queue automatically — no explicit "dead-letter this" call exists.
$options = new ReceiveMessageOptions();
$options->setReceiveMode(ReceiveMode::PEEK_LOCK);

for ($attempt = 1; $attempt <= MAX_DELIVERY_COUNT + 1; $attempt++) {
    $received = $serviceBus->receiveSubscriptionMessage(TOPIC_NAME, SUBSCRIPTION_NAME, $options);

    if ($received === null) {
        echo "Attempt {$attempt}: no message available (it has likely already been dead-lettered).\n";
        break;
    }

    echo "Attempt {$attempt}: received (delivery #{$received->getDeliveryCount()}), simulating failure -> unlocking.\n";
    $serviceBus->unlockMessage($received);
}

// 4. Read it back from the native dead-letter sub-queue.
$deadLetterSubscription = SUBSCRIPTION_NAME . '/$DeadLetterQueue';
$deadLetter = $serviceBus->receiveSubscriptionMessage(TOPIC_NAME, $deadLetterSubscription, $options);

if ($deadLetter === null) {
    echo "No dead-lettered message found yet (Azure may need a moment to process it — try re-running).\n";
    exit(0);
}

echo 'Found dead-lettered message: ' . (string) $deadLetter->getBody() . "\n";

// 5. Requeue it: publish a fresh copy onto the original topic, then remove the
//    dead-lettered copy. There's no "move" operation, so this is a copy + delete.
$requeued = new BrokeredMessage($deadLetter->getBody());
$serviceBus->sendTopicMessage(TOPIC_NAME, $requeued);
$serviceBus->deleteMessage($deadLetter);
echo "Requeued the message onto \"" . TOPIC_NAME . "\" and removed it from the dead-letter sub-queue.\n";
