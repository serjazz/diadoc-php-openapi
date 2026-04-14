<?php

declare(strict_types=1);

namespace Test\Unit;

use MagDv\Diadoc\DiadocApi;
use MagDv\Diadoc\Exception\DiadocApiException;
use Test\base\BaseTest;
use Test\enums\ConfigNames;

class AuthTest extends BaseTest
{
    public function testBuildAuthorizationUrlUsesStagingScopeForStagingHost(): void
    {
        $api = new DiadocApi('my-client', 'my-secret', 'https://diadoc-api-staging.kontur.ru/');
        $url = $api->buildAuthorizationUrl('https://app.test/oauth/callback', 's1', 'n1');

        self::assertStringContainsString('https://identity.kontur.ru/connect/authorize?', $url);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $parts);
        self::assertSame('code', $parts['response_type'] ?? null);
        self::assertSame('my-client', $parts['client_id'] ?? null);
        self::assertSame('s1', $parts['state'] ?? null);
        self::assertSame('n1', $parts['nonce'] ?? null);
        $scopes = preg_split('/\s+/', trim(str_replace('+', ' ', $parts['scope'] ?? '')));
        self::assertTrue(in_array('Diadoc.PublicAPI.Staging', $scopes, true));
    }

    public function testBuildAuthorizationUrlUsesStagingScopeForTestHost(): void
    {
        $api = new DiadocApi('my-client', 'my-secret', 'https://diadoc-api-test.kontur.ru/');
        $url = $api->buildAuthorizationUrl('https://app.test/oauth/callback', 's2', 'n2');

        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $parts);
        $scopes = preg_split('/\s+/', trim(str_replace('+', ' ', $parts['scope'] ?? '')));
        self::assertTrue(in_array('Diadoc.PublicAPI.Staging', $scopes, true));
    }

    public function testBuildAuthorizationUrlUsesProductionScopeForProductionHost(): void
    {
        $api = new DiadocApi('my-client', 'my-secret', 'https://diadoc-api.kontur.ru/');
        $url = $api->buildAuthorizationUrl('https://app.test/oauth/callback', 'st', null);

        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $parts);
        self::assertArrayHasKey('scope', $parts);
        $scopes = preg_split('/\s+/', trim(str_replace('+', ' ', $parts['scope'])));
        self::assertTrue(in_array('Diadoc.PublicAPI', $scopes, true));
        self::assertFalse(in_array('Diadoc.PublicAPI.Staging', $scopes, true));
    }

    public function testExchangeAuthorizationCodeWithInvalidCodeThrows(): void
    {
        $clientId = getenv(ConfigNames::OAUTH_CLIENT_ID);
        $secret = getenv(ConfigNames::OAUTH_CLIENT_SECRET);
        if ($clientId === false || $clientId === '' || $secret === false || $secret === '') {
            self::markTestSkipped('Нужны OAUTH_CLIENT_ID и OAUTH_CLIENT_SECRET в .env для запроса к identity.kontur.ru');
        }

        $api = new DiadocApi(
            $clientId,
            $secret,
            getenv(ConfigNames::DIADOC_URL) !== false && getenv(ConfigNames::DIADOC_URL) !== ''
                ? getenv(ConfigNames::DIADOC_URL)
                : 'https://diadoc-api.kontur.ru/'
        );

        $this->expectException(DiadocApiException::class);
        $api->exchangeAuthorizationCode('invalid-code-not-issued-by-server', 'https://localhost/oauth/callback');
    }
}
