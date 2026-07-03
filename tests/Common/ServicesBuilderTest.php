<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\Common;

use MageTips\WindowsAzure\Common\Internal\Http\HttpClient;
use MageTips\WindowsAzure\Common\Internal\RestProxy;
use MageTips\WindowsAzure\Common\ServicesBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class ServicesBuilderTest extends TestCase
{
    private const CONNECTION_STRING = 'Endpoint=https://example.servicebus.windows.net/;'
        . 'SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=dGVzdA==';

    /**
     * ServicesBuilder is a process-lifetime singleton; every HttpClient it hands
     * out should share one underlying Guzzle client so REST calls across
     * different createServiceBusService() calls (e.g. different topics) still
     * reuse an already-open connection instead of each starting fresh.
     */
    public function testTwoServiceBusServicesShareTheSameUnderlyingGuzzleClient(): void
    {
        $builder = ServicesBuilder::getInstance();

        $first = $builder->createServiceBusService(self::CONNECTION_STRING);
        $second = $builder->createServiceBusService(self::CONNECTION_STRING);

        $this->assertSame(
            $this->extractGuzzleClient($first),
            $this->extractGuzzleClient($second)
        );
    }

    private function extractGuzzleClient(object $serviceBusRestProxy): object
    {
        $channelProperty = new \ReflectionProperty(RestProxy::class, '_channel');
        $channelProperty->setAccessible(true);
        /** @var HttpClient $httpClient */
        $httpClient = $channelProperty->getValue($serviceBusRestProxy);

        $guzzleClientProperty = new \ReflectionProperty(HttpClient::class, '_guzzleClient');
        $guzzleClientProperty->setAccessible(true);

        return $guzzleClientProperty->getValue($httpClient);
    }
}
