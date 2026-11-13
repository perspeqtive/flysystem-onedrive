<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Token;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

interface TokenProviderInterface
{
    /**
     * @throws ExceptionInterface
     */
    public function getToken(string $tenantId, string $clientId, string $clientSecret): Token;
}
