<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Token\TokenProviderInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class GraphProvider implements GraphProviderInterface
{
    private ?Graph $graph = null;

    public function __construct(
        private readonly TokenProviderInterface $tokenProvider,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly GraphFactoryInterface $graphFactory,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function getGraph(): Graph
    {
        if ($this->graph instanceof Graph === false) {
            $graph = $this->graphFactory->create();
            $graph->setAccessToken($this->fetchToken());
            $this->graph = $graph;
        }

        return $this->graph;
    }

    /**
     * Updates the token of the cached Graph instance in place, so every adapter sharing it uses the new token.
     *
     * @throws ExceptionInterface
     */
    public function refreshGraphToken(): void
    {
        if ($this->graph instanceof Graph === false) {
            $this->getGraph();

            return;
        }

        $this->graph->setAccessToken($this->fetchToken());
    }

    /**
     * @throws ExceptionInterface
     */
    private function fetchToken(): string
    {
        return $this->tokenProvider
            ->getToken($this->tenantId, $this->clientId, $this->clientSecret)
            ->token;
    }
}
