# magetips/windowsazure

A PHP client library for [Azure Service Bus](https://azure.microsoft.com/en-us/products/service-bus).
It manages namespace topology (topics, subscriptions, rules) and message operations (send, peek-lock
receive, unlock, renew lock, delete) over the Service Bus REST API, authenticating via Shared Access
Signature (SAS).

Framework-agnostic — no dependency on Magento, Laravel, Symfony, or any other framework. Use it in
any PHP application that needs to talk to Azure Service Bus.

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Key classes](#key-classes)
- [Usage](#usage)
  - [Topology: create a topic, subscription, and rule](#topology-create-a-topic-subscription-and-rule)
  - [Send a message](#send-a-message)
  - [Receive a message (peek-lock)](#receive-a-message-peek-lock)
  - [Long-running processing: renew the lock](#long-running-processing-renew-the-lock)
  - [Reading dead-lettered messages](#reading-dead-lettered-messages)
  - [Listing topics and subscriptions](#listing-topics-and-subscriptions)
- [Examples](#examples)
- [Testing](#testing)
- [Limitations](#limitations)
- [License](#license)

## Features

- Topic, subscription, and rule management (create, get, list, delete)
- Message send/receive with peek-lock and receive-and-delete modes
- Explicit lock lifecycle control: unlock (abandon), renew, delete (complete)
- Native Azure dead-letter sub-queue access
- SAS-based authentication via connection string, matching the format shown in the Azure Portal
- No framework dependencies; installable and testable standalone

## Requirements

- PHP 7.3 or later (including PHP 8.x)
- `guzzlehttp/guzzle` ^6.5 or ^7.0
- `pear/net_url2` ^2.2

## Installation

```bash
composer require magetips/windowsazure
```

If this package isn't published to a registry you use, add it as a
[path](https://getcomposer.org/doc/05-repositories.md#path) or
[VCS](https://getcomposer.org/doc/05-repositories.md#vcs) repository in your project's `composer.json`.

## Quick start

Everything starts from a **connection string** for one Service Bus namespace, in the format shown in
the Azure Portal under *Shared access policies*:

```php
$connectionString = 'Endpoint=https://<namespace>.servicebus.windows.net/;'
    . 'SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=<key>';

$serviceBus = \MageTips\WindowsAzure\Common\ServicesBuilder::getInstance()
    ->createServiceBusService($connectionString);
```

`$serviceBus` is a `MageTips\WindowsAzure\ServiceBus\Internal\IServiceBus` (concretely a
`ServiceBusRestProxy`) — every example below is a method call on this object.

> **Tip:** building the client is cheap, but there's no connection pooling built in. If you're
> sending or receiving many messages, reuse a single `$serviceBus` instance rather than rebuilding
> it per call.

## Key classes

| Class | Purpose |
|---|---|
| `Common\ServicesBuilder` | Entry point — turns a connection string into an `IServiceBus`. |
| `ServiceBus\ServiceBusRestProxy` | Implements `IServiceBus`; every operation below lives here. |
| `ServiceBus\Models\BrokeredMessage` | A message: body, custom properties, delivery count, lock info. |
| `ServiceBus\Models\TopicInfo` / `SubscriptionInfo` / `RuleInfo` | Describe a topic/subscription/rule to create. |
| `ServiceBus\Models\ReceiveMessageOptions` + `ReceiveMode` | Controls how `receive*Message()` fetches messages (peek-lock vs. receive-and-delete). |
| `Common\ServiceException` | Thrown on any non-2xx REST response. |

## Usage

### Topology: create a topic, subscription, and rule

```php
use MageTips\WindowsAzure\ServiceBus\Models\TopicInfo;
use MageTips\WindowsAzure\ServiceBus\Models\SubscriptionInfo;
use MageTips\WindowsAzure\ServiceBus\Models\RuleInfo;

$serviceBus->createTopic(new TopicInfo('orders-export'));
$serviceBus->createSubscription('orders-export', new SubscriptionInfo('defaultsub'));
$serviceBus->createRule('orders-export', 'defaultsub', new RuleInfo('$Default'));
```

Creating a resource that already exists throws a `ServiceException`. Check for existence first
(e.g. via `listTopics()`) if you need idempotent setup.

### Send a message

```php
use MageTips\WindowsAzure\ServiceBus\Models\BrokeredMessage;

$message = new BrokeredMessage(json_encode(['orderId' => 1001]));
$message->setContentType('application/json');
$message->setProperty('EventType', 'order.exported'); // custom property, arbitrary key/value

$serviceBus->sendTopicMessage('orders-export', $message);
```

### Receive a message (peek-lock)

Peek-lock is the mode to use for anything that needs acknowledge/retry semantics — the message
stays on the subscription, invisible to other receivers, until you explicitly delete or unlock it.

```php
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMode;

$options = new ReceiveMessageOptions();
$options->setReceiveMode(ReceiveMode::PEEK_LOCK);

$message = $serviceBus->receiveSubscriptionMessage('orders-export', 'defaultsub', $options);

if ($message === null) {
    // The subscription had no messages available.
}

$body = (string) $message->getBody();
$deliveryCount = $message->getDeliveryCount(); // number of times Azure has (re)delivered this message
```

After processing, do exactly one of the following:

```php
// Success: remove the message from the subscription for good.
$serviceBus->deleteMessage($message);

// Transient failure: release the lock immediately so the message is redeliverable.
// Azure automatically dead-letters it once the subscription's MaxDeliveryCount is exceeded.
$serviceBus->unlockMessage($message);
```

`unlockMessage()`, `deleteMessage()`, and `renewLock()` all operate on `$message->getLockLocation()`
(a URL Azure returns with the message), not the topic/subscription name — always call them with the
same `BrokeredMessage` instance you received, not a reconstructed one.

### Long-running processing: renew the lock

Azure's default lock duration is short (30–60 seconds). If a handler might run longer, extend the
lock periodically so the message doesn't become redeliverable mid-processing:

```php
$serviceBus->renewLock($message); // call again before $message->getLockedUntilUtc() is reached
```

### Reading dead-lettered messages

Once a subscription's `MaxDeliveryCount` is exceeded, Azure automatically moves the message into a
built-in, per-subscription dead-letter sub-queue. There's no separate API for it — append
`/$DeadLetterQueue` to the subscription name passed to `receiveSubscriptionMessage()`:

```php
$deadLetter = $serviceBus->receiveSubscriptionMessage(
    'orders-export',
    'defaultsub/$DeadLetterQueue',
    $options
);
```

### Listing topics and subscriptions

```php
$topics = $serviceBus->listTopics()->getTopicInfos();
$subscriptions = $serviceBus->listSubscriptions('orders-export')->getSubscriptionInfos();
```

## Examples

Runnable, end-to-end scripts against a real Service Bus namespace — see [`examples/`](examples):

- [`01-send-and-receive-message.php`](examples/01-send-and-receive-message.php) — the core lifecycle: create topic/subscription, send, receive, acknowledge.
- [`02-dead-letter-handling.php`](examples/02-dead-letter-handling.php) — forcing a message into the dead-letter sub-queue and requeuing it.

## Testing

The test suite mocks the HTTP layer, so it runs without network access or Azure credentials:

```bash
composer install
vendor/bin/phpunit
```

## Limitations

- **Protocol:** this library uses the classic Service Bus REST API (SAS authentication, Atom/XML
  topology payloads), the same surface long-supported for backward compatibility. It does not use
  AMQP and is not built on Microsoft's modern Track 2 SDK protocol.
- **Receive modes:** `ReceiveMode::PEEK_LOCK` supports retry and dead-letter semantics via
  `unlockMessage()`/`deleteMessage()`/`renewLock()`. `ReceiveMode::RECEIVE_AND_DELETE` removes the
  message immediately on receipt — there is no second chance if the handler fails afterward.
- **Topology calls return values:** `createTopic()`, `createSubscription()`, and `createRule()`
  parse Azure's response body as an Atom entry and return the created resource; they are not `void`.
- **No connection pooling:** callers are responsible for reusing client instances where appropriate.

## License

Apache License 2.0. See [`LICENSE.txt`](LICENSE.txt).
