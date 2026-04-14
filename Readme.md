# Клиент API Диадок для PHP

PHP-библиотека для работы с API Диадок (protobuf + HTTP), совместимая с PHP 7.1.

## Происхождение и доработки

За основу взят модуль [`magdv/diadoc-php`](https://github.com/magdv/diadoc-php).  
В рамках этого репозитория выполнены ключевые доработки:

- перевод авторизации на OpenID Connect (Authorization Code Flow) вместо устаревшего `DiadocAuth`/`/Authenticate`;
- добавлен `Bearer`-заголовок и обработка жизненного цикла `access_token`/`refresh_token`;
- добавлено проактивное и реактивное обновление токена (refresh);
- обновлены примеры, тестовая обвязка и конфигурация окружения;
- добавлен локальный тестовый OAuth-сервер для сценария CLI + браузер.

**Механизм авторизации актуальный на 14.04.2026**

Документация API Диадок:
- [Интеграция с API](https://developer.kontur.ru/Docs/diadoc-api/howtostart/integration.html)
- [Авторизация (OIDC)](https://developer.kontur.ru/docs/diadoc-api/authentication.html)

## Требования

- PHP `>=7.1.3` (проект тестируется на `7.1.33`);
- расширения `ext-curl`, `ext-json`;
- `composer`;
- для генерации классов из proto: установленный `protoc`.

## Установка

```bash
composer install
```

## Быстрый пример использования

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use MagDv\Diadoc\DiadocApi;

$clientId = 'ваш-client_id';
$clientSecret = 'ваш-client_secret';
$redirectUri = 'https://ваше-приложение/oauth/callback';

$api = new DiadocApi(
    $clientId,
    $clientSecret,
    'https://diadoc-api.kontur.ru/' // или тестовый URL
);

// 1) Получить URL входа и отправить пользователя в браузер:
$loginUrl = $api->buildAuthorizationUrl($redirectUri, 'state-value', null);

// 2) В обработчике callback после редиректа с ?code=...:
// $api->exchangeAuthorizationCode($_GET['code'], $redirectUri);

// 3) Или восстановить ранее сохраненную сессию:
// $api->setOAuthSession($accessToken, $refreshToken, $expiresAtUnix);

$orgList = $api->getMyOrganizations();
echo $orgList->getOrganizations()[0]->getOrgId();
```

## Конфигурация окружения

Пример в `.env.example`.

Основные переменные:

- `OAUTH_CLIENT_ID` — `client_id` приложения из кабинета интегратора;
- `OAUTH_CLIENT_SECRET` — `client_secret` приложения;
- `OAUTH_IDENTITY_URL` — URL identity-провайдера (обычно `https://identity.kontur.ru`);
- `DIADOC_URL` — базовый URL API Диадок (prod / staging / test);
- `OAUTH_REDIRECT_URI` — redirect для OAuth callback (для локального теста по умолчанию `http://127.0.0.1:8765/oauth/callback`);
- `ORG_ID`, `FROM_BOX_ID`, `TO_BOX_ID` — данные для интеграционных тестов.

Опционально (ручная подстановка токенов):

- `DIADOC_ACCESS_TOKEN`
- `DIADOC_REFRESH_TOKEN`
- `DIADOC_ACCESS_EXPIRES_AT` (unix timestamp)

## Развертывание (локально через Docker)

В проекте есть `docker-compose.yml` с сервисами:

- `cli` — запуск скриптов, smoke и интеграционных проверок;
- `oauth-test` (профиль `oauth`) — встроенный PHP-сервер для получения OAuth-токена через браузер.

### Запуск CLI-среды

```bash
docker compose run --rm cli composer install
```

### Запуск тестового OAuth-сервера

```bash
docker compose --profile oauth up --build oauth-test
```

После запуска откройте в браузере:

- `http://127.0.0.1:8765/`

Важно:

- в кабинете интегратора должен быть зарегистрирован **ровно тот же** `redirect_uri`, что и `OAUTH_REDIRECT_URI`;
- для локального сценария по умолчанию: `http://127.0.0.1:8765/oauth/callback`.

После успешного callback сервер:

- сохраняет OAuth-сессию в `var/oauth_last_session.json`;
- сохраняет кэш-сессию в `var/*.cache` (формат совместим с `tests/helpers/ApiClient.php`).

## Тестирование

### 1) Smoke/compat тест

Проверяет:

- PHP/расширения;
- синтаксис `src/` и `phpProto/`;
- автозагрузку;
- базовый protobuf roundtrip;
- критичные конструкторы/хелперы.

Запуск:

```bash
docker compose run --rm cli composer run test:compat
```

### 2) Интеграционный тест с реальным API

Пример проверки чтения контрагентов:

```bash
docker compose run --rm cli php -r "require 'vendor/autoload.php'; use Test\helpers\ApiClient; \$api = (new ApiClient())->getApi(); \$orgId = getenv('ORG_ID'); \$counteragents = \$api->getCountragentsV2(\$orgId); echo 'totalCount=' . \$counteragents->getTotalCount() . PHP_EOL;"
```

Если приходит `Organization ... is not found`, обновите `ORG_ID` значением из `getMyOrganizations()` текущего пользователя в той же среде (`DIADOC_URL`).

## Разработка

Полезные команды:

- `composer run generate-proto` — генерация PHP-классов из `proto/`;
- `composer run test:compat` — smoke-проверка совместимости.

## Генерация классов из proto

Поток работы:

1. Обновить/добавить схемы в `proto/`;
2. Выполнить `composer run generate-proto`;
3. Проверить сгенерированный код в `phpProto/`;
4. Обновить использование новых полей/методов в `src/`.