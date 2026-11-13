<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Flysystem;

use Exception;
use PERSPEQTIVE\FlysystemOneDrive\Exception\SiteNotFoundException;
use PERSPEQTIVE\FlysystemOneDrive\Exception\UnknownDriveException;
use PERSPEQTIVE\FlysystemOneDrive\Graph\GraphProviderInterface;
use PERSPEQTIVE\FlysystemOneDrive\Site\SiteIdResolverInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_merge;
use function sprintf;

final class OneDriveFlysystemFactory
{
    private const SITE_DIRECTORY_TYPE = 'sites';

    /**
     * @var array<string, OneDriveAdapter>
     */
    private array $filesystem = [];

    /**
     * @param array<string, array{drive: string, options?: array<string, mixed>}> $options
     */
    public function __construct(
        private readonly GraphProviderInterface $graphProvider,
        private readonly HttpClientInterface $httpClient,
        private readonly SiteIdResolverInterface $siteIdResolver,
        private readonly array $options,
    ) {
    }

    /**
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws TransportExceptionInterface
     * @throws UnknownDriveException
     */
    public function get(string $identifier): OneDriveAdapter
    {
        return $this->filesystem[$identifier] ??= $this->generateFilesystem($identifier);
    }

    /**
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws TransportExceptionInterface
     * @throws UnknownDriveException
     */
    private function generateFilesystem(string $identifier): OneDriveAdapter
    {
        if (!isset($this->options[$identifier])) {
            throw new UnknownDriveException(sprintf('Unknown drive "%s".', $identifier));
        }

        $graph = $this->graphProvider->getGraph();
        $siteId = $this->siteIdResolver->resolve($identifier);

        return new OneDriveAdapter(
            $graph,
            $siteId . '/drive',
            $this->httpClient,
            array_merge(
                $this->options[$identifier]['options'] ?? [],
                ['directory_type' => self::SITE_DIRECTORY_TYPE],
            ),
        );
    }
}
