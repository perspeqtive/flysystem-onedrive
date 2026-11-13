<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph\Http;

use GuzzleHttp\ClientInterface;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Http\GraphRequest;

final class TimeoutAwareGraphRequest extends GraphRequest
{
    /**
     * @throws GraphException
     */
    public function __construct(
        private readonly GuzzleClientFactoryInterface $clientFactory,
        float $requestTimeout,
        string $requestType,
        string $endpoint,
        ?string $accessToken,
        string $baseUrl,
        string $apiVersion,
        ?string $proxyPort = null,
        bool $proxyVerifySSL = false,
    ) {
        parent::__construct($requestType, $endpoint, $accessToken, $baseUrl, $apiVersion, $proxyPort, $proxyVerifySSL);

        $this->setTimeout($requestTimeout);
    }

    protected function createGuzzleClient(): ClientInterface
    {
        return $this->clientFactory->create(
            $this->baseUrl,
            (bool) $this->http_errors,
            $this->headers,
            $this->proxyPort,
            (bool) $this->proxyVerifySSL,
        );
    }
}
