<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Http\GraphCollectionRequest;

use function array_key_exists;
use function is_array;

final class TimeoutAwareGraphCollectionRequest extends GraphCollectionRequest
{
    private const COUNT_PROPERTY = '@odata.count';

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

    /**
     * Unlike the parent implementation the count request uses the same client settings as every other
     * request and signals an unavailable count with an exception instead of a notice.
     *
     * @throws GraphException
     * @throws GuzzleException
     */
    public function count(): int
    {
        $request = new TimeoutAwareGraphRequest(
            $this->clientFactory,
            (float) $this->timeout,
            $this->requestType,
            $this->endpoint . $this->getConcatenator() . '$count=true',
            $this->accessToken,
            $this->baseUrl,
            $this->apiVersion,
            $this->proxyPort,
            (bool) $this->proxyVerifySSL,
        );
        $request->addHeaders($this->headers);

        $result = $request->execute()->getBody();

        if (!is_array($result) || !array_key_exists(self::COUNT_PROPERTY, $result)) {
            throw new GraphException('Count unavailable for this collection');
        }

        return (int) $result[self::COUNT_PROPERTY];
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
