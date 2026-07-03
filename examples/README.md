# Examples

Runnable examples demonstrating this library against a real Azure Service Bus namespace.

## Prerequisites

- A Service Bus namespace on the **Standard** or **Premium** tier (Basic tier doesn't support
  topics/subscriptions, which these examples use).
- The namespace's connection string (Azure Portal → your namespace → *Shared access policies* →
  `RootManageSharedAccessKey` → *Primary Connection String*).
- Dependencies installed: from the package root, run `composer install`.

## Running

Export your connection string, then run any example directly:

```bash
export AZURE_SERVICE_BUS_CONNECTION_STRING="Endpoint=https://<namespace>.servicebus.windows.net/;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=<key>"

php examples/01-send-and-receive-message.php
php examples/02-dead-letter-handling.php
```

Both examples create their own topic/subscription if missing and are safe to re-run.

## What's here

| File | Demonstrates |
|---|---|
| `bootstrap.php` | Shared setup: reads the connection string and builds the Service Bus client. Not run directly. |
| `01-send-and-receive-message.php` | The core lifecycle: create topic/subscription, send a message, receive it with peek-lock, acknowledge it. |
| `02-dead-letter-handling.php` | Forcing a message into the native dead-letter sub-queue by exceeding `MaxDeliveryCount`, then reading it back and requeuing it. |

All example data (order IDs, customer names, payloads) is dummy data for demonstration only.
