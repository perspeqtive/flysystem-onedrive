<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks;

use GuzzleHttp\ClientInterface;
use PERSPEQTIVE\FlysystemOneDrive\Graph\Http\GuzzleClientFactoryInterface;

final class MockGuzzleClientFactory implements GuzzleClientFactoryInterface
{
    public ClientInterface $clientToReturn;

    /**
     * @var array{
     *     baseUrl: string,
     *     hasHttpErrors: bool,
     *     headers: array<string, string|string[]>,
     *     proxyPort: ?string,
     *     proxyVerifySSL: bool
     * }|null
     */
    public ?array $lastCreateArguments = null;

    public int $createCount = 0;

    public function create(
        string $baseUrl,
        bool $hasHttpErrors,
        array $headers,
        ?string $proxyPort,
        bool $proxyVerifySSL,
    ): ClientInterface {
        $this->lastCreateArguments = [
            'baseUrl' => $baseUrl,
            'hasHttpErrors' => $hasHttpErrors,
            'headers' => $headers,
            'proxyPort' => $proxyPort,
            'proxyVerifySSL' => $proxyVerifySSL,
        ];
        ++$this->createCount;

        return $this->clientToReturn;
    }
}
