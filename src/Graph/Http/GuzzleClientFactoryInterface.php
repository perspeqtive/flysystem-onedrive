<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Graph\Http;

use GuzzleHttp\ClientInterface;

interface GuzzleClientFactoryInterface
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function create(
        string $baseUrl,
        bool $hasHttpErrors,
        array $headers,
        ?string $proxyPort,
        bool $proxyVerifySSL,
    ): ClientInterface;
}
