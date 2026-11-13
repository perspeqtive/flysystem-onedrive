<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Http;

use Closure;
use PERSPEQTIVE\FlysystemOneDrive\Exception\ConnectTimeoutException;

use function sprintf;

/**
 * Aborts a request whose connection (DNS, TCP and TLS) could not be established within the connect timeout.
 * The "pretransfer_time" is used instead of "connect_time" because the latter stays 0 on reused connections.
 * Once a connection has been established the listener never aborts again, so slow transfers are bounded
 * by the regular idle timeout of the http client only.
 */
final class ConnectTimeoutProgressListener
{
    private bool $isConnected = false;

    private readonly ?Closure $onProgress;

    public function __construct(
        private readonly float $connectTimeout,
        ?callable $onProgress = null,
    ) {
        $this->onProgress = $onProgress === null ? null : $onProgress(...);
    }

    /**
     * @param array<string, mixed> $info
     *
     * @throws ConnectTimeoutException
     */
    public function __invoke(int $downloadedBytes, int $downloadSize, array $info): void
    {
        $this->guardConnectTimeout($info);

        if ($this->onProgress !== null) {
            ($this->onProgress)($downloadedBytes, $downloadSize, $info);
        }
    }

    /**
     * @param array<string, mixed> $info
     *
     * @throws ConnectTimeoutException
     */
    private function guardConnectTimeout(array $info): void
    {
        if ($this->isConnected) {
            return;
        }

        if ((float) ($info['pretransfer_time'] ?? 0.0) > 0.0 || (int) ($info['http_code'] ?? 0) > 0) {
            $this->isConnected = true;

            return;
        }

        if ((float) ($info['total_time'] ?? 0.0) <= $this->connectTimeout) {
            return;
        }

        throw new ConnectTimeoutException(sprintf(
            'Connection to "%s" could not be established within %s seconds.',
            (string) ($info['url'] ?? 'unknown url'),
            $this->connectTimeout,
        ));
    }
}
