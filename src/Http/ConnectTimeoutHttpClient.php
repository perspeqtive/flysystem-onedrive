<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Http;

use Closure;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Symfony HttpClient has no connect timeout option, its "timeout" is an idle timeout. This decorator aborts
 * requests whose connection could not be established within the connect timeout. A connect timeout of 0
 * disables the check. An "on_progress" default passed to withOptions() is kept by the decorator and chained
 * whenever a request does not define its own "on_progress".
 */
final readonly class ConnectTimeoutHttpClient implements HttpClientInterface, ResetInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private float $connectTimeout,
        private ?Closure $defaultOnProgress = null,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->connectTimeout > 0.0) {
            $options['on_progress'] = new ConnectTimeoutProgressListener(
                $this->connectTimeout,
                $options['on_progress'] ?? $this->defaultOnProgress,
            );
        }

        return $this->httpClient->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        if ($this->connectTimeout <= 0.0 || !isset($options['on_progress'])) {
            return new self($this->httpClient->withOptions($options), $this->connectTimeout, $this->defaultOnProgress);
        }

        $defaultOnProgress = $options['on_progress'](...);
        unset($options['on_progress']);

        return new self($this->httpClient->withOptions($options), $this->connectTimeout, $defaultOnProgress);
    }

    public function reset(): void
    {
        if ($this->httpClient instanceof ResetInterface) {
            $this->httpClient->reset();
        }
    }
}
