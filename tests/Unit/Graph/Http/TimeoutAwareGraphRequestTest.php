<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Microsoft\Graph\Core\GraphConstants;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model\Site;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphRequest;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class TimeoutAwareGraphRequestTest extends TestCase
{
    private MockHandler $mockHandler;

    private MockGuzzleClientFactory $clientFactory;

    private TimeoutAwareGraphRequest $graphRequest;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->clientFactory = new MockGuzzleClientFactory();
        $this->clientFactory->clientToReturn = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->graphRequest = new TimeoutAwareGraphRequest(
            $this->clientFactory,
            30.0,
            'GET',
            '/sites/root',
            'token',
            'https://graph.microsoft.com',
            'v1.0',
            'proxy:8080',
            true,
        );
    }

    public function testConstructorUsesConfiguredRequestTimeout(): void
    {
        self::assertSame(30.0, $this->graphRequest->getTimeout());
    }

    public function testConstructorRequiresAccessToken(): void
    {
        $this->expectException(GraphException::class);

        new TimeoutAwareGraphRequest($this->clientFactory, 30.0, 'GET', '/sites', null, 'https://g.test', 'v1.0');
    }

    public function testExecuteUsesClientOfFactoryWithRequestSettings(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"id": "site-id"}'));

        $site = $this->graphRequest
            ->addHeaders(['Prefer' => 'HonorNonIndexedQueriesWarningMayFailRandomly'])
            ->setReturnType(Site::class)
            ->execute();

        self::assertInstanceOf(Site::class, $site);
        self::assertSame('site-id', $site->getId());
        self::assertSame('v1.0/sites/root', (string) $this->mockHandler->getLastRequest()?->getUri());
        self::assertSame(30.0, $this->mockHandler->getLastOptions()['timeout']);
        self::assertSame(
            [
                'baseUrl' => 'https://graph.microsoft.com',
                'hasHttpErrors' => true,
                'headers' => [
                    'Host' => 'https://graph.microsoft.com',
                    'Content-Type' => 'application/json',
                    'SdkVersion' => 'Graph-php-' . GraphConstants::SDK_VERSION,
                    'Authorization' => 'Bearer token',
                    'Prefer' => 'HonorNonIndexedQueriesWarningMayFailRandomly',
                ],
                'proxyPort' => 'proxy:8080',
                'proxyVerifySSL' => true,
            ],
            $this->clientFactory->lastCreateArguments,
        );
    }

    public function testExecutePassesDisabledHttpErrorsToFactory(): void
    {
        $this->mockHandler->append(new Response(204));

        $this->graphRequest->setHttpErrors(false)->execute();

        self::assertFalse($this->clientFactory->lastCreateArguments['hasHttpErrors'] ?? null);
    }
}
