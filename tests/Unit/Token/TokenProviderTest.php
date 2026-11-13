<?php

declare(strict_types=1);

namespace PERSPEQTIVE\FlysystemOneDrive\Test\Unit\Token;

use PERSPEQTIVE\FlysystemOneDrive\Token\TokenProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TokenProviderTest extends TestCase
{
    private MockResponse $tokenResponse;

    private TokenProvider $tokenProvider;

    protected function setUp(): void
    {
        $this->tokenResponse = new MockResponse('{"access_token": "access-token"}');
        $this->tokenProvider = new TokenProvider(new MockHttpClient($this->tokenResponse));
    }

    public function testGetTokenRequestsClientCredentialsToken(): void
    {
        $token = $this->tokenProvider->getToken('tenant-id', 'client-id', 'client-secret');

        self::assertSame('access-token', $token->token);
        self::assertSame('POST', $this->tokenResponse->getRequestMethod());
        self::assertSame(
            'https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token',
            $this->tokenResponse->getRequestUrl(),
        );
        self::assertStringContainsString('grant_type=client_credentials', $this->tokenResponse->getRequestOptions()['body']);
    }
}
