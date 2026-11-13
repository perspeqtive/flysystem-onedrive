<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Site;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PERSPEQTIVE\FlysystemOneDrive\Exception\SiteNotFoundException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Site\GraphSiteIdResolver;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGraphProvider;
use PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks\MockGuzzleClientFactory;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class GraphSiteIdResolverTest extends TestCase
{
    private const SITE_ID = 'contoso.sharepoint.com,1111,2222';

    private MockHandler $mockHandler;

    private GraphSiteIdResolver $siteIdResolver;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
        $clientFactory = new MockGuzzleClientFactory();
        $clientFactory->clientToReturn = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        $graphProvider = new MockGraphProvider();
        $graphProvider->graphToReturn = (new TimeoutAwareGraph($clientFactory, 100.0))->setAccessToken('token');

        $this->siteIdResolver = new GraphSiteIdResolver($graphProvider, [
            'ByPath' => [
                'drive' => 'Industrie Umgebung',
                'site' => ['hostname' => 'contoso.sharepoint.com', 'path' => 'sites/Industrie Umgebung'],
            ],
            'ByEncodedPath' => [
                'drive' => 'Industrie Umgebung',
                'site' => ['hostname' => 'contoso.sharepoint.com', 'path' => 'sites/Industrie%20Umgebung'],
            ],
            'BySearch' => [
                'drive' => 'IndustrieUmgebung',
                'site' => null,
            ],
            'LegacyConfig' => [
                'drive' => 'IndustrieUmgebung',
            ],
        ]);
    }

    public function testResolveLooksUpSiteByPath(): void
    {
        $this->mockHandler->append(new Response(200, [], sprintf('{"id": "%s"}', self::SITE_ID)));

        self::assertSame(self::SITE_ID, $this->siteIdResolver->resolve('ByPath'));
        self::assertSame(
            'v1.0/sites/contoso.sharepoint.com:/sites/Industrie%20Umgebung',
            (string) $this->mockHandler->getLastRequest()?->getUri(),
        );
    }

    public function testResolveDoesNotEncodeAnAlreadyEncodedSitePathTwice(): void
    {
        $this->mockHandler->append(new Response(200, [], sprintf('{"id": "%s"}', self::SITE_ID)));

        self::assertSame(self::SITE_ID, $this->siteIdResolver->resolve('ByEncodedPath'));
        self::assertSame(
            'v1.0/sites/contoso.sharepoint.com:/sites/Industrie%20Umgebung',
            (string) $this->mockHandler->getLastRequest()?->getUri(),
        );
    }

    public function testResolveFailsWhenSitePathDoesNotExist(): void
    {
        $this->mockHandler->append(new Response(404, [], '{"error": {"code": "itemNotFound"}}'));

        $this->expectException(SiteNotFoundException::class);

        $this->siteIdResolver->resolve('ByPath');
    }

    public function testResolveKeepsOtherGraphErrors(): void
    {
        $this->mockHandler->append(new Response(504, [], '{}'));

        $this->expectException(ServerException::class);

        $this->siteIdResolver->resolve('ByPath');
    }

    public function testResolveSearchesSiteByDisplayNameWithoutSiteConfig(): void
    {
        $this->mockHandler->append(new Response(200, [], sprintf(
            '{"value": [{"id": "other", "displayName": "IndustrieUmgebung Archiv"}, '
            . '{"id": "%s", "displayName": "IndustrieUmgebung"}]}',
            self::SITE_ID,
        )));

        self::assertSame(self::SITE_ID, $this->siteIdResolver->resolve('BySearch'));
        self::assertSame(
            'v1.0/sites?search=IndustrieUmgebung',
            (string) $this->mockHandler->getLastRequest()?->getUri(),
        );
    }

    public function testResolveSupportsDriveConfigWithoutSiteKey(): void
    {
        $this->mockHandler->append(new Response(200, [], sprintf(
            '{"value": [{"id": "%s", "displayName": "IndustrieUmgebung"}]}',
            self::SITE_ID,
        )));

        self::assertSame(self::SITE_ID, $this->siteIdResolver->resolve('LegacyConfig'));
    }

    public function testResolveFailsWhenSearchFindsNoMatchingSite(): void
    {
        $this->mockHandler->append(new Response(200, [], '{"value": [{"id": "other", "displayName": "Other"}]}'));

        $this->expectException(SiteNotFoundException::class);

        $this->siteIdResolver->resolve('BySearch');
    }

    public function testResolveFailsForUnknownDrive(): void
    {
        $this->expectException(UnknownDriveException::class);

        $this->siteIdResolver->resolve('Unknown');
    }

    public function testResolveCachesResolvedSiteIds(): void
    {
        $this->mockHandler->append(new Response(200, [], sprintf('{"id": "%s"}', self::SITE_ID)));

        $this->siteIdResolver->resolve('ByPath');

        self::assertSame(self::SITE_ID, $this->siteIdResolver->resolve('ByPath'));
        self::assertSame(0, $this->mockHandler->count());
    }
}
