# Описание
Компонент для авторизации на портале "Госуслуги"

# Пример 

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

<a href="<?=$esia->getUrl()?>Войти через портал госуслуги</a>"
```

После редиректа на ваш `redirectUrl` вы получите в `$_GET['code']` код для получения токена

Пример получения токена и информации о пользователе

```

$esia = new \esia\OpenId($config);

$esia->getToken($_GET['code']);

$personInfo = $esia->getPersonInfo();
$addressInfo = $esia->getAddressInfo();
$contactInfo = $esia->getContactInfo();

```

