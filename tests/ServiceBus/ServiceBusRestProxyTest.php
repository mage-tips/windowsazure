<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\ServiceBus;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use MageTips\WindowsAzure\Common\Internal\Serialization\XmlSerializer;
use MageTips\WindowsAzure\ServiceBus\Models\BrokeredMessage;
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMessageOptions;
use MageTips\WindowsAzure\ServiceBus\Models\ReceiveMode;
use MageTips\WindowsAzure\ServiceBus\Models\RuleInfo;
use MageTips\WindowsAzure\ServiceBus\Models\SubscriptionInfo;
use MageTips\WindowsAzure\ServiceBus\Models\TopicInfo;
use MageTips\WindowsAzure\ServiceBus\ServiceBusRestProxy;
use MageTips\WindowsAzure\Test\Support\FakeHttpClient;
use MageTips\WindowsAzure\Test\Support\RequestRecorder;

/**
 * Exercises the request-building side of ServiceBusRestProxy: given a call, does
 * it hit the right path with the right HTTP method/headers/body? Response parsing
 * is covered where it's cheap to set up (receiveSubscriptionMessage); full Atom/XML
 * response deserialization for topology calls is out of scope here.
 *
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class ServiceBusRestProxyTest extends TestCase
{
    private const BASE_URI = 'https://test-namespace.servicebus.windows.net';

    private function buildProxy(RequestRecorder $recorder, Response $response): ServiceBusRestProxy
    {
        $httpClient = new FakeHttpClient($recorder, $response);

        return new ServiceBusRestProxy($httpClient, self::BASE_URI, new XmlSerializer());
    }

    public function testSendTopicMessageSendsPostWithBody(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(201));

        $message = new BrokeredMessage('hello world');
        $proxy->sendTopicMessage('my-topic', $message);

        $request = $recorder->last();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('my-topic/messages', $request['url']);
        $this->assertSame('hello world', $request['body']);
    }

    public function testReceiveSubscriptionMessageParsesBrokeredMessageFromResponse(): void
    {
        $recorder = new RequestRecorder();
        $response = new Response(
            201,
            [
                'BrokerProperties' => json_encode(['DeliveryCount' => 3, 'MessageId' => 'msg-1']),
                'Location' => self::BASE_URI . '/my-topic/subscriptions/sub-1/messages/1/abc-lock-token',
            ],
            'the message body'
        );
        $proxy = $this->buildProxy($recorder, $response);

        $options = new ReceiveMessageOptions();
        $options->setReceiveMode(ReceiveMode::PEEK_LOCK);

        $message = $proxy->receiveSubscriptionMessage('my-topic', 'sub-1', $options);

        $request = $recorder->last();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('my-topic/subscriptions/sub-1/messages/head', $request['url']);

        $this->assertInstanceOf(BrokeredMessage::class, $message);
        $this->assertSame(3, $message->getDeliveryCount());
        $this->assertSame(
            self::BASE_URI . '/my-topic/subscriptions/sub-1/messages/1/abc-lock-token',
            $message->getLockLocation()
        );
        $this->assertSame('the message body', (string) $message->getBody());
    }

    public function testReceiveSubscriptionMessageReturnsNullOnNoContent(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(204));

        $options = new ReceiveMessageOptions();
        $options->setReceiveMode(ReceiveMode::PEEK_LOCK);

        $message = $proxy->receiveSubscriptionMessage('my-topic', 'sub-1', $options);

        $this->assertNull($message);
    }

    public function testUnlockMessageSendsPutToLockLocation(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(200));

        $message = new BrokeredMessage('body');
        $message->setLockLocation(self::BASE_URI . '/my-topic/subscriptions/sub-1/messages/1/lock-token');

        $proxy->unlockMessage($message);

        $request = $recorder->last();
        $this->assertSame('PUT', $request['method']);
        $this->assertStringContainsString('my-topic/subscriptions/sub-1/messages/1/lock-token', $request['url']);
    }

    public function testRenewLockSendsPostToLockLocation(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(200));

        $message = new BrokeredMessage('body');
        $message->setLockLocation(self::BASE_URI . '/my-topic/subscriptions/sub-1/messages/1/lock-token');

        $proxy->renewLock($message);

        $request = $recorder->last();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('my-topic/subscriptions/sub-1/messages/1/lock-token', $request['url']);
    }

    public function testDeleteMessageSendsDeleteToLockLocation(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(200));

        $message = new BrokeredMessage('body');
        $message->setLockLocation(self::BASE_URI . '/my-topic/subscriptions/sub-1/messages/1/lock-token');

        $proxy->deleteMessage($message);

        $request = $recorder->last();
        $this->assertSame('DELETE', $request['method']);
        $this->assertStringContainsString('my-topic/subscriptions/sub-1/messages/1/lock-token', $request['url']);
    }

    public function testCreateTopicSendsPutWithAtomXmlBody(): void
    {
        $recorder = new RequestRecorder();
        // create* calls parse the response body as an Atom <entry> (Azure echoes
        // back the created resource); a minimal well-formed entry is enough since
        // Entry::fromXml() only reads keys that are present.
        $proxy = $this->buildProxy($recorder, new Response(201, [], $this->atomEntryBody('orders-export')));

        $proxy->createTopic(new TopicInfo('orders-export'));

        $request = $recorder->last();
        $this->assertSame('PUT', $request['method']);
        $this->assertStringContainsString('orders-export', $request['url']);
        $this->assertSame('application/atom+xml;type=entry;charset=utf-8', $request['headers']['content-type']);
        $this->assertStringContainsString('<TopicDescription', $request['body']);
    }

    public function testCreateSubscriptionSendsPutToSubscriptionPath(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(201, [], $this->atomEntryBody('defaultsub')));

        $subscriptionInfo = new SubscriptionInfo('defaultsub');
        $proxy->createSubscription('orders-export', $subscriptionInfo);

        $request = $recorder->last();
        $this->assertSame('PUT', $request['method']);
        $this->assertStringContainsString('orders-export/subscriptions/defaultsub', $request['url']);
    }

    public function testCreateRuleSendsPutToRulePath(): void
    {
        $recorder = new RequestRecorder();
        $proxy = $this->buildProxy($recorder, new Response(201, [], $this->atomEntryBody('$Default')));

        $ruleInfo = new RuleInfo('$Default');
        $proxy->createRule('orders-export', 'defaultsub', $ruleInfo);

        $request = $recorder->last();
        $this->assertSame('PUT', $request['method']);
        $this->assertStringContainsString('orders-export/subscriptions/defaultsub/rules', $request['url']);
    }

    private function atomEntryBody(string $title): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<entry xmlns="http://www.w3.org/2005/Atom"><title type="text">' . $title . '</title></entry>';
    }
}
