
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
"fr05t1k/esia" : "dev-master"
```

# Как использовать 

Пример получения ссылки для авторизации
```php
<?php 
$config = [
   'clientId' => 'INSP03211',
   'redirectUrl' => 'http://my-site.com/response.php',
   'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
   'privateKeyPath' => 'my-site.com.pem',
   'privateKeyPassword' => 'my-site.com',
   'certPath' => 'my-site.com.pem',
   'tmpPath' => 'tmp',
];

$esia = new \esia\OpenId($config, new esia\transport\Curl());
?>

<a href="<?=$esia->getUrl()?>">Войти через портал госуслуги</a>
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```php
<?php
$esia = new \esia\OpenId($config, new esia\transport\Curl());

$esia->getToken($_GET['code']);

$personInfo = $esia->getPersonInfo();
$addressInfo = $esia->getAddressInfo();
$contactInfo = $esia->getContactInfo();

```
Пример получения данных об организации

```php
<?php
$esia = new \esia\OpenId($config, new esia\transport\Curl());

$esia->getToken($_GET['code']);

/**
 *  get user oid
 */
$personInfo = $esia->getPersonInfo();

/** @var array $orgs = [
 *  \stdClass => [
 *      'oid' => '11155',
 *      'ogrn'=>'112233',
 *      'fullName'=> 'Some org',
 *      ...
 *  ]
 * ] */

$orgs = $esia->getOrgRoles();

...

// in next page we must chose one org

$orgOid = $_GET['oorgOid'];

$scopes = [
    'http://esia.gosuslugi.ru/org_shortname?org_oid=#org_oid#',
    'http://esia.gosuslugi.ru/org_inn?org_oid=#org_oid#',
    'http://esia.gosuslugi.ru/org_addrs?org_oid=#org_oid#',
    'http://esia.gosuslugi.ru/org_ctts?org_oid=#org_oid#',
    'http://esia.gosuslugi.ru/org_emps?org_oid=#org_oid#',
    ];

$scopes = preg_replace("/#org_oid#/i", $orgOid, $scopes);

$esia->setScope($scopes);
$esia->setRedirectUrl('http://my-site.com/orgResponse.php?org_oid='. $orgOid);
$url = $esia->getUrl();

// after login

$esia->getOrgToken($_GET['code']);

// get token data & save it for next requests
$tokenData = $esia->getFullTokenData();

$orgInfo = $esia->getOrgInfo($orgOid);
$orgContacts = $esia->getOrgContacts($orgOid);

```

# Конфиг

`clientId` - ID вашего приложения.

`redirectUrl` - URL куда будет перенаправлен ответ с кодом.

`portalUrl` - по умолчанию: `https://esia-portal1.test.gosuslugi.ru/`. Домен портала для авторизация (только домен).

`codeUrl` - по умолчанию: `aas/oauth2/ac`. URL для получения кода.

`tokenUrl` - по умолчанию: `aas/oauth2/te`. URL для получение токена.

`scope` - по умолчанию: `http://esia.gosuslugi.ru/usr_inf`. Запрашиваемые права у пользователя.

`privateKeyPath` - путь до приватного ключа.

`privateKeyPassword` - пароль от приватного ключа.

`certPath` - путь до сертификата.

`tmpPath` - путь до дериктории где будет проходить подпись (должна быть доступна для записи).

`log` - callable с одни параметром $message, в который будет передаваться сообщения лога.
