<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Mocks;

use PERSPEQTIVE\FlysystemOneDrive\Token\Token;
use PERSPEQTIVE\FlysystemOneDrive\Token\TokenProviderInterface;

use function array_shift;

final class MockTokenProvider implements TokenProviderInterface
{
    /**
     * @var list<string>
     */
    public array $tokensToReturn = [];

    /**
     * @var array{tenantId: string, clientId: string, clientSecret: string}|null
     */
    public ?array $lastCredentials = null;

    public int $callCount = 0;

    public function getToken(string $tenantId, string $clientId, string $clientSecret): Token
    {
        $this->lastCredentials = [
            'tenantId' => $tenantId,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ];
        ++$this->callCount;

        return new Token((string) array_shift($this->tokensToReturn));
    }
}
