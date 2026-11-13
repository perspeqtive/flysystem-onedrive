<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit;

use PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveAdapter;
use PERSPEQTIVE\FlysystemOneDrive\Flysystem\OneDriveFlysystemFactory;
use PERSPEQTIVE\FlysystemOneDrive\FlysystemOneDriveAdapterBundle;
use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProvider;
use PERSPEQTIVE\FlysystemOneDrive\Graph\TimeoutAwareGraph;
use PERSPEQTIVE\FlysystemOneDrive\Http\ConnectTimeoutHttpClient;
use PERSPEQTIVE\FlysystemOneDrive\Site\GraphSiteIdResolver;
use PERSPEQTIVE\FlysystemOneDrive\Site\SiteIdResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function sys_get_temp_dir;

final class FlysystemOneDriveAdapterBundleTest extends TestCase
{
    private const PUBLIC_SERVICE_IDS = [
        'perspeqtive.flysystem_one_drive.http_client',
        'perspeqtive.flysystem_one_drive.graph.graph_provider',
        'perspeqtive.flysystem_one_drive.site.site_id_resolver',
        'perspeqtive.flysystem_one_drive.flysystem.one_drive_flysystem_factory',
    ];

    private ContainerBuilder $container;

    private FlysystemOneDriveAdapterBundle $bundle;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
        $this->container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->container->setParameter('kernel.debug', false);
        $this->container->register(HttpClientInterface::class)->setSynthetic(true)->setPublic(true);
        $this->bundle = new FlysystemOneDriveAdapterBundle();
    }

    public function testLoadUsesDefaultTimeoutsWithLegacyConfig(): void
    {
        $this->loadConfig([
            'credentials' => ['tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => 'secret'],
            'drives' => ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung']],
        ]);

        self::assertSame(1.0, $this->container->getParameter('perspeqtive.flysystem_one_drive.http.connect_timeout'));
        self::assertSame(100.0, $this->container->getParameter('perspeqtive.flysystem_one_drive.http.request_timeout'));
        self::assertSame(
            ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung', 'site' => null, 'options' => []]],
            $this->container->getParameter('perspeqtive_flysystem.drives'),
        );
    }

    public function testLoadNormalizesHttpAndSiteConfig(): void
    {
        $this->loadConfig([
            'http' => ['connect_timeout' => 2, 'request_timeout' => 30.5],
            'drives' => [
                'IndustrieUmgebung' => [
                    'drive' => 'IndustrieUmgebung',
                    'site' => ['hostname' => 'contoso.sharepoint.com', 'path' => '/sites/IndustrieUmgebung/'],
                    'options' => ['chunk_size' => 3276800],
                ],
            ],
        ]);

        self::assertSame(2.0, $this->container->getParameter('perspeqtive.flysystem_one_drive.http.connect_timeout'));
        self::assertSame(30.5, $this->container->getParameter('perspeqtive.flysystem_one_drive.http.request_timeout'));
        self::assertSame(
            [
                'IndustrieUmgebung' => [
                    'drive' => 'IndustrieUmgebung',
                    'site' => ['hostname' => 'contoso.sharepoint.com', 'path' => 'sites/IndustrieUmgebung'],
                    'options' => ['chunk_size' => 3276800],
                ],
            ],
            $this->container->getParameter('perspeqtive_flysystem.drives'),
        );
    }

    public function testLoadTreatsNullSiteAsLegacyConfig(): void
    {
        $this->loadConfig([
            'drives' => ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung', 'site' => null]],
        ]);

        self::assertSame(
            ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung', 'site' => null, 'options' => []]],
            $this->container->getParameter('perspeqtive_flysystem.drives'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideInvalidConfigs')]
    public function testLoadRejectsInvalidConfig(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadConfig($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidConfigs(): iterable
    {
        yield 'negative connect timeout' => [['http' => ['connect_timeout' => -1]]];
        yield 'hostname with scheme' => [[
            'drives' => [
                'Drive' => ['drive' => 'Drive', 'site' => ['hostname' => 'https://contoso.sharepoint.com', 'path' => 'sites/a']],
            ],
        ]];
        yield 'empty site path' => [[
            'drives' => [
                'Drive' => ['drive' => 'Drive', 'site' => ['hostname' => 'contoso.sharepoint.com', 'path' => '/']],
            ],
        ]];
        yield 'site without hostname' => [[
            'drives' => ['Drive' => ['drive' => 'Drive', 'site' => ['path' => 'sites/a']]],
        ]];
    }

    public function testContainerWiresServicesWithConnectTimeoutHttpClient(): void
    {
        $this->loadConfig([
            'credentials' => ['tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => 'secret'],
            'drives' => ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung']],
        ]);
        $this->bundle->build($this->container);
        foreach (self::PUBLIC_SERVICE_IDS as $serviceId) {
            $this->container->getDefinition($serviceId)->setPublic(true);
        }
        $this->container->getAlias(SiteIdResolverInterface::class)->setPublic(true);
        $this->container->compile();
        $this->container->set(
            HttpClientInterface::class,
            new MockHttpClient(new MockResponse('{"access_token": "token"}')),
        );

        $graphProvider = $this->container->get('perspeqtive.flysystem_one_drive.graph.graph_provider');

        self::assertInstanceOf(
            ConnectTimeoutHttpClient::class,
            $this->container->get('perspeqtive.flysystem_one_drive.http_client'),
        );
        self::assertInstanceOf(GraphProvider::class, $graphProvider);
        self::assertInstanceOf(TimeoutAwareGraph::class, $graphProvider->getGraph());
        self::assertInstanceOf(GraphSiteIdResolver::class, $this->container->get(SiteIdResolverInterface::class));
        self::assertInstanceOf(
            OneDriveFlysystemFactory::class,
            $this->container->get('perspeqtive.flysystem_one_drive.flysystem.one_drive_flysystem_factory'),
        );
        self::assertTrue($this->container->has('perspeqtive_flysystem.onedriveadapter.IndustrieUmgebung'));
    }

    public function testBuildRegistersAdapterServicePerDrive(): void
    {
        $this->loadConfig(['drives' => ['IndustrieUmgebung' => ['drive' => 'IndustrieUmgebung']]]);
        $this->bundle->build($this->container);
        $this->container->getCompilerPassConfig()->setRemovingPasses([]);
        $this->container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $this->container->compile();

        $definition = $this->container->getDefinition('perspeqtive_flysystem.onedriveadapter.IndustrieUmgebung');

        self::assertSame(OneDriveAdapter::class, $definition->getClass());
        self::assertTrue($definition->isPublic());
        self::assertSame(['IndustrieUmgebung'], $definition->getArguments());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadConfig(array $config): void
    {
        $this->bundle->getContainerExtension()?->load([$config], $this->container);
    }
}
