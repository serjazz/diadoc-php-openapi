<?php

declare(strict_types=1);

namespace Test\helpers;

use MagDv\Diadoc\DiadocApi;
use MagDv\Diadoc\Signer\OpensslSignerProvider;
use Test\enums\ConfigNames;

class ApiClient
{
    /**
     * @var DiadocApi|null
     */
    private $api;

    /**
     * @var SimpleFileCache
     */
    private $cache;

    /**
     * @return DiadocApi
     */
    public function getApi()
    {
        $base = dirname(__DIR__);
        $caFile = $base . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'domain.crt';
        $certFile = $base . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'domain.csr';
        $keyFile = $base . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'domain.key';
        $signedProvider = new OpensslSignerProvider($caFile, $certFile, $keyFile);

        if ($this->api === null) {
            $clientId = getenv(ConfigNames::OAUTH_CLIENT_ID);
            $clientSecret = getenv(ConfigNames::OAUTH_CLIENT_SECRET);
            if ($clientId === false || $clientId === '' || $clientSecret === false || $clientSecret === '') {
                throw new \RuntimeException('В .env должны быть заданы OAUTH_CLIENT_ID и OAUTH_CLIENT_SECRET.');
            }

            $identityUrl = getenv(ConfigNames::OAUTH_IDENTITY_URL);
            $identityUrl = $identityUrl !== false && $identityUrl !== '' ? $identityUrl : 'https://identity.kontur.ru';

            $diadocUrl = getenv(ConfigNames::DIADOC_URL);
            $diadocUrl = ($diadocUrl !== false && $diadocUrl !== '') ? $diadocUrl : 'https://diadoc-api.kontur.ru/';

            $this->api = new DiadocApi(
                $clientId,
                $clientSecret,
                $diadocUrl,
                $identityUrl,
                false,
                $signedProvider
            );
            $this->cache = Cache::getCache();
            $self = $this;
            $this->api->setOAuthSessionPersistenceCallback(static function (array $session) use ($self) {
                $cacheExp = time() + 86400 * 30;
                if (!empty($session['expires_at'])) {
                    $cacheExp = max($cacheExp, (int) $session['expires_at'] + 7200);
                }
                $self->cache->set(
                    ConfigNames::DIADOC_OAUTH_CACHE_KEY,
                    json_encode($session, JSON_UNESCAPED_UNICODE),
                    $cacheExp
                );
            });
        }

        $this->restoreOrLoadOAuthSession();

        return $this->api;
    }

    private function restoreOrLoadOAuthSession(): void
    {
        $cached = $this->cache->get(ConfigNames::DIADOC_OAUTH_CACHE_KEY);
        if ($cached !== false && is_string($cached)) {
            $data = json_decode($cached, true);
            if (is_array($data) && !empty($data['access_token'])) {
                $this->api->setOAuthSession(
                    $data['access_token'],
                    isset($data['refresh_token']) ? $data['refresh_token'] : null,
                    isset($data['expires_at']) ? (int) $data['expires_at'] : null
                );

                return;
            }
        }

        $access = getenv(ConfigNames::DIADOC_ACCESS_TOKEN);
        if ($access !== false && $access !== '') {
            $exp = getenv(ConfigNames::DIADOC_ACCESS_EXPIRES_AT);
            $this->api->setOAuthSession(
                $access,
                getenv(ConfigNames::DIADOC_REFRESH_TOKEN) ?: null,
                $exp !== false && $exp !== '' ? (int) $exp : null
            );

            return;
        }

        throw new \RuntimeException(
            'Нет OAuth-сессии: заполните в .env OAUTH_CLIENT_ID / OAUTH_CLIENT_SECRET и '
            . 'либо положите токены в кеш (ключ ' . ConfigNames::DIADOC_OAUTH_CACHE_KEY . '), '
            . 'либо задайте DIADOC_ACCESS_TOKEN (и при необходимости DIADOC_REFRESH_TOKEN, DIADOC_ACCESS_EXPIRES_AT). '
            . 'Первичное получение кода: откройте buildAuthorizationUrl() в браузере и выполните exchangeAuthorizationCode().'
        );
    }
}
