<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Graph\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Model\DriveItem;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphCollectionRequest;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PHPUnit\Framework\TestCase;

final class TimeoutAwareGraphCollectionRequestTest extends TestCase
{
    private MockHandler $mockHandler;

    private MockGuzzleClientFactory $clientFactory;

    private TimeoutAwareGraphCollectionRequest $collectionRequest;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $this->clientFactory = new MockGuzzleClientFactory();
        $this->clientFactory->clientToReturn = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $this->collectionRequest = new TimeoutAwareGraphCollectionRequest(
            $this->clientFactory,
            45.0,
            'GET',
            '/drives/drive-id/root/children',
            'token',
            'https://graph.microsoft.com',
            'v1.0',
        );
    }

    public function testGetPageUsesClientOfFactory(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"value": [{"name": "liste.xlsx"}]}'));

        $items = $this->collectionRequest->setReturnType(DriveItem::class)->getPage();

        self::assertCount(1, $items);
        self::assertSame('liste.xlsx', $items[0]->getName());
        self::assertTrue($this->collectionRequest->isEnd());
        self::assertSame(1, $this->clientFactory->createCount);
        self::assertSame(45.0, $this->mockHandler->getLastOptions()['timeout']);
    }

    public function testCountUsesClientOfFactory(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"@odata.count": 7}'));

        self::assertSame(7, $this->collectionRequest->count());
        self::assertSame(
            'v1.0/drives/drive-id/root/children?$count=true',
            rawurldecode((string) $this->mockHandler->getLastRequest()?->getUri()),
        );
        self::assertSame(45.0, $this->mockHandler->getLastOptions()['timeout']);
        self::assertSame('Bearer token', $this->clientFactory->lastCreateArguments['headers']['Authorization'] ?? null);
    }

    public function testCountFailsWhenCountIsUnavailable(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"value": []}'));

        $this->expectException(GraphException::class);

        $this->collectionRequest->count();
    }
}
