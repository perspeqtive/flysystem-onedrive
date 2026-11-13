<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Flysystem;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveFlysystemFactory;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGraphProvider;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockSiteIdResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function fclose;
use function fopen;
use function fwrite;
use function rewind;
use function str_repeat;

final class OneDriveFlysystemFactoryTest extends TestCase
{
    private const CHUNK_SIZE = 327680;

    private MockHandler $mockHandler;

    private MockHttpClient $httpClient;

    private MockSiteIdResolver $siteIdResolver;

    private OneDriveFlysystemFactory $flysystemFactory;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $clientFactory = new MockGuzzleClientFactory();
        $clientFactory->clientToReturn = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $graphProvider = new MockGraphProvider();
        $graphProvider->graphToReturn = (new TimeoutAwareGraph($clientFactory, 100.0))->setAccessToken('token');
        $this->httpClient = new MockHttpClient([new MockResponse('{}'), new MockResponse('{}')]);
        $this->siteIdResolver = new MockSiteIdResolver();
        $this->siteIdResolver->siteIdToReturn = 'site-id';

        $this->flysystemFactory = new OneDriveFlysystemFactory(
            $graphProvider,
            $this->httpClient,
            $this->siteIdResolver,
            [
                'Configured' => [
                    'drive' => 'Configured',
                    'site' => null,
                    'options' => ['chunk_size' => self::CHUNK_SIZE, 'directory_type' => 'drives'],
                ],
                'InvalidChunkSize' => [
                    'drive' => 'InvalidChunkSize',
                    'site' => null,
                    'options' => ['chunk_size' => 3200000],
                ],
                'WithoutOptions' => [
                    'drive' => 'WithoutOptions',
                ],
            ],
        );
    }

    public function testGetCreatesAdapterForResolvedSiteDrive(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"name": "neu", "folder": {"childCount": 0}}'));

        $adapter = $this->flysystemFactory->get('WithoutOptions');

        self::assertTrue($adapter->directoryExists('Import/neu'));
        self::assertSame(['WithoutOptions'], $this->siteIdResolver->resolvedDriveIdentifiers);
        self::assertSame(
            'v1.0/sites/site-id/drive/root:/Import/neu',
            (string) $this->mockHandler->getLastRequest()?->getUri(),
        );
    }

    public function testGetPassesConfiguredOptionsToAdapterButKeepsSiteDirectoryType(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"uploadUrl": "https://upload.test/session"}'));
        $contents = fopen('php://temp', 'r+');
        fwrite($contents, str_repeat('x', self::CHUNK_SIZE + 1));
        rewind($contents);

        $this->flysystemFactory->get('Configured')->writeStream('Import/liste.xlsx', $contents);
        fclose($contents);

        self::assertSame(
            'v1.0/sites/site-id/drive/root:/Import/liste.xlsx:/createUploadSession',
            (string) $this->mockHandler->getLastRequest()?->getUri(),
        );
        self::assertSame(2, $this->httpClient->getRequestsCount());
    }

    public function testGetValidatesConfiguredOptions(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Chunk size must be a multiple of 320KB');

        $this->flysystemFactory->get('InvalidChunkSize');
    }

    public function testGetReturnsCachedAdapter(): void
    {
        self::assertSame($this->flysystemFactory->get('WithoutOptions'), $this->flysystemFactory->get('WithoutOptions'));
        self::assertSame(['WithoutOptions'], $this->siteIdResolver->resolvedDriveIdentifiers);
    }

    public function testGetFailsForUnknownDrive(): void
    {
        $this->expectException(UnknownDriveException::class);

        $this->flysystemFactory->get('Unknown');
    }
}
