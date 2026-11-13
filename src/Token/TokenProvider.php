<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Token;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class TokenProvider implements TokenProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function getToken(string $tenantId, string $clientId, string $clientSecret): Token
    {
        $response = $this->httpClient->request('POST', "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
            'body' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);
        $token = json_decode((string) $response->getContent(), true)['access_token'] ?? null;
        return new Token($token);
    }
}
