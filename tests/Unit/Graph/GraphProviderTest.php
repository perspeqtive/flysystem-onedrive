<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph;

use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProvider;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGraphFactory;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockTokenProvider;
use PHPUnit\Framework\TestCase;

final class GraphProviderTest extends TestCase
{
    private MockTokenProvider $tokenProvider;

    private MockGraphFactory $graphFactory;

    private GraphProvider $graphProvider;

    protected function setUp(): void
    {
        $this->tokenProvider = new MockTokenProvider();
        $this->tokenProvider->tokensToReturn = ['first-token', 'second-token'];
        $this->graphFactory = new MockGraphFactory();
        $this->graphFactory->graphToReturn = new TimeoutAwareGraph(new MockGuzzleClientFactory(), 100.0);
        $this->graphProvider = new GraphProvider(
            $this->tokenProvider,
            'tenant-id',
            'client-id',
            'client-secret',
            $this->graphFactory,
        );
    }

    public function testGetGraphCreatesGraphOnceWithToken(): void
    {
        $graph = $this->graphProvider->getGraph();

        self::assertSame($this->graphFactory->graphToReturn, $graph);
        self::assertSame($graph, $this->graphProvider->getGraph());
        self::assertSame(1, $this->graphFactory->createCount);
        self::assertSame(1, $this->tokenProvider->callCount);
        self::assertSame(
            ['tenantId' => 'tenant-id', 'clientId' => 'client-id', 'clientSecret' => 'client-secret'],
            $this->tokenProvider->lastCredentials,
        );
        self::assertSame('Bearer first-token', $graph->createRequest('GET', '/me')->getHeaders()['Authorization']);
    }

    public function testRefreshGraphTokenUpdatesTheCachedGraphInstance(): void
    {
        $graph = $this->graphProvider->getGraph();

        $this->graphProvider->refreshGraphToken();

        self::assertSame($graph, $this->graphProvider->getGraph());
        self::assertSame(1, $this->graphFactory->createCount);
        self::assertSame(2, $this->tokenProvider->callCount);
        self::assertSame('Bearer second-token', $graph->createRequest('GET', '/me')->getHeaders()['Authorization']);
    }

    public function testRefreshGraphTokenCreatesGraphWhenNoneExistsYet(): void
    {
        $this->graphProvider->refreshGraphToken();

        self::assertSame(1, $this->graphFactory->createCount);
        self::assertSame(1, $this->tokenProvider->callCount);
        self::assertSame(
            'Bearer first-token',
            $this->graphProvider->getGraph()->createRequest('GET', '/me')->getHeaders()['Authorization'],
        );
    }
}
