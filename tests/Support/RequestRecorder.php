<?php

declare(strict_types=1);

namespace MageTips\WindowsAzure\Test\Support;

/**
 * Shared by FakeHttpClient and every clone RestProxy makes of it, so a test can
 * inspect every HTTP request that would have been sent across the lifetime of a
 * single ServiceBusRestProxy call.
 *
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */
class RequestRecorder
{
    /** @var array<int, array{method: string, url: ?string, headers: array, body: string}> */
    public $requests = [];

    public function last(): array
    {
        if (empty($this->requests)) {
            throw new \RuntimeException('No requests were recorded.');
        }

        return $this->requests[count($this->requests) - 1];
    }
}
