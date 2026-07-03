<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\Support;

use Psr\Http\Message\ResponseInterface;
use MageTips\WindowsAzure\Common\Internal\Http\IHttpClient;
use MageTips\WindowsAzure\Common\Internal\Http\IUrl;

/**
 * Minimal IHttpClient test double. RestProxy::sendHttpContext() clones the
 * channel on every call before configuring and sending it, so a PHPUnit mock's
 * expects()/with() assertions on the original instance won't reliably see calls
 * made on the clone. This double instead records every request into a
 * RequestRecorder shared across clones (object properties survive PHP's default
 * shallow clone by reference), which the test then inspects directly.
 *
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class FakeHttpClient implements IHttpClient
{
    /** @var RequestRecorder */
    private $recorder;

    /** @var ResponseInterface */
    private $response;

    /** @var string */
    private $method = '';

    /** @var array */
    private $headers = [];

    /** @var string */
    private $body = '';

    public function __construct(RequestRecorder $recorder, ResponseInterface $response)
    {
        $this->recorder = $recorder;
        $this->response = $response;
    }

    public function setUrl(IUrl $url)
    {
    }

    public function getUrl()
    {
        return null;
    }

    public function setMethod($method)
    {
        $this->method = $method;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeader($header, $value, $replace = false)
    {
        $this->headers[$header] = $value;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function setPostParameters(array $postParameters)
    {
    }

    public function sendAndGetHttpResponse(array $filters, ?IUrl $url = null)
    {
        $this->recorder->requests[] = [
            'method' => $this->method,
            'url' => $url ? $url->getUrl() : null,
            'headers' => $this->headers,
            'body' => $this->body,
        ];

        return $this->response;
    }

    public function send(array $filters, ?IUrl $url = null)
    {
        return (string) $this->sendAndGetHttpResponse($filters, $url)->getBody();
    }

    public function setExpectedStatusCode($statusCodes)
    {
    }

    public function getSuccessfulStatusCode()
    {
        return [];
    }

    public function setConfig($name, $value = null)
    {
    }

    public function getConfig($name)
    {
        return null;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function __clone()
    {
        // Deliberately not deep-cloning $recorder: every clone must keep
        // recording into the same shared recorder instance.
    }

    public static function throwIfError($actual, $reason, $message, array $expected)
    {
    }
}
