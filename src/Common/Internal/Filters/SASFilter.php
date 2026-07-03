<?php

/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0.
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 *
 * @link      https://github.com/windowsazure/azure-sdk-for-php
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */

namespace MageTips\WindowsAzure\Common\Internal\Filters;

use MageTips\WindowsAzure\Common\Internal\Resources;
use MageTips\WindowsAzure\Common\Internal\Utilities;
use MageTips\WindowsAzure\Common\Internal\IServiceFilter;
use MageTips\WindowsAzure\Common\Internal\Http\IHttpClient;
use MageTips\WindowsAzure\ServiceBus\Internal\WrapTokenManager;
use MageTips\WindowsAzure\ServiceBus\Internal\IWrap;
use Psr\Http\Message\ResponseInterface;

/**
 * Adds SAS authentication header to the http request object.
 */
class SASFilter implements IServiceFilter {

	private $sharedAccessKeyName;

	private $sharedAccessKey;

    public function __construct(
        $sharedAccessKeyName,
        $sharedAccessKey
    ) {
        $this->sharedAccessKeyName = $sharedAccessKeyName;
        $this->sharedAccessKey = $sharedAccessKey;
    }

    /**
     * Adds WRAP authentication header to the request headers.
     *
     * @param IHttpClient $request HTTP channel object
     *
     * @return IHttpClient
     */
    public function handleRequest(IHttpClient $request) {
        $token = $this->getAuthorization(
        	$request->getUrl(),
        	$this->sharedAccessKeyName,
        	$this->sharedAccessKey
    	);

        $request->setHeader(Resources::AUTHENTICATION, $token);

        return $request;
    }

    /**
     * @param $url
     * @param $policy
     * @param $key
     */
    private function getAuthorization($url, $sharedAccessKeyName, $sharedAccessKey) {
        $expiry = time() + 3600;
        $encodedUrl = Utilities::lowerUrlencode($url);
        $scope = $encodedUrl . "\n" . $expiry;
        $signature = base64_encode(hash_hmac('sha256', $scope, $sharedAccessKey, true));
        return sprintf(Resources::SAS_AUTHORIZATION,
        	Utilities::lowerUrlencode($signature),
        	$expiry,
        	$sharedAccessKeyName,
        	$encodedUrl
        );
    }


    /**
     * Returns the original response.
     *
     * @param IHttpClient       $request  A HTTP channel object
     * @param ResponseInterface $response A HTTP response object
     *
     * @return ResponseInterface
     */
    public function handleResponse(IHttpClient $request, ResponseInterface $response) {
        return $response;
    }
}
