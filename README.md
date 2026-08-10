
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
