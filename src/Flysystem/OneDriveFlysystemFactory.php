<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Flysystem;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Site;
use PERSPEQTIVE\FlysystemOneDrive\Token\TokenProvider;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OneDriveFlysystemFactory
{
    /**
     * @var array<string,OneDriveAdapter>
     */
    private array $filesystem = [];

    public function __construct(
        private readonly TokenProvider $tokenProvider,
        private readonly HttpClientInterface $httpClient,
        private readonly array $options
    ) {
    }

    /**
     * @throws GuzzleException
     * @throws TransportExceptionInterface
     */
    public function get(string $identifier): OneDriveAdapter
    {
        if ($this->filesystem[$identifier] instanceof OneDriveAdapter === false) {
            $this->filesystem[$identifier] = $this->generateFilesystem($identifier);
        }

        return $this->filesystem[$identifier];
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     * @throws GuzzleException
     */
    private function generateFilesystem(string $identifier): OneDriveAdapter
    {
        if(!isset($this->options['drives'][$identifier])) {
            throw new RuntimeException("Unknown drive {$identifier}");
        }
        $graph = $this->buildGraph($this->options['drives'][$identifier]);
        $siteId = $this->getSiteId($graph, $this->options['drives'][$identifier]['site']);

        return new OneDriveAdapter(
            $graph,
            $siteId . '/drive',
            $this->httpClient,
            [
                'directory_type' => 'sites'
            ]
        );
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function buildGraph(array $options): Graph
    {
        $token = $this->tokenProvider->getToken(
            $options['tenantId'],
            $options['clientId'],
            $options['clientSecret']
        );

        $graph = new Graph();
        $graph->setAccessToken($token->token);

        return $graph;
    }

    /**
     * @throws GuzzleException
     * @throws GraphException
     */
    private function getSiteId(Graph $graph, string $site): string
    {
        $sites = $graph->createRequest('GET', '/sites?search=' . urlencode($site))
            ->setReturnType(Site::class)
            ->execute();

        foreach ($sites as $site) {
            if ($site->getDisplayName() === $site) {
                return $site->getId();
            }
        }

        throw new RuntimeException('Site ' . $site . ' not found');
    }
}
