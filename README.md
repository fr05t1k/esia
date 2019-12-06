
# Единая система идентификации и аутентификации (ЕСИА) OpenId 

[![Build Status](https://travis-ci.org/fr05t1k/esia.svg?branch=master)](https://travis-ci.org/fr05t1k/esia)

# Описание
Компонент для авторизации на портале "Госуслуги"

# Установка

При помощи [composer](https://getcomposer.org/download/):
```
composer require --prefer-dist fr05t1k/esia
```
Или добавьте в composer.json

```
"fr05t1k/esia" : "^2.0"
```

# Как использовать 

Пример получения ссылки для авторизации c использованием сертификата и закрытого ключа:
```php
<?php 
$config = new \Esia\Config([
  'clientId' => 'INSP03211',
  'redirectUrl' => 'http://my-site.com/response.php',
  'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
  'scope' => ['fullname', 'birthdate'],
  'privateKeyPath' => 'my-site.com.key',
  'privateKeyPassword' => 'password',
  'certPath' => 'my-site.com.pem',
  'tmpPath' => '/tmp',
]);
$esia = new \Esia\OpenId($config);
?>

<a href="<?=$esia->buildUrl()?>">Войти через портал госуслуги</a>
```

Пример получения ссылки для авторизации c использованием КриптоПро DSS сервера:
```php
<?php 
$config = new \Esia\Config([
  'clientId' => 'INSP03211',
  'redirectUrl' => 'http://my-site.com/response.php',
  'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
  'scope' => ['fullname', 'birthdate'],
  'signer' => 'CryptoProDSS',
  'privateKeyPath' => '111', // ID сертификата на КриптоПро DSS сервере
  'privateKeyPassword' => '123456', // Пин-код от сертификата
  'certPath' => 'https://dss.server/SignServer/rest/api/documents', // Путь REST API КриптоПро DSS до конечной точки подписи документов
  'tmpPath' => '',
  // Параметры авторизации на КриптоПро DSS с использованием учетных данных владельца
  'additionalData' => [
  [
    'oauthPath' => 'https://dss.server/STS/oauth/token',
    'oauthData' => [
      'grant_type' => 'password',
      'username' => 'user',
      'password' => 'password',
      'resource' => 'urn:cryptopro:dss:frontend:signserver',
      'client_id' => '12345678-9012-3456-7890-123456789012',
    ],
  ],
]);
$esia = new \Esia\OpenId($config);
?>
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```

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

`portalUrl` - по умолчанию: `https://esia-portal1.test.gosuslugi.ru/`. Домен портала для авторизация (только домен).

`codeUrlPath` - по умолчанию: `aas/oauth2/ac`. URL для получения кода.

`tokenUrlPath` - по умолчанию: `aas/oauth2/te`. URL для получение токена.

`scope` - по умолчанию: `fullname birthdate gender email mobile id_doc snils inn`. Запрашиваемые права у пользователя.

`signer` - метод подписи запроса. Возможные варианты: SignerPKCS7, CliSignerPKCS7, SignerCryptoProDSS. По умолчанию SignerPKCS7.

`privateKeyPath` - путь до приватного ключа или ID сертификата на КриптоПро DSS сервере.

`privateKeyPassword` - пароль от приватного ключа или пин-код от сертификата.

`certPath` - путь до сертификата или путь REST API КриптоПро DSS до конечной точки подписи документов.

`tmpPath` - путь до дериктории где будет проходить подпись (должна быть доступна для записи). Для КриптоПро DSS не используется.

`additionalData` - дополнительные параметры авторизации (для КриптоПро DSS - авторизация на OAuth сервере).

# Токен и oid

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
