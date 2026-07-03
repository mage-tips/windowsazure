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
 * @link      https://github.com/Azure/azure-sdk-for-php
 * @category  MageTips
 * @package   MageTips_WindowsAzure
 * @author    Muhammad Umar <umarshiekh619@gmail.com>
 * @link      https://github.com/mage-tips/windowsazure
 */

namespace MageTips\WindowsAzure\Common;

use MageTips\WindowsAzure\Common\Internal\Http\IHttpClient;
use MageTips\WindowsAzure\Common\Internal\Resources;
use MageTips\WindowsAzure\Common\Internal\Serialization\ISerializer;
use MageTips\WindowsAzure\Common\Internal\Http\HttpClient;
use MageTips\WindowsAzure\Common\Internal\Filters\HeadersFilter;
use MageTips\WindowsAzure\Common\Internal\Serialization\XmlSerializer;
use MageTips\WindowsAzure\Common\Internal\ServiceBusSettings;
use MageTips\WindowsAzure\ServiceBus\Internal\IServiceBus;
use MageTips\WindowsAzure\ServiceBus\ServiceBusRestProxy;

/**
 * Builds the Azure Service Bus service object.
 */
class ServicesBuilder
{
    /**
     * @var ServicesBuilder
     */
    private static $_instance = null;

    /**
     * Gets the HTTP client used in the REST services construction.
     *
     * @return IHttpClient
     */
    protected function httpClient()
    {
        return new HttpClient();
    }

    /**
     * Gets the serializer used in the REST services construction.
     *
     * @return ISerializer
     */
    protected function serializer()
    {
        return new XmlSerializer();
    }

    /**
     * Builds a Service Bus object.
     *
     * @param string $connectionString The configuration connection string
     *
     * @return IServiceBus
     */
    public function createServiceBusService($connectionString)
    {
        $settings = ServiceBusSettings::createFromConnectionString(
            $connectionString
        );

        $httpClient = $this->httpClient();
        $serializer = $this->serializer();
        $serviceBusWrapper = new ServiceBusRestProxy(
            $httpClient,
            $settings->getServiceBusEndpointUri(),
            $serializer
        );

        // Adding headers filter
        $headers = [];

        $headersFilter = new HeadersFilter($headers);
        $serviceBusWrapper = $serviceBusWrapper->withFilter($headersFilter);

        $filter = $settings->getFilter();

        return $serviceBusWrapper->withFilter($filter);
    }

    /**
     * Gets the static instance of this class.
     *
     * @return ServicesBuilder
     */
    public static function getInstance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
}
