<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Http;

use PERSPEQTIVE\FlysystemOneDrive\Exception\ConnectTimeoutException;
use PERSPEQTIVE\FlysystemOneDrive\Http\ConnectTimeoutProgressListener;
use PHPUnit\Framework\TestCase;

final class ConnectTimeoutProgressListenerTest extends TestCase
{
    private const CONNECT_TIMEOUT = 1.0;

    /**
     * @var list<array{int, int, array<string, mixed>}>
     */
    private array $forwardedProgress;

    private ConnectTimeoutProgressListener $progressListener;

    protected function setUp(): void
    {
        $this->forwardedProgress = [];
        $this->progressListener = new ConnectTimeoutProgressListener(
            self::CONNECT_TIMEOUT,
            function (int $downloadedBytes, int $downloadSize, array $info): void {
                $this->forwardedProgress[] = [$downloadedBytes, $downloadSize, $info];
            },
        );
    }

    public function testInvokeAbortsWhenConnectionIsNotEstablishedWithinConnectTimeout(): void
    {
        $this->expectException(ConnectTimeoutException::class);

        ($this->progressListener)(0, 0, ['pretransfer_time' => 0.0, 'total_time' => 1.01, 'url' => 'https://x.test']);
    }

    public function testInvokeDoesNotAbortWhileConnectTimeoutIsNotReached(): void
    {
        ($this->progressListener)(0, 0, ['pretransfer_time' => 0.0, 'total_time' => 0.99]);

        self::assertCount(1, $this->forwardedProgress);
    }

    public function testInvokeNeverAbortsOnceConnectionWasEstablished(): void
    {
        ($this->progressListener)(0, 0, ['pretransfer_time' => 0.2, 'total_time' => 0.2]);
        ($this->progressListener)(10, 100, ['pretransfer_time' => 0.2, 'total_time' => 30.0]);
        ($this->progressListener)(0, 0, ['pretransfer_time' => 0.0, 'total_time' => 31.0]);

        self::assertCount(3, $this->forwardedProgress);
    }

    public function testInvokeTreatsReceivedResponseAsEstablishedConnection(): void
    {
        ($this->progressListener)(0, 0, ['http_code' => 200, 'total_time' => 5.0]);

        self::assertCount(1, $this->forwardedProgress);
    }

    public function testInvokeForwardsProgressToOriginalListener(): void
    {
        $info = ['pretransfer_time' => 0.1, 'total_time' => 0.3];

        ($this->progressListener)(42, 100, $info);

        self::assertSame([[42, 100, $info]], $this->forwardedProgress);
    }

    public function testInvokeWorksWithoutOriginalListener(): void
    {
        $progressListener = new ConnectTimeoutProgressListener(self::CONNECT_TIMEOUT);

        $this->expectException(ConnectTimeoutException::class);

        $progressListener(0, 0, ['total_time' => 2.0]);
    }
}
