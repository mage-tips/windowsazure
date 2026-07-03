<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\Common\Internal;

use PHPUnit\Framework\TestCase;
use MageTips\WindowsAzure\Common\Internal\ServiceBusSettings;

/**
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class ServiceBusSettingsTest extends TestCase
{
    /**
     * The Azure Portal gives connection strings with an "sb://" endpoint (the
     * AMQP convention), but this client makes plain HTTPS REST calls and
     * Guzzle rejects any scheme other than http/https. Confirmed against a
     * live namespace on 2026-07-02: without normalization, this failed with
     * "The scheme 'sb' is not supported."
     */
    public function testSbSchemeIsNormalizedToHttps(): void
    {
        $connectionString = 'Endpoint=sb://example.servicebus.windows.net/;'
            . 'SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=dGVzdA==';

        $settings = ServiceBusSettings::createFromConnectionString($connectionString);

        $this->assertSame('https://example.servicebus.windows.net/', $settings->getServiceBusEndpointUri());
    }

    public function testHttpsSchemeIsLeftUnchanged(): void
    {
        $connectionString = 'Endpoint=https://example.servicebus.windows.net/;'
            . 'SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=dGVzdA==';

        $settings = ServiceBusSettings::createFromConnectionString($connectionString);

        $this->assertSame('https://example.servicebus.windows.net/', $settings->getServiceBusEndpointUri());
    }
}
