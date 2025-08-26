<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Token;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class TokenProvider
{

    public function __construct(
        private HttpClientInterface $client,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function getToken(string $tenantId, string $clientId, string $clientSecret): Token
    {

        $response = $this->client->request('POST', "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
            'body3' => [
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
