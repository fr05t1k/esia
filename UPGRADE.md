# Руководство по обновлению: 2.4.2 → 3.0.0

Версия **3.0.0** модернизирует библиотеку под актуальный API ЕСИА, делает её
независимой от конкретного HTTP-клиента (PSR-18/PSR-17) и добавляет проверку
JWT-маркера. Ниже перечислены все изменения, которые могут затронуть ваш код,
и способы миграции.

> [!WARNING]
> Изменения в этой версии тестируются **офлайн** — у авторов не всегда есть
> доступ к реальной среде ЕСИА (стенд `esia-portal1.test.gosuslugi.ru` и
> промышленный контур доступны только из РФ и требуют зарегистрированной ИС и
> ГОСТ-сертификата). **Обновляйтесь на свой страх и риск** и, пожалуйста,
> поделитесь результатом в обсуждении:
> [Отчёты о совместимости с реальной средой ЕСИА](https://github.com/fr05t1k/esia/discussions/93).

> TL;DR
> 1. Поднимите PHP до **>= 8.3**.
> 2. Установите любой PSR-18 HTTP-клиент (например `guzzlehttp/guzzle`) — из
>    зависимостей библиотеки он удалён.
> 3. Уберите `Esia\Http\GuzzleHttpClient` — передавайте PSR-18 клиент напрямую
>    (или ничего, он найдётся автоматически).
> 4. Проверьте изменившиеся значения по умолчанию для эндпоинтов (`https`,
>    `v2/ac`, `v3/te`) и при необходимости задайте прежние явно.

---

## 1. Требования к окружению

| | 2.4.2 | 3.0.0 |
|---|---|---|
| PHP | `^7.1 \| ^8.0` | **`^8.3`** |
| Расширения | `ext-openssl`, `ext-json` | `ext-openssl`, `ext-json` |

Обновите ограничение в своём `composer.json` и окружение до PHP 8.3+.

```
composer require fr05t1k/esia:^3.0
```

---

## 2. HTTP-клиент: Guzzle → PSR-18 / PSR-17 (⚠️ breaking)

Библиотека больше **не зависит** от Guzzle. Теперь она работает с любым
клиентом, реализующим **PSR-18** (`Psr\Http\Client\ClientInterface`), и
фабриками **PSR-17** (`Psr\Http\Message\RequestFactoryInterface`,
`StreamFactoryInterface`).

### Что удалено

| Удалено в 3.0.0 | Замена |
|---|---|
| `Esia\Http\GuzzleHttpClient` | любой PSR-18 клиент (в т.ч. сам `GuzzleHttp\Client`) |
| `Esia\Http\Exceptions\HttpException` | `Esia\Exceptions\RequestFailException` / `ForbiddenException` |
| зависимость `guzzlehttp/guzzle` в `require` | установите клиент сами (см. ниже) |

### Установите реализацию

Guzzle 7 сам реализует PSR-18, поэтому достаточно:

```
composer require guzzlehttp/guzzle
```

Подойдёт любой другой клиент (Symfony HTTP Client, `php-http/curl-client` и
т.д.) вместе с PSR-17 фабрикой (`nyholm/psr7`, `guzzlehttp/psr7`, …).

### Изменение конструктора

Сигнатура `OpenId::__construct()` расширена. Второй аргумент теперь — PSR-18
клиент (а не обёртка `GuzzleHttpClient`), плюс появились опциональные PSR-17
фабрики. Все аргументы, кроме `$config`, необязательны — реализации находятся
автоматически через `php-http/discovery`.

```php
// 2.4.2
use Esia\Http\GuzzleHttpClient;
use GuzzleHttp\Client;

$esia = new \Esia\OpenId($config, new GuzzleHttpClient(new Client()));
```

```php
// 3.0.0 — вариант A: передать клиент явно
$esia = new \Esia\OpenId($config, new \GuzzleHttp\Client());

// 3.0.0 — вариант B: ничего не передавать, клиент и фабрики найдутся сами
$esia = new \Esia\OpenId($config);

// 3.0.0 — вариант C: полный контроль (например, для DI-контейнера)
$esia = new \Esia\OpenId(
    $config,
    $psr18Client,
    $psr17RequestFactory,
    $psr17StreamFactory
);
```

### Обработка ошибок HTTP

Раньше ошибки транспорта могли приводить к `GuzzleHttp\Exception\*` /
`HttpException`. Теперь поведение соответствует PSR-18:

- HTTP **403** → `Esia\Exceptions\ForbiddenException`;
- любой ответ со статусом **>= 400** → `Esia\Exceptions\RequestFailException`;
- ошибка транспорта (реализующая `Psr\Http\Client\ClientExceptionInterface`)
  → `Esia\Exceptions\RequestFailException`.

Все они наследуют `Esia\Exceptions\AbstractEsiaException`, поэтому если вы
ловили именно его — менять ничего не нужно.

---

## 3. Изменённые значения по умолчанию (⚠️ поведение)

Значения по умолчанию в `Config` приведены к актуальному API ЕСИА.

| Параметр | 2.4.2 | 3.0.0 |
|---|---|---|
| `portalUrl` | `http://esia-portal1.test.gosuslugi.ru/` | `https://esia-portal1.test.gosuslugi.ru/` |
| `codeUrlPath` | `aas/oauth2/ac` | `aas/oauth2/v2/ac` |
| `tokenUrlPath` | `aas/oauth2/te` | `aas/oauth2/v3/te` |

Кроме того, актуальный эндпоинт получения токена `aas/oauth2/v3/te`
**требует** параметр `client_certificate_hash` (иначе ЕСИА вернёт
`ESIA-007014`). Для него добавлен новый параметр конфигурации
`clientCertificateHash` (по умолчанию пусто — тогда он не отправляется).

### Как оставить прежнее поведение

Если вы полагались на старые значения по умолчанию, задайте их явно:

```php
$config = new \Esia\Config([
    // ...
    'portalUrl'    => 'http://esia-portal1.test.gosuslugi.ru/',
    'codeUrlPath'  => 'aas/oauth2/ac',
    'tokenUrlPath' => 'aas/oauth2/te',
]);
```

### Продуктивная среда

Для боевой среды укажите продуктивный домен и хэш сертификата системы-клиента:

```php
$config = new \Esia\Config([
    // ...
    'portalUrl'             => 'https://esia.gosuslugi.ru/',
    'clientCertificateHash' => '<hex-hash>', // fingerprint сертификата (SHA-256)
]);
```

---

## 4. Проверка JWT-маркера (новое, опционально)

3.0.0 добавляет **опциональную** проверку подписи и claim-ов JWT, полученного
от ЕСИА. По умолчанию она **выключена**, поэтому существующий код продолжает
работать без изменений.

Включение через конфигурацию:

```php
$config = new \Esia\Config([
    // ...
    'esiaCertPath'    => '/path/to/esia-signing-cert.pem', // сертификат подписи ЕСИА
    'esiaTokenIssuer' => 'http://esia.gosuslugi.ru/',       // ожидаемый iss (опц.)
    'tokenLeeway'     => 60,                                // допуск по времени, сек (опц.)
]);
```

Либо подключаемо, своей реализацией `Esia\Token\TokenValidatorInterface`:

```php
$esia->setTokenValidator($myValidator);
```

При включённой проверке `getToken()` может выбросить (все — наследники
`Esia\Exceptions\InvalidTokenException` → `AbstractEsiaException`):

| Исключение | Причина |
|---|---|
| `Esia\Exceptions\SignatureInvalidException` | неверная/отсутствующая подпись или неподдерживаемый алгоритм |
| `Esia\Exceptions\TokenExpiredException` | истёк срок (`exp`) или ещё не действителен (`nbf`) |
| `Esia\Exceptions\InvalidClaimException` | неверный `iss` / аудитория (`aud`/`client_id`) / некорректный токен |

Сертификаты подписи ЕСИА выдаются на Технологическом портале (отдельно для
тестовой и боевой среды, алгоритм ГОСТ Р 34.10-2012).

---

## 5. Небольшое изменение поведения `getToken()`

`getToken()` теперь требует наличия в маркере идентификатора субъекта
(`urn:esia:sbj_id`). Если его нет, вместо тихого сохранения пустого `oid`
бросается `Esia\Exceptions\InvalidClaimException`. Для корректных маркеров ЕСИА
поведение не меняется. Дополнительно маркер записывается в `Config` только
**после** успешной проверки/разбора, поэтому отклонённый токен не попадает в
состояние клиента.

---

## Чек-лист миграции

- [ ] PHP обновлён до **8.3+**; ограничение в `composer.json` — `fr05t1k/esia:^3.0`.
- [ ] Установлен PSR-18 клиент (`composer require guzzlehttp/guzzle` или иной).
- [ ] Убраны упоминания `Esia\Http\GuzzleHttpClient` и `Esia\Http\Exceptions\HttpException`.
- [ ] Конструктор `OpenId` вызывается без обёртки `GuzzleHttpClient` (PSR-18 клиент напрямую или без аргумента).
- [ ] Проверены значения `portalUrl` / `codeUrlPath` / `tokenUrlPath`; при необходимости заданы прежние явно.
- [ ] Для `aas/oauth2/v3/te` задан `clientCertificateHash`.
- [ ] (Опционально) включена проверка JWT через `esiaCertPath` / `setTokenValidator()`.
