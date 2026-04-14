Клиент API для diadoc.ru
---------------------------

Клиент API diadoc.ru.

За основу был взят другой клиент.
https://github.com/agentsib/diadoc-php

Работа над ним давно тормознулась и пришлось добавлять новые методы, чтобы оно заработало.

Документация
https://developer.kontur.ru/Docs/diadoc-api/http/PostMessage.html

## Пример

Авторизация — по [OpenID Connect (Authorization Code)](https://developer.kontur.ru/docs/diadoc-api/authentication.html): получите `code` на `redirect_uri`, затем обменяйте его на токены.

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$clientId = 'ваш-client_id';
$clientSecret = 'ваш-client_secret';
$redirectUri = 'https://ваше-приложение/oauth/callback';

$api = new \MagDv\Diadoc\DiadocApi(
    $clientId,
    $clientSecret,
    'https://diadoc-api.kontur.ru/'
);

// 1) Отправьте пользователя в браузере на этот URL:
$loginUrl = $api->buildAuthorizationUrl($redirectUri, 'произвольный-state', null);

// 2) В обработчике callback после редиректа с ?code=...:
// $api->exchangeAuthorizationCode($_GET['code'], $redirectUri);

// Если токены уже есть (из кеша):
// $api->setOAuthSession($accessToken, $refreshToken, $expiresAtUnix);

// Дальше нужен действующий access_token (после exchange или setOAuthSession).

// выводим список контрагентов нашей организации
$orgId = 'ламлвоалоывлолыовлаоыловалоыва';
$contragents = $api->getCountragentsV2($orgId);

// количество контрагентов
var_dump($contragents->getTotalCount());

/** @var Diadoc\Proto\Counteragent $item */
foreach ($contragents->getCounteragents() as $item) {
    $org = $item->getOrganization();
    // пример вывода данных из ответа
    if ($org) {
        $d = [];
        $d['konturId'] = $org->getOrgId();
        $d['inn'] = $org->getInn();
        $d['fullName'] = $org->getFullName();
        $d['shortName'] = $org->getShortName();
        $d['kpp'] = $org->getKpp();
        $d['ogrn'] = $org->getOgrn();
        $d['isRoaming'] = $org->getIsRoaming();
    }
    var_dump($d);
}
```


## Тесты

     Тест не дает полной картины работоспособности апи. 
     Мы не можем быть уверены, что нам всегда возвращают нужные данные, т.к. стенд тестовый.
     Тут я скорее проверяют, что обращаюсь куда надо и что плюс-минус все работает.

## Как вести разработку

В композере я подключил скрипты:
- Для кодстайла `composer fix-style`
- Генерация php классов из proto файлов `composer generate-proto`. Чтобы генерация работала, надо чтобы в системе был установлен `protobuf`
- Запуск Ректора `composer rector` (подключил для разовой помощи, но решил оставить)

Можно также использовать `Makefile` для всех перечисленных выше возможностей.

## Генерация php классов из proto файлов

Вся логика по выборке прото файлов находится в файле `testAuth.php`. 
Если что - то новое появилось в описании апи диадока или вдруг тупо не хватает, то надо это изменить сначала в прото файлах.
- Идем в каталог `proto` тут ищем необходимое или добавляем новое.
- Запукаем `composer generate-proto`
- Смотрим, что у нас сгенерировалось в папке `phpProto`
- Теперь надо заиспользовать новые поля в нашем коде.

Можно также использовать `Makefile` для всех перечисленных выше возможностей.

## Генерация тестового сертификата
https://losst.pro/sozdanie-sertifikata-openssl