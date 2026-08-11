# Changelog

Все значимые изменения в этом проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.1.0/),
проект придерживается [семантического версионирования](https://semver.org/lang/ru/).

## [Unreleased]

## [3.0.1] - 2026-08-10

### Fixed

- `CliCryptoProSigner`: убран лишний флаг `-detached` из вызова `cryptcp
  -signf`. Команда `-signf` уже создаёт открепленную (detached) PKCS#7
  подпись, а недокументированный `-detached` на части версий cryptcp мог
  приводить к ошибке неизвестного параметра. Формат подписи не меняется.
  (#97, спасибо @ilimurzin за замечание в #89)

## [3.0.0] - 2026-08-10

Крупное обновление: библиотека приведена в соответствие с актуальным API ЕСИА
(Методические рекомендации), избавлена от жёсткой привязки к Guzzle в пользу
PSR-18/PSR-17, получила проверку JWT-маркера, first-class ГОСТ-подпись и
полноценное офлайн-тестирование. Подробная инструкция по миграции —
в [`UPGRADE.md`](UPGRADE.md).

> ⚠️ Изменения проверялись **офлайн**: у авторов не всегда есть доступ к
> реальной среде ЕСИА. Обновляйтесь на свой страх и риск и делитесь результатом
> в [обсуждении #93](https://github.com/fr05t1k/esia/discussions/93).

### Added

- Опциональная проверка JWT-маркера ЕСИА: подпись и claim'ы (`exp`, `nbf`,
  `iat`, `iss`, `aud`/`client_id`). Реализация подключаемая через
  `Esia\Token\TokenValidatorInterface` и `OpenId::setTokenValidator()`; из
  коробки — `JwtValidator` + `OpenSslSignatureVerifier` (RSA/RS256, а также
  ГОСТ Р 34.10-2012 при наличии GOST-движка в OpenSSL). (#68, #84)
- First-class ГОСТ-2012 / КриптоПро сигнеры: `CliSignerPKCS7`
  (OpenSSL + GOST-движок), `CliCryptoProSigner` (утилита `cryptcp`) и
  `CryptoProSigner` (PHP-расширение КриптоПро). (#69, #89)
- Информация об организациях и ролях пользователя: `OpenId::getRoles()` и
  `OpenId::getOrganizations()`. (#75, #90)
- Поддержка OAuth-параметра `state` (защита от CSRF): автогенерация при
  `buildUrl()`/`getToken()`, `Config::getState()`/`setState()` и
  `buildUrl($state)`. (#75, #90)
- Внедрение сигнера и логгера через конструктор `OpenId` (удобно для
  DI-контейнеров) — в дополнение к `setSigner()`/`setLogger()`. (#76, #91)
- Новые параметры конфигурации: `clientCertificateHash` (обязателен для
  эндпоинта `aas/oauth2/v3/te`), `esiaCertPath`, `esiaTokenIssuer`,
  `tokenLeeway`.
- Офлайн-тестирование: JWT-фикстуры и HTTP-моки, локальный ГОСТ round-trip в CI
  и end-to-end набор против локального мок-сервера ЕСИА. (#70, #71, #72, #86, #92)
- CI на GitHub Actions (миграция с Travis CI); статический анализ и стиль:
  PHPStan, php-cs-fixer, composer-normalize, проверка на PHP 8.4. (#65, #73, #79, #88)
- Документация: руководство по обновлению [`UPGRADE.md`](UPGRADE.md),
  руководство по тестированию [`docs/testing.md`](docs/testing.md), обновлённый
  README (главная страница GitHub Pages) и файл `LICENSE` (MIT). (#74, #87, #94, #95)

### Changed

- **BREAKING** Минимальная версия PHP повышена до **8.3** (ранее `^7.1 || ^8.0`). (#79)
- **BREAKING** HTTP-слой переведён с Guzzle на **PSR-18/PSR-17**: реализация
  находится автоматически (`php-http/discovery`) либо передаётся в конструктор
  `OpenId`. Сигнатура `OpenId::__construct()` изменена. (#83)
- **BREAKING** Значения по умолчанию приведены к актуальному API ЕСИА:
  `portalUrl` теперь `https://` (ранее `http://`), `codeUrlPath` —
  `aas/oauth2/v2/ac` (ранее `aas/oauth2/ac`), `tokenUrlPath` —
  `aas/oauth2/v3/te` (ранее `aas/oauth2/te`). (#66, #81)
- **BREAKING** Обработка HTTP-ошибок соответствует PSR-18: статус **403** →
  `ForbiddenException`, любой ответ **>= 400** или ошибка транспорта →
  `RequestFailException` (оба — наследники `AbstractEsiaException`).
- `getToken()` теперь требует наличия claim `urn:esia:sbj_id` (иначе —
  `InvalidClaimException`) и записывает маркер в `Config` только **после**
  успешной проверки/разбора.

### Removed

- **BREAKING** Класс `Esia\Http\GuzzleHttpClient` — используйте любой PSR-18
  клиент напрямую. (#83)
- **BREAKING** Класс `Esia\Http\Exceptions\HttpException` — заменён на
  `Esia\Exceptions\RequestFailException` / `ForbiddenException`. (#83)
- **BREAKING** Прямая зависимость `guzzlehttp/guzzle` — установите PSR-18 клиент
  самостоятельно (`composer require guzzlehttp/guzzle` или другой). (#83)

### Fixed

- `getAddressInfo()` больше не падает на пустом ответе — при отсутствии
  элементов возвращается пустой массив. (#67)

### Security

- Появилась возможность криптографически проверять подлинность полученного от
  ЕСИА JWT-маркера (подпись RS256/ГОСТ + claim'ы) вместо простого декодирования
  payload'а. Проверка **опциональна** и включается через `esiaCertPath` либо
  собственным `TokenValidatorInterface`. (#68, #84)

[Unreleased]: https://github.com/fr05t1k/esia/compare/3.0.1...HEAD
[3.0.1]: https://github.com/fr05t1k/esia/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/fr05t1k/esia/compare/2.4.2...3.0.0
