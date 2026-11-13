<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph;

use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphFactory;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphRequest;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class GraphFactoryTest extends TestCase
{
    private GraphFactory $graphFactory;

    protected function setUp(): void
    {
        $this->graphFactory = new GraphFactory(new MockGuzzleClientFactory(), 25.0);
    }

    public function testCreateReturnsTimeoutAwareGraphWithConfiguredRequestTimeout(): void
    {
        $graph = $this->graphFactory->create();

        self::assertInstanceOf(TimeoutAwareGraph::class, $graph);
        $request = $graph->setAccessToken('token')->createRequest('GET', '/me');
        self::assertInstanceOf(TimeoutAwareGraphRequest::class, $request);
        self::assertSame(25.0, $request->getTimeout());
    }

    public function testCreateReturnsNewInstances(): void
    {
        self::assertNotSame($this->graphFactory->create(), $this->graphFactory->create());
    }
}
