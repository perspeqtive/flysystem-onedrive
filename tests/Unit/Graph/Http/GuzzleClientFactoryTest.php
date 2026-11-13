<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph\Http;

use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\GuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class GuzzleClientFactoryTest extends TestCase
{
    private const HEADERS = [
        'Host' => 'https://graph.microsoft.com',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer token',
    ];

    private GuzzleClientFactory $clientFactory;

    protected function setUp(): void
    {
        $this->clientFactory = new GuzzleClientFactory(1.5);
    }

    public function testCreateReproducesGraphClientSettingsAndAddsConnectTimeout(): void
    {
        $client = $this->clientFactory->create('https://graph.microsoft.com', true, self::HEADERS, null, false);

        self::assertSame('https://graph.microsoft.com', (string) $client->getConfig('base_uri'));
        self::assertTrue($client->getConfig('http_errors'));
        self::assertSame(self::HEADERS, array_intersect_key($client->getConfig('headers'), self::HEADERS));
        self::assertSame(1.5, $client->getConfig('connect_timeout'));
        self::assertNull($client->getConfig('proxy'));
        self::assertNull($client->getConfig('timeout'));
        self::assertSame($this->isHttp2Supported() ? '2' : null, $client->getConfig('version'));
    }

    public function testCreateConfiguresProxy(): void
    {
        $client = $this->clientFactory->create('https://graph.microsoft.com', false, self::HEADERS, 'proxy:8080', true);

        self::assertFalse($client->getConfig('http_errors'));
        self::assertSame('proxy:8080', $client->getConfig('proxy'));
        self::assertTrue($client->getConfig('verify'));
    }

    public function testCreateDisablesProxySslVerificationOnlyWhenRequested(): void
    {
        $client = $this->clientFactory->create('https://graph.microsoft.com', true, self::HEADERS, 'proxy:8080', false);

        self::assertFalse($client->getConfig('verify'));
    }

    private function isHttp2Supported(): bool
    {
        return extension_loaded('curl')
            && defined('CURL_VERSION_HTTP2')
            && (curl_version()['features'] & CURL_VERSION_HTTP2) === CURL_VERSION_HTTP2;
    }
}
