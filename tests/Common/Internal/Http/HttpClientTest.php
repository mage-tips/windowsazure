<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\Common\Internal\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use MageTips\WindowsAzure\Common\Internal\Http\HttpClient;
use MageTips\WindowsAzure\Common\Internal\Http\Url;
use PHPUnit\Framework\TestCase;

/**
 * sendAndGetHttpResponse() used to build a brand-new Guzzle Client on every
 * single call, discarding connection reuse entirely - these tests prove a
 * client injected into the constructor is genuinely reused across calls, not
 * just accepted and ignored.
 *
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class HttpClientTest extends TestCase
{
    public function testSendSucceedsThroughAnInjectedClient(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'first response'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $httpClient = new HttpClient('', '', $client);
        $httpClient->setExpectedStatusCode(200);

        $body = $httpClient->send([], new Url('https://example.servicebus.windows.net/topic'));

        $this->assertSame('first response', $body);
    }

    /**
     * Two consecutive calls only succeed if the SAME injected client (and thus
     * the SAME MockHandler queue) backs both of them. Before the fix, the
     * second call would build a fresh, un-mocked Guzzle Client and attempt a
     * real network request instead of consuming the second queued response.
     */
    public function testTheSameInjectedClientIsReusedAcrossMultipleCalls(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'first response'),
            new Response(200, [], 'second response'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $httpClient = new HttpClient('', '', $client);
        $httpClient->setExpectedStatusCode(200);

        $first = $httpClient->send([], new Url('https://example.servicebus.windows.net/topic'));
        $second = $httpClient->send([], new Url('https://example.servicebus.windows.net/topic'));

        $this->assertSame('first response', $first);
        $this->assertSame('second response', $second);
        $this->assertSame(0, $mock->count());
    }

    public function testDefaultsToItsOwnClientWhenNoneIsInjected(): void
    {
        $httpClient = new HttpClient();

        $this->assertInstanceOf(HttpClient::class, $httpClient);
    }
}
