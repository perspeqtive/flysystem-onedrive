<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

use function curl_version;
use function defined;
use function extension_loaded;

/**
 * Reproduces the client settings of Microsoft\Graph\Http\GraphRequest::createGuzzleClient() and adds a
 * connect timeout, which only limits DNS resolution, TCP connect and TLS handshake. A value of 0 disables it.
 */
final readonly class GuzzleClientFactory implements GuzzleClientFactoryInterface
{
    public function __construct(
        private float $connectTimeout,
    ) {
    }

    public function create(
        string $baseUrl,
        bool $hasHttpErrors,
        array $headers,
        ?string $proxyPort,
        bool $proxyVerifySSL,
    ): ClientInterface {
        $clientSettings = [
            'base_uri' => $baseUrl,
            'http_errors' => $hasHttpErrors,
            'headers' => $headers,
            'connect_timeout' => $this->connectTimeout,
        ];

        if ($proxyPort !== null) {
            $clientSettings['verify'] = $proxyVerifySSL;
            $clientSettings['proxy'] = $proxyPort;
        }

        if ($this->isHttp2Supported()) {
            $clientSettings['version'] = '2';
        }

        return new Client($clientSettings);
    }

    private function isHttp2Supported(): bool
    {
        return extension_loaded('curl')
            && defined('CURL_VERSION_HTTP2')
            && (curl_version()['features'] & CURL_VERSION_HTTP2) === CURL_VERSION_HTTP2;
    }
}
