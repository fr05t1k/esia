
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
```
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

$esia = new \esia\OpenId($config);
?>

<a href="<?=$esia->getUrl()?>">Войти через портал госуслуги</a>
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```

$esia = new \esia\OpenId($config);

$esia->getToken($_GET['code']);

$personInfo = $esia->getPersonInfo();
$addressInfo = $esia->getAddressInfo();
$contactInfo = $esia->getContactInfo();
$documentInfo = $esia->getDocInfo();

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
