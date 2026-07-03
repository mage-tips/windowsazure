<?php

/**
 * Shared bootstrap for the example scripts in this directory.
 *
 * Set AZURE_SERVICE_BUS_CONNECTION_STRING in your environment before running any
 * example, e.g.:
 *
 *   export AZURE_SERVICE_BUS_CONNECTION_STRING="Endpoint=https://<namespace>.servicebus.windows.net/;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=<key>"
 *   php examples/01-send-and-receive-message.php
 *
 * Get this value from the Azure Portal: your Service Bus namespace ->
 * Shared access policies -> RootManageSharedAccessKey -> Primary Connection String.
 * The namespace must be Standard or Premium tier — Basic tier doesn't support topics.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

function example_connection_string(): string
{
    $connectionString = getenv('AZURE_SERVICE_BUS_CONNECTION_STRING');

    if ($connectionString === false || $connectionString === '') {
        fwrite(
            STDERR,
            "AZURE_SERVICE_BUS_CONNECTION_STRING is not set.\n" .
            "Export a real Service Bus connection string before running this example.\n" .
            "See examples/bootstrap.php for details.\n"
        );
        exit(1);
    }

    return $connectionString;
}

function example_service_bus(): \MageTips\WindowsAzure\ServiceBus\Internal\IServiceBus
{
    return \MageTips\WindowsAzure\Common\ServicesBuilder::getInstance()
        ->createServiceBusService(example_connection_string());
}
