<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive;

use PERSPEQTIVE\FlysystemOneDrive\DependencyInjection\RegisterOneDriveAdaptersPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function preg_match;
use function trim;

final class FlysystemOneDriveAdapterBundle extends AbstractBundle
{
    public const DEFAULT_CONNECT_TIMEOUT = 1.0;

    public const DEFAULT_REQUEST_TIMEOUT = 100.0;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterOneDriveAdaptersPass());
    }

    /**
     * @param array{
     *     credentials: array{tenant_id: ?string, client_id: ?string, client_secret: ?string},
     *     http: array{connect_timeout: float|int, request_timeout: float|int},
     *     drives: array<string, array{
     *         drive: string,
     *         site?: array{hostname: string, path: string},
     *         options: array<string, mixed>
     *     }>
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.yaml');

        $container->parameters()
            ->set('perspeqtive_flysystem.drives', $this->buildDrivesConfig($config['drives']))
            ->set('perspeqtive.flysystem_one_drive.credentials.tenantId', $config['credentials']['tenant_id'] ?? '')
            ->set('perspeqtive.flysystem_one_drive.credentials.clientId', $config['credentials']['client_id'] ?? '')
            ->set(
                'perspeqtive.flysystem_one_drive.credentials.clientSecret',
                $config['credentials']['client_secret'] ?? '',
            )
            ->set('perspeqtive.flysystem_one_drive.http.connect_timeout', (float) $config['http']['connect_timeout'])
            ->set('perspeqtive.flysystem_one_drive.http.request_timeout', (float) $config['http']['request_timeout']);
    }

    /**
     * @param array<string, array{drive: string, site?: array{hostname: string, path: string}, options: array<string, mixed>}> $drives
     *
     * @return array<string, array{drive: string, site: array{hostname: string, path: string}|null, options: array<string, mixed>}>
     */
    private function buildDrivesConfig(array $drives): array
    {
        $drivesConfig = [];

        foreach ($drives as $name => $driveConfig) {
            $drivesConfig[$name] = [
                'drive' => $driveConfig['drive'],
                'site' => $driveConfig['site'] ?? null,
                'options' => $driveConfig['options'],
            ];
        }

        return $drivesConfig;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('credentials')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('tenant_id')->defaultNull()->end()
                        ->scalarNode('client_id')->defaultNull()->end()
                        ->scalarNode('client_secret')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('http')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->floatNode('connect_timeout')
                            ->info(
                                'Seconds to wait for DNS, TCP connect and TLS handshake to Microsoft. '
                                . 'Running transfers are not limited. 0 disables the connect timeout.',
                            )
                            ->defaultValue(self::DEFAULT_CONNECT_TIMEOUT)
                            ->min(0)
                        ->end()
                        ->floatNode('request_timeout')
                            ->info('Total seconds a single Graph API request may take. 0 waits indefinitely.')
                            ->defaultValue(self::DEFAULT_REQUEST_TIMEOUT)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('drives')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('drive')
                                ->info('Display name of the SharePoint site, used for the legacy site search.')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('site')
                                ->info('Resolves the site by its path instead of the slow site search.')
                                ->beforeNormalization()
                                    ->ifNull()
                                    ->thenUnset()
                                ->end()
                                ->children()
                                    ->scalarNode('hostname')
                                        ->info('SharePoint hostname, e.g. "contoso.sharepoint.com".')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->validate()
                                            ->ifTrue(
                                                static fn (string|int|float|bool $hostname): bool
                                                    => preg_match('#[/:\s]#', (string) $hostname) === 1,
                                            )
                                            ->thenInvalid('The hostname %s must not contain a scheme, port or path.')
                                        ->end()
                                    ->end()
                                    ->scalarNode('path')
                                        ->info('Server relative path of the site, e.g. "sites/IndustrieUmgebung". Percent-encoding is optional.')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(static fn (string $path): string => trim($path, '/'))
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('options')
                                ->info('Options passed to the OneDriveAdapter, e.g. "chunk_size".')
                                ->prototype('variable')->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
