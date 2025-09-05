<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Token\TokenProvider;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GraphProvider implements GraphProviderInterface
{

    private ?Graph $graph = null;

    public function __construct(
        private readonly TokenProvider $tokenProvider,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,

    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getGraph(): Graph {
        if ($this->graph instanceof Graph === false) {
            $this->graph = new Graph();
            $this->graph->setAccessToken($this->fetchToken());
        }
        return $this->graph;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function refreshGraphToken(): void {
        $this->graph->setAccessToken(
            $this->fetchToken()
        );
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function fetchToken(): string
    {
        $token = $this->tokenProvider->getToken(
            $this->tenantId,
            $this->clientId,
            $this->clientSecret
        );
        return $token->token;
    }

}