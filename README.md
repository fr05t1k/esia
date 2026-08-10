
# Единая система идентификации и аутентификации (ЕСИА) OpenId 

[![CI](https://github.com/fr05t1k/esia/actions/workflows/ci.yml/badge.svg)](https://github.com/fr05t1k/esia/actions/workflows/ci.yml)

# Описание
Компонент для авторизации на портале "Госуслуги".

# Требования
PHP >= 8.3, расширения `ext-openssl` и `ext-json`.

Библиотека не привязана к конкретному HTTP-клиенту и использует стандарты
PSR-18 (HTTP Client) и PSR-17 (HTTP Factories). Необходимо установить любую
их реализацию, например Guzzle:

```
composer require guzzlehttp/guzzle
```

Подойдёт любой PSR-18 клиент (Guzzle, Symfony HTTP Client, и т.д.) вместе с
PSR-17 фабрикой (`nyholm/psr7`, `guzzlehttp/psr7` и т.п.). Реализация
находится автоматически (через `php-http/discovery`), либо её можно передать
явно в конструктор `OpenId`:

```php
$esia = new \Esia\OpenId($config, $psr18Client, $psr17RequestFactory, $psr17StreamFactory);
```

# Обратная совместимость
Начиная с этой версии значения по умолчанию обновлены под актуальный API ЕСИА
(Методические рекомендации): эндпоинты `aas/oauth2/v2/ac` и `aas/oauth2/v3/te`,
`portalUrl` использует `https://`. Актуальный `aas/oauth2/v3/te` требует параметр
`clientCertificateHash`. Если вы полагались на прежние значения по умолчанию
(`aas/oauth2/ac`, `aas/oauth2/te`, `http://`), задайте их явно через `Config`.

Обновляетесь с 2.x? См. подробное [руководство по обновлению 2.4.2 → 3.0.0](UPGRADE.md).

# Внимание!
Получив токен вы можете выполнять любые API запросы. Библиотека не поддерживает все существующие методы в API, а предоставляет только самые базовые. Основная цель библиотеки - получение токена.

# Установка

При помощи [composer](https://getcomposer.org/download/):
```
composer require --prefer-dist fr05t1k/esia
```
Или добавьте в composer.json

```
"fr05t1k/esia" : "^3.0"
```

# Как использовать 

Пример получения ссылки для авторизации
```php
<?php 
$config = new \Esia\Config([
  'clientId' => 'INSP03211',
  'redirectUrl' => 'http://my-site.com/response.php',
  'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
  'scope' => ['fullname', 'birthdate'],
]);
$esia = new \Esia\OpenId($config);
$esia->setSigner(new \Esia\Signer\SignerPKCS7(
    'my-site.com.pem',
    'my-site.com.pem',
    'password',
    '/tmp'
));
?>

<a href="<?=$esia->buildUrl()?>">Войти через портал госуслуги</a>
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```php

$esia = new \Esia\OpenId($config);

// Вы можете использовать токен в дальнейшем вместе с oid 
$token = $esia->getToken($_GET['code']);

$personInfo = $esia->getPersonInfo();
$addressInfo = $esia->getAddressInfo();
$contactInfo = $esia->getContactInfo();
$documentInfo = $esia->getDocInfo();

// Организации и роли пользователя (нужен полученный токен):
$roles = $esia->getRoles();               // членство в организациях + роли (одним запросом)
$organizations = $esia->getOrganizations(); // подробные данные по каждой организации

```

## Организации и роли

Метод `getRoles()` возвращает список организаций, в которых состоит пользователь,
вместе с ролями (эндпоинт `rs/prns/{oid}/roles`). Данные приходят одним запросом —
каждый элемент содержит `oid` организации, краткое/полное наименование, ОГРН,
флаги `chief`/`admin` и т.д. Если пользователь не состоит ни в одной организации,
возвращается пустой массив.

Метод `getOrganizations()` использует коллекцию `rs/prns/{oid}/orgs`, элементы
которой — ссылки на ресурсы организаций; каждая ссылка догружается и возвращается
полными данными организации.

```php
// Токен уже получен через getToken()
$roles = $esia->getRoles();
foreach ($roles as $role) {
    echo $role['shortName'], PHP_EOL;
}

$organizations = $esia->getOrganizations();
```

## OAuth state (защита от CSRF)

`state` — одноразовый идентификатор запроса авторизации. Библиотека генерирует
его автоматически при вызове `buildUrl()`/`getToken()` и сохраняет в `Config`,
поэтому его можно получить и сверить при возврате пользователя:

```php
$config = new \Esia\Config([/* ... */]);
$esia = new \Esia\OpenId($config);

$url = $esia->buildUrl();
$state = $config->getState(); // сохраните в сессии

// ... после редиректа сверьте $_GET['state'] с сохранённым значением
```

Свой `state` можно передать и напрямую в `buildUrl($state)` — удобно, когда
нужно различать несколько одновременно открытых окон входа. Переданное значение
попадёт в URL и сохранится в `Config`:

```php
$url = $esia->buildUrl($myState);
```

Можно задать свой `state` — через параметр конфигурации или `setState()` — тогда
он будет использован вместо сгенерированного:

```php
$config = new \Esia\Config([/* ... */, 'state' => $myState]);
// или
$config->setState($myState);
```

# Конфиг

`clientId` - ID вашего приложения.

`redirectUrl` - URL куда будет перенаправлен ответ с кодом.

`portalUrl` - по умолчанию: `https://esia-portal1.test.gosuslugi.ru/` (тестовая среда). Домен портала для авторизации (только домен). Для продуктивной среды укажите `https://esia.gosuslugi.ru/`.

`codeUrlPath` - по умолчанию: `aas/oauth2/v2/ac`. URL для получения кода авторизации. Прежний путь `aas/oauth2/ac` выведен из эксплуатации; при необходимости старое значение можно вернуть через этот параметр.

`tokenUrlPath` - по умолчанию: `aas/oauth2/v3/te`. URL для получения токена. Прежний путь `aas/oauth2/te` выведен из эксплуатации; при необходимости старое значение можно вернуть через этот параметр.

`clientCertificateHash` - по умолчанию: пусто. Хэш (fingerprint) сертификата системы-клиента в hex-формате. **Обязателен** для актуального эндпоинта получения токена `aas/oauth2/v3/te` (иначе ЕСИА вернёт `ESIA-007014`). Сертификат должен быть предварительно зарегистрирован в ЕСИА и привязан к УЗ системы-клиента. Значение представляет собой отпечаток (fingerprint) сертификата по SHA-256 в hex-формате (например, `openssl x509 -in cert.pem -noout -fingerprint -sha256`); точный способ вычисления см. в методических рекомендациях ЕСИА. Если параметр не задан, он не отправляется (обратная совместимость).

`scope` - по умолчанию: `fullname birthdate gender email mobile id_doc snils inn`. Запрашиваемые права у пользователя.

`privateKeyPath` - путь до приватного ключа.

`privateKeyPassword` - пароль от приватного ключа.

`certPath` - путь до сертификата.

`tmpPath` - путь до дериктории где будет проходить подпись (должна быть доступна для записи).

`state` - по умолчанию: пусто. OAuth-идентификатор запроса (анти-CSRF). Если не задан, генерируется автоматически при `buildUrl()`/`getToken()` и сохраняется в `Config` (доступен через `getState()`). Можно задать свой — он будет использован вместо сгенерированного.

`esiaCertPath` - по умолчанию: пусто. Путь до сертификата подписи ЕСИА (для продуктивной среды — GOST-2012). Если задан, полученный JWT автоматически проверяется: подпись, `exp`/`nbf`/`iat`, `iss` и аудитория (`aud`/`client_id`). Если не задан, проверка пропускается (обратная совместимость).

`esiaTokenIssuer` - по умолчанию: пусто. Ожидаемое значение claim `iss` в токене. Если не задано, `iss` не проверяется.

`tokenLeeway` - по умолчанию: `60`. Допустимое отклонение (в секундах) при проверке временных claim'ов (`exp`, `nbf`, `iat`) для компенсации рассинхронизации часов.

## Проверка JWT (подпись и claim'ы)

По умолчанию библиотека не проверяет подпись полученного от ЕСИА токена — это
сохраняет обратную совместимость. Чтобы включить проверку, укажите путь до
сертификата подписи ЕСИА через `esiaCertPath` (и, при необходимости,
`esiaTokenIssuer`):

```php
$config = new \Esia\Config([
    // ... остальные параметры
    'esiaCertPath'    => '/path/to/esia-signing-cert.pem',
    'esiaTokenIssuer' => 'http://esia.gosuslugi.ru/',
]);
$esia = new \Esia\OpenId($config);

// getToken выбросит наследника InvalidTokenException при некорректном токене:
// SignatureInvalidException — неверная подпись,
// TokenExpiredException     — истёк срок (exp) или ещё не действителен (nbf),
// InvalidClaimException     — неверный iss / аудитория (aud/client_id).
$token = $esia->getToken($_GET['code']);
```

Проверка сделана подключаемой (pluggable): вы можете передать собственную
реализацию `\Esia\Token\TokenValidatorInterface` (например, с проверкой
GOST-подписи через CryptoPro) через `\Esia\OpenId::setTokenValidator()`.
Стандартная реализация `\Esia\Token\JwtValidator` проверяет подпись через
`\Esia\Token\OpenSslSignatureVerifier` (RSA из коробки; алгоритмы GOST-2012 —
при наличии GOST-движка в OpenSSL).

## Подпись запросов (сигнеры)

Для получения токена запрос к ЕСИА должен быть подписан. ЕСИА требует подпись
**ГОСТ Р 34.10-2012** (RSA больше не поддерживается в продуктивной среде).
Сигнер задаётся через `\Esia\OpenId::setSigner()` и должен реализовывать
`\Esia\Signer\SignerInterface`. Доступны следующие реализации:

| Сигнер | Механизм | Требования |
| --- | --- | --- |
| `\Esia\Signer\SignerPKCS7` | Нативный `openssl_pkcs7_sign()` | Стандартный PHP с `ext-openssl`. **Не умеет ГОСТ** в обычной сборке OpenSSL — подходит только для тестов/RSA. |
| `\Esia\Signer\CliSignerPKCS7` | Вызов `openssl smime -engine gost` | OpenSSL, собранный с GOST-движком (`libengine-gost-openssl1.1`), в `PATH`. Ключ и сертификат ГОСТ в PEM. |
| `\Esia\Signer\CliCryptoProSigner` | Вызов утилиты `cryptcp` | Установленный **КриптоПро CSP** с утилитой `cryptcp`. Сертификат ГОСТ в хранилище CSP, указывается по SHA-1 отпечатку. |
| `\Esia\Signer\CryptoProSigner` | PHP-расширение КриптоПро (`\CPStore`/`\CPSigner`) | Проприетарное PHP-расширение КриптоПро. Сертификат ГОСТ в хранилище `My` текущего пользователя. |

Пример с CLI-сигнером ГОСТ (OpenSSL + GOST-движок):

```php
$esia->setSigner(new \Esia\Signer\CliSignerPKCS7(
    '/path/to/gost-cert.pem',
    '/path/to/gost-key.pem',
    'key-password',
    '/tmp'
));
```

Пример с КриптоПро через утилиту `cryptcp` (отпечаток — SHA-1 сертификата в
хранилище CSP):

```php
$esia->setSigner(new \Esia\Signer\CliCryptoProSigner(
    '/opt/cprocsp/bin/amd64/cryptcp', // путь до cryptcp
    '745187e5c161cd2e3130d886f9df4492fa270685', // отпечаток сертификата
    'pin', // PIN контейнера (если задан)
    '/tmp' // каталог для временных файлов
));
```

Пример с КриптоПро через PHP-расширение:

```php
$esia->setSigner(new \Esia\Signer\CryptoProSigner(
    '745187e5c161cd2e3130d886f9df4492fa270685', // отпечаток сертификата
    'pin' // PIN контейнера (если задан)
));
```

Все сигнеры поддерживают PSR-3 логгер через `setLogger()`.

Сигнер и логгер также можно передать сразу в конструктор `\Esia\OpenId`
(удобно для DI-контейнеров), не прибегая к сеттерам:

```php
$esia = new \Esia\OpenId(
    $config,
    $httpClient,      // ?Psr\Http\Client\ClientInterface
    $requestFactory,  // ?Psr\Http\Message\RequestFactoryInterface
    $streamFactory,   // ?Psr\Http\Message\StreamFactoryInterface
    $signer,          // ?Esia\Signer\SignerInterface
    $logger           // ?Psr\Log\LoggerInterface
);
```

Любой из аргументов можно передать как `null` — тогда используется значение по
умолчанию. Методы `setSigner()` и `setLogger()` продолжают работать.



Токен - jwt токен которые вы получаете от ЕСИА для дальнейшего взаимодействия

oid - уникальный идентификатор владельца токена

## Как получить oid?
Если 2 способа:
1. oid содержится в jwt токене, расшифровав его
2. После получения токена oid сохраняется в config и получить можно так 
```php
$esia->getConfig()->getOid();
```

## Переиспользование Токена

Дополнительно укажите токен и идентификатор в конфиге
```php
$config->setToken($jwt);
$config->setOid($oid);
```

# Тестирование

> [!WARNING]
> У авторов и контрибьюторов **не всегда есть доступ к реальной среде ЕСИА**:
> и промышленный контур, и тестовый стенд `esia-portal1.test.gosuslugi.ru`
> доступны только из РФ и требуют зарегистрированной ИС и ГОСТ-сертификата.
> Поэтому изменения проверяются **офлайн**, а против реального ЕСИА могут быть
> не протестированы — **обновляйтесь на свой страх и риск**. Если обновление
> заработало (или нет) с реальной средой, пожалуйста, оставьте короткий отчёт в
> обсуждении: [Отчёты о совместимости с реальной средой ЕСИА](https://github.com/fr05t1k/esia/discussions/93).

Официальная документация ЕСИА не предусматривает изолированного тестирования
интеграции: единственный «боевой» путь — удалённый тестовый стенд
`esia-portal1.test.gosuslugi.ru`, который требует сетевого доступа (стенд
доступен только из РФ), зарегистрированной тестовой ИС и выданного ГОСТ-сертификата.
Поэтому основное покрытие в библиотеке — **офлайн**, без обращения к ЕСИА:

- **HTTP-моки** (`php-http/mock-client`) — юнит-тесты гоняют `buildUrl`/`getToken`
  и методы `rs/prns/*` против заранее заданных PSR-7 ответов.
- **JWT-фикстуры** — проверка подписи и claim'ов маркера доступа с ключом,
  сгенерированным на лету.
- **Локальный ГОСТ round-trip** — подпись и её проверка через OpenSSL с
  gost-engine (CI-job `gost-roundtrip`), без участия ЕСИА.
- **End-to-end против локального мок-сервера ЕСИА** — набор `e2e` поднимает
  встроенный веб-сервер PHP с фикстурным роутером
  (`tests/_support/mock_esia_router.php`), имитирующим эндпоинты ЕСИА, и
  прогоняет полный сценарий `getToken → getPersonInfo → getContactInfo →
  getRoles → getOrganizations` через настоящий PSR-18 клиент. Так проверяется
  весь HTTP-транспорт и разбор ответов детерминированно и на любой машине.

Запуск тестов:

```bash
composer install
vendor/bin/codecept build   # генерирует акторов для наборов unit и e2e
vendor/bin/codecept run     # все наборы
vendor/bin/codecept run e2e # только end-to-end против мок-сервера
```

ГОСТ-тесты (`-g gost`) требуют OpenSSL с установленным gost-engine
(`libengine-gost-openssl1.1`); без него они падают локально, но проходят в CI.
