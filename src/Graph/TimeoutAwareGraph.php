<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph;

use Microsoft\Graph\Core\GraphConstants;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\GuzzleClientFactoryInterface;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphCollectionRequest;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\TimeoutAwareGraphRequest;

/**
 * The parent keeps its connection settings private, so they are mirrored here to create requests that use
 * the configured connect and request timeouts.
 */
final class TimeoutAwareGraph extends Graph
{
    private ?string $accessToken = null;

    private string $apiVersion = GraphConstants::API_VERSION;

    private string $baseUrl = GraphConstants::REST_ENDPOINT;

    private ?string $proxyPort = null;

    private bool $proxyVerifySSL = false;

    public function __construct(
        private readonly GuzzleClientFactoryInterface $clientFactory,
        private readonly float $requestTimeout,
    ) {
        parent::__construct();
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl): self
    {
        parent::setBaseUrl($baseUrl);
        $this->baseUrl = (string) $baseUrl;

        return $this;
    }

    /**
     * @param string $apiVersion
     */
    public function setApiVersion($apiVersion): self
    {
        parent::setApiVersion($apiVersion);
        $this->apiVersion = (string) $apiVersion;

        return $this;
    }

    /**
     * @param string|null $accessToken
     */
    public function setAccessToken($accessToken): self
    {
        parent::setAccessToken($accessToken);
        $this->accessToken = $accessToken === null ? null : (string) $accessToken;

        return $this;
    }

    /**
     * @param string|int|null $port
     * @param bool $verifySSL
     */
    public function setProxyPort($port, $verifySSL = false): self
    {
        parent::setProxyPort($port, $verifySSL);
        $this->proxyPort = $port === null ? null : (string) $port;
        $this->proxyVerifySSL = (bool) $verifySSL;

        return $this;
    }

    /**
     * @param string $requestType
     * @param string $endpoint
     *
     * @throws GraphException
     */
    public function createRequest($requestType, $endpoint): TimeoutAwareGraphRequest
    {
        return new TimeoutAwareGraphRequest(
            $this->clientFactory,
            $this->requestTimeout,
            $requestType,
            $endpoint,
            $this->accessToken,
            $this->baseUrl,
            $this->apiVersion,
            $this->proxyPort,
            $this->proxyVerifySSL,
        );
    }

    /**
     * @param string $requestType
     * @param string $endpoint
     *
     * @throws GraphException
     */
    public function createCollectionRequest($requestType, $endpoint): TimeoutAwareGraphCollectionRequest
    {
        return new TimeoutAwareGraphCollectionRequest(
            $this->clientFactory,
            $this->requestTimeout,
            $requestType,
            $endpoint,
            $this->accessToken,
            $this->baseUrl,
            $this->apiVersion,
            $this->proxyPort,
            $this->proxyVerifySSL,
        );
    }
}
