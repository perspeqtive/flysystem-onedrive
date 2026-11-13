<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Microsoft\Graph\Core\GraphConstants;
use Microsoft\Graph\Exception\GraphException;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphCollectionRequest;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphRequest;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class TimeoutAwareGraphTest extends TestCase
{
    private MockHandler $mockHandler;

    private MockGuzzleClientFactory $clientFactory;

    private TimeoutAwareGraph $graph;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->clientFactory = new MockGuzzleClientFactory();
        $this->clientFactory->clientToReturn = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->graph = new TimeoutAwareGraph($this->clientFactory, 60.0);
    }

    public function testCreateRequestUsesGraphDefaults(): void
    {
        $request = $this->graph->setAccessToken('token')->createRequest('GET', '/sites/root');

        self::assertInstanceOf(TimeoutAwareGraphRequest::class, $request);
        self::assertSame(GraphConstants::REST_ENDPOINT, $request->getBaseUrl());
        self::assertSame(GraphConstants::API_VERSION, $request->getApiVersion());
        self::assertSame('Bearer token', $request->getHeaders()['Authorization']);
        self::assertSame(60.0, $request->getTimeout());
    }

    public function testCreateRequestUsesConfiguredConnectionSettings(): void
    {
        $this->mockHandler->append(new Response(204));

        $this->graph
            ->setAccessToken('token')
            ->setBaseUrl('https://graph.microsoft.test/')
            ->setApiVersion('beta')
            ->setProxyPort('proxy:8080', true)
            ->createRequest('GET', '/sites/root')
            ->execute();

        self::assertSame('beta/sites/root', (string) $this->mockHandler->getLastRequest()?->getUri());
        self::assertSame('https://graph.microsoft.test/', $this->clientFactory->lastCreateArguments['baseUrl'] ?? null);
        self::assertSame('proxy:8080', $this->clientFactory->lastCreateArguments['proxyPort'] ?? null);
        self::assertTrue($this->clientFactory->lastCreateArguments['proxyVerifySSL'] ?? null);
    }

    public function testSetProxyPortAcceptsIntegerPortLikeTheParent(): void
    {
        $this->mockHandler->append(new Response(204));

        $this->graph
            ->setAccessToken('token')
            ->setProxyPort(8888)
            ->createRequest('GET', '/sites/root')
            ->execute();

        self::assertSame('8888', $this->clientFactory->lastCreateArguments['proxyPort'] ?? null);
    }

    public function testCreateRequestUsesRefreshedAccessToken(): void
    {
        $this->graph->setAccessToken('expired');

        $request = $this->graph->setAccessToken('refreshed')->createRequest('GET', '/sites/root');

        self::assertSame('Bearer refreshed', $request->getHeaders()['Authorization']);
    }

    public function testCreateRequestRequiresAccessToken(): void
    {
        $this->expectException(GraphException::class);

        $this->graph->createRequest('GET', '/sites/root');
    }

    public function testCreateCollectionRequestReturnsTimeoutAwareCollectionRequest(): void
    {
        $request = $this->graph
            ->setAccessToken('token')
            ->setApiVersion('beta')
            ->createCollectionRequest('GET', '/sites/root/drives');

        self::assertInstanceOf(TimeoutAwareGraphCollectionRequest::class, $request);
        self::assertSame('beta', $request->getApiVersion());
        self::assertSame('Bearer token', $request->getHeaders()['Authorization']);
        self::assertSame(60.0, $request->getTimeout());
    }
}
