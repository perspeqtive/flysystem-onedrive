<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Http;

use PERSPEQTIVE\FlysystemOneDrive\Exception\ConnectTimeoutException;
use PERSPEQTIVE\FlysystemOneDrive\Http\ConnectTimeoutHttpClient;
use PERSPEQTIVE\FlysystemOneDrive\Http\ConnectTimeoutProgressListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ConnectTimeoutHttpClientTest extends TestCase
{
    private const CONNECT_TIMEOUT = 1.0;

    /**
     * @var list<ResponseInterface>
     */
    private array $responsesToReturn;

    /**
     * @var list<array<string, mixed>>
     */
    private array $recordedOptions;

    private MockHttpClient $innerHttpClient;

    private ConnectTimeoutHttpClient $connectTimeoutHttpClient;

    protected function setUp(): void
    {
        $this->responsesToReturn = [];
        $this->recordedOptions = [];
        $this->innerHttpClient = new MockHttpClient(
            function (string $method, string $url, array $options): ResponseInterface {
                $this->recordedOptions[] = $options;

                return array_shift($this->responsesToReturn);
            },
        );
        $this->connectTimeoutHttpClient = new ConnectTimeoutHttpClient($this->innerHttpClient, self::CONNECT_TIMEOUT);
    }

    public function testRequestAbortsWhenConnectionIsNotEstablishedWithinConnectTimeout(): void
    {
        $this->responsesToReturn[] = new MockResponse('', ['pretransfer_time' => 0.0, 'total_time' => 1.5]);

        $this->expectException(ConnectTimeoutException::class);

        $this->connectTimeoutHttpClient->request('GET', 'https://graph.microsoft.test/')->getContent();
    }

    public function testRequestDoesNotAbortSlowTransfersOnceConnected(): void
    {
        $this->responsesToReturn[] = new MockResponse(
            ['first ', 'second'],
            ['pretransfer_time' => 0.1, 'total_time' => 75.0],
        );

        $content = $this->connectTimeoutHttpClient->request('PUT', 'https://upload.test/', ['body' => 'chunk'])
            ->getContent();

        self::assertSame('first second', $content);
    }

    public function testRequestKeepsCallingTheOriginalProgressListener(): void
    {
        $this->responsesToReturn[] = new MockResponse('content', ['pretransfer_time' => 0.1, 'total_time' => 0.2]);
        $progressCalls = 0;

        $this->connectTimeoutHttpClient->request('GET', 'https://download.test/', [
            'on_progress' => static function () use (&$progressCalls): void {
                ++$progressCalls;
            },
        ])->getContent();

        self::assertGreaterThan(0, $progressCalls);
        self::assertInstanceOf(ConnectTimeoutProgressListener::class, $this->recordedOptions[0]['on_progress']);
    }

    public function testRequestPassesOptionsUnchangedWhenConnectTimeoutIsDisabled(): void
    {
        $this->responsesToReturn[] = new MockResponse('content', ['pretransfer_time' => 0.0, 'total_time' => 5.0]);
        $httpClient = new ConnectTimeoutHttpClient($this->innerHttpClient, 0.0);

        $content = $httpClient->request('GET', 'https://download.test/')->getContent();

        self::assertSame('content', $content);
        self::assertNull($this->recordedOptions[0]['on_progress'] ?? null);
    }

    public function testWithOptionsKeepsTheDecoration(): void
    {
        $this->responsesToReturn[] = new MockResponse('', ['pretransfer_time' => 0.0, 'total_time' => 1.5]);

        $httpClient = $this->connectTimeoutHttpClient->withOptions(['base_uri' => 'https://graph.microsoft.test/']);

        self::assertInstanceOf(ConnectTimeoutHttpClient::class, $httpClient);
        $this->expectException(ConnectTimeoutException::class);
        $httpClient->request('GET', 'v1.0/sites')->getContent();
    }

    public function testWithOptionsKeepsCallingTheDefaultProgressListener(): void
    {
        $this->responsesToReturn[] = new MockResponse('content', ['pretransfer_time' => 0.1, 'total_time' => 0.2]);
        $progressCalls = 0;

        $httpClient = $this->connectTimeoutHttpClient->withOptions([
            'on_progress' => static function () use (&$progressCalls): void {
                ++$progressCalls;
            },
        ]);
        $httpClient->request('GET', 'https://download.test/')->getContent();

        self::assertGreaterThan(0, $progressCalls);
        self::assertInstanceOf(ConnectTimeoutProgressListener::class, $this->recordedOptions[0]['on_progress']);
    }

    public function testWithOptionsDefaultProgressListenerIsReplacedByRequestProgressListener(): void
    {
        $this->responsesToReturn[] = new MockResponse('content', ['pretransfer_time' => 0.1, 'total_time' => 0.2]);
        $defaultProgressCalls = 0;
        $requestProgressCalls = 0;

        $httpClient = $this->connectTimeoutHttpClient->withOptions([
            'on_progress' => static function () use (&$defaultProgressCalls): void {
                ++$defaultProgressCalls;
            },
        ]);
        $httpClient->request('GET', 'https://download.test/', [
            'on_progress' => static function () use (&$requestProgressCalls): void {
                ++$requestProgressCalls;
            },
        ])->getContent();

        self::assertSame(0, $defaultProgressCalls);
        self::assertGreaterThan(0, $requestProgressCalls);
    }

    public function testStreamDelegatesToTheDecoratedClient(): void
    {
        $this->responsesToReturn[] = new MockResponse('content', ['pretransfer_time' => 0.1, 'total_time' => 0.2]);
        $response = $this->connectTimeoutHttpClient->request('GET', 'https://download.test/');

        $content = '';
        foreach ($this->connectTimeoutHttpClient->stream($response) as $chunk) {
            $content .= $chunk->getContent();
        }

        self::assertSame('content', $content);
    }

    public function testResetResetsTheDecoratedClient(): void
    {
        $this->responsesToReturn[] = new MockResponse('content');
        $this->connectTimeoutHttpClient->request('GET', 'https://download.test/')->getContent();

        $this->connectTimeoutHttpClient->reset();

        self::assertSame(0, $this->innerHttpClient->getRequestsCount());
    }
}
