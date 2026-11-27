<div align="center">

<h1>Bookurier PHP SDK</h1>

[![CI](https://github.com/daika7ana/bookurier-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/daika7ana/bookurier-php-sdk/actions/workflows/ci.yml)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-12%E2%80%9313-0A7BBB?style=flat-square)](docs/TESTING.md)
[![Smoke Tests](https://img.shields.io/badge/Smoke%20Tests-Live%20API-orange?style=flat-square)](docs/TESTING.md)
[![PSR Standards](https://img.shields.io/badge/PSR-7%2F17%2F18-blue?style=flat-square)](https://www.php-fig.org/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-green?style=flat-square)](LICENSE)

A modern, type-safe PHP SDK for the **Bookurier portal API — Updated October 2026**

</div>

> 🚀 Framework-agnostic, portal-credential-based, fully typed with DTOs, and built for Romanian courier shipments.

The SDK wraps Bookurier's JSON and form endpoints behind a PSR-18 client, maps
batch results, history and partner cities to readonly DTOs, and returns labels
and bordero documents as the documents Bookurier sent. Authentication is the
portal account username and password, sent as `user` and `pwd` on every account
request. History is public and uses a shared tracking key.

This README covers the 2.0 line. If you are upgrading from 1.x, see
[Upgrading From 1.x](#upgrading-from-1x).

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Supported Resources](#supported-resources)
- [Error Handling](#error-handling)
- [Framework Support](#framework-support)
- [Testing](#testing)
- [Upgrading From 1.x](#upgrading-from-1x)
- [Documentation](#documentation)

## Features

### Core Strengths

- ✅ **PSR-18 HTTP client** — bundled Guzzle transport by default; any PSR-18 client can be supplied instead
- ✅ **Typed DTOs** — build an `Order`; read `BatchResult`, `HistoryEvent`, `PartnerCity` and `Document` response objects
- ✅ **Current portal endpoints** — create and batch-create orders, read history, fetch PDF/HTML labels, print the daily bordero, request a pickup and list partner cities
- ✅ **Partial batch results** — `createBatch()` returns references and per-order errors instead of failing the whole batch
- ✅ **Status enum** — `Status::isFinal()` tells you when to stop polling history
- ✅ **Document guards** — PDF labels must start with `%PDF`; challenge pages and unexpected bodies are rejected
- ✅ **Rate-limit awareness** — HTTP 429 raises `UnexpectedStatusException` with the `Retry-After` value exposed as `$retryAfter`
- ✅ **Zero framework coupling** — no Laravel, Symfony or other framework in `require`
- ✅ **Static analysis and tests** — PHPStan level 8 over `src/`, a fake-transport unit suite and a live smoke suite

### Perfect For

- 🎯 Romanian courier shipments — AWB creation, labels, bordero and history
- 🎯 Laravel, Symfony, or plain PHP applications
- 🎯 Integrations that need typed order and batch results instead of raw arrays
- 🎯 Teams that want predictable local verification through unit tests, static analysis, and smoke checks

## Requirements

- **PHP 8.3+**
- **Composer 2**
- A PSR-7/17/18 compatible environment

## Installation

### Via Composer

```bash
composer require daika7ana/bookurier-php-sdk:^2.0
```

## Quick Start

Configure the portal account and pickup point, create one order, then persist the
AWB it returns:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5', // the portal pickup-point number
));

$awb = $client->orders()->create(new Order(
    uniqueId: 'A1',
    recipientName: 'Ionescu Vasile',
    phone: '0744666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',
    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sector 1',
    cashOnDelivery: 100.65,
    notes: 'De sunat cu 1/2 inainte',
));

// Persist $awb immediately. Labels, history and every later call use the AWB;
// there are no by-unique-id lookups.
file_put_contents("awb-{$awb}.pdf", $client->labels()->pdf($awb)->contents);

echo $awb; // e.g. BOK6000123456
```

For the full flow — orders, batches, history, labels, bordero, partner cities,
pickup and error handling — see [docs/QUICKSTART.md](docs/QUICKSTART.md).

That's it! You've created an AWB and written its PDF label to disk.

## Configuration

### Base URL

`ClientConfig` is a `final readonly` value object. The main settings are:

- `userId`
- `password`
- `baseUrl`
- `timeout`
- `pickupPoint`
- `trackingKey`

The default base URL is `https://portal.bookurier.ro/api`
(`ClientConfig::BASE_URL`). The client joins the base URL and an endpoint by
trimming the trailing and leading slashes.

### Credentials & Environment Variables

Use `ClientConfig::fromEnvironment()` when you want configuration to come from
environment variables instead of application code. It reads:

| Variable | Maps to | Notes |
|---|---|---|
| `BOOKURIER_USERNAME` | `ClientConfig::$userId` | Portal username |
| `BOOKURIER_PASSWORD` | `ClientConfig::$password` | Portal password |
| `BOOKURIER_PICKUP_POINT` | `ClientConfig::$pickupPoint` | Default pickup-point number |
| `BOOKURIER_TRACKING_KEY` | `ClientConfig::$trackingKey` | Defaults to `ClientConfig::DEFAULT_TRACKING_KEY` |
| `BOOKURIER_BASE_URL` | `ClientConfig::$baseUrl` | Defaults to `ClientConfig::BASE_URL` |
| `BOOKURIER_TIMEOUT` | `ClientConfig::$timeout` | Seconds; must be numeric |

`Client::create()` with no `config` calls `ClientConfig::fromEnvironment()`. A
`ClientConfig` you construct yourself does not read the environment.

For every variable and its default, see
[docs/CONFIGURATION.md](docs/CONFIGURATION.md#environment-variables).

### Timeouts

Set a default on `ClientConfig::timeout`. Per-call overrides exist on the
low-level `Client::postJson()` / `postForm()` / `get()` / `send()` methods and on
`reports()->borderou(..., timeout:)`. Precedence: per-call argument →
`ClientConfig::timeout` → `GuzzleHttpClient` constructor arguments → 30 s request
/ 10 s connect defaults.

A bare PSR-18 client keeps its own timeout and cannot receive the per-call
argument — passing one throws `InvalidArgumentException`; combining a bare client
with a non-null `ClientConfig::timeout` also throws.

The vendor recommends 60 s for `add_cmds.php` and 30 s for the other endpoints:

```php
$slow = $client->withTimeout(60.0); // shares the transport and configuration
$awb = $slow->orders()->create($order);
```

### Operational Notes

- Rate limits: `add_cmds.php` and `print_awbs.php` allow 30 requests per minute,
  `request_pickup.php` and `print_borderou.php` 10, and `awb_history.php` 120.
  The SDK does not throttle or retry.
- There is no delete or cancellation endpoint. An AWB that never enters the
  courier network is not billed; do not hand over parcels you want to abandon, and
  mark the AWB ignored in your own system.
- Persist the AWB returned by `create()` or `BatchResult::references()`. Labels
  and history are AWB-only; there are no by-unique-id lookups.
- The host bot filter can return an HTML challenge page with HTTP 200 during
  request bursts. Reads can be retried after a few minutes; reconcile create
  calls before retrying.
- `pickup()->request()` dispatches a physical courier and is `@experimental`.
  Never auto-retry it.

## Authentication

Bookurier authenticates with a portal account username and password. JSON
endpoints receive them as `user` and `pwd` in the JSON body; form endpoints as
form fields. `awb_history.php` is a public GET that needs only the shared tracking
key. There is no API key, OAuth exchange or refresh flow.

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

`Client::postJson()` and `Client::postForm()` throw `AuthenticationException`
before sending when either value is empty. `Client::get()` and `Client::send()`
add no credentials.

Order creation also needs a pickup point. It comes from `Order::$pickupPoint`, or
from `ClientConfig::pickupPoint` when the order has none. With neither, `create()`
and `createBatch()` throw `InvalidArgumentException` before sending.

See [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) for the endpoint-by-endpoint
credential table, tracking key and redaction guidance.

## Supported Resources

All endpoints are relative to `https://portal.bookurier.ro/api`.

### Orders

- `orders()->create(Order $order): string` — `add_cmds.php`; returns the AWB.
  Persist it immediately; labels and history are AWB-only.
- `orders()->createBatch(array $orders): BatchResult` — `add_cmds.php`; partial
  success is possible, so check `hasErrors()` / `errors()` as well as
  `references()`. Duplicate non-empty `uniqueId` values are rejected before
  sending.
- `orders()->history(string $awb): Collection<HistoryEvent>` — public GET to
  `awb_history.php`; the last event is the current status. `Status::isFinal()`
  marks terminal states.

### Labels

- `labels()->pdf(string|array $awbs, ?string $mode = null, int $page = 0): Document`
  — `print_awbs.php`; requires a `%PDF` body.
- `labels()->html(string|array $awbs, ?string $mode = null, int $page = 0): Document`
  — `print_awbs.php`; returns the HTML body.
- `$mode` is `'s'` or `'m'`; when null the vendor default (`m`) applies. `$page`
  defaults to `0`.

### Reports

- `reports()->borderou(string $date, ?string $pickupPoint = null, ?float $timeout = null): Document`
  — `print_borderou.php`; the date is required (`yyyy-mm-dd` or `dd-mm-yyyy`) and
  the result is an HTML document.

### Pickup

- `pickup()->request(?string $pickupPoint = null): string` — `request_pickup.php`;
  returns the confirmation message. `@experimental`: it dispatches a physical
  courier and is never smoke-tested.

### Cities

- `cities()->partnerCities(?string $district = null): Collection<PartnerCity>` —
  `get_partner_cities.php`; partner-served cities only, excluding Bucharest and
  Ilfov. The district filter is diacritics-insensitive.

There is no status endpoint, no by-unique-id lookup and no delete/cancellation
endpoint. The current status is the last event returned by `history()`. Persist
the AWB returned by `create()` or `BatchResult::references()`.

See [docs/RESOURCES.md](docs/RESOURCES.md) for the endpoint and return type of
each method, and [docs/DTOS.md](docs/DTOS.md) for the request and response
objects.

## Error Handling

Bookurier reports most failures with HTTP 200 and
`{"status":"error","message":"..."}`. The SDK classifies the HTTP status first;
see [docs/ERRORS.md](docs/ERRORS.md) for the full precedence, 429 handling and
bot-filter guidance.

| Exception | Raised when |
|---|---|
| `AuthenticationException` | Missing credentials before sending, `Invalid user` / `Login fail`, or HTTP 401 |
| `ValidationException` | Any other JSON error message, or HTTP 400/422 |
| `TransportException` | DNS, TLS, timeout or connection failure; original is `getPrevious()` |
| `UnexpectedStatusException` | Empty/non-JSON body, challenge page, HTTP 429, other non-2xx status, or a missing AWB |
| `InvalidArgumentException` | Missing pickup point or district, empty batch, duplicate non-empty `uniqueId`, foreign-client timeout |

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // Missing credentials, or the portal rejected the login.
} catch (ValidationException $e) {
    // The portal rejected the order data.
} catch (TransportException $e) {
    // Network failure; the request may still have reached Bookurier.
} catch (UnexpectedStatusException $e) {
    // Unexpected body, challenge page, rate limit or missing AWB.
    $e->retryAfter; // set for HTTP 429
} catch (BookurierException $e) {
    // Any other SDK failure.
}
```

For a single `create()`, a per-order error is raised as `ValidationException`.
For `createBatch()`, per-order errors are returned in `BatchResult::errors()`.

## Framework Support

### Zero Framework Dependencies

No Laravel, Symfony or other framework is in `require`, and no service provider
is needed. The package works in frameworks and standalone PHP 8.3+ applications
alike:

- 🌐 **Laravel** applications
- 🌐 **Symfony** projects
- 🌐 **Standalone PHP** applications
- 🌐 Any **PHP 8.3+** environment

### Custom HTTP Client

A bare PSR-18 client is wrapped automatically:

```php
use Daika7ana\Bookurier\Client;
use GuzzleHttp\Client as GuzzleClient;

$client = Client::create(
    httpClient: new GuzzleClient(['timeout' => 10.0]),
);
```

A wrapped client keeps its own timeout, so `ClientConfig::timeout` must stay
`null`; passing both throws `InvalidArgumentException`. Implement
`Daika7ana\Bookurier\Http\HttpClientInterface` instead if you need the SDK's
per-call timeout.

## Testing

The unit suite is offline: it runs against a fake PSR-18 transport. The optional
smoke suite calls the live portal API and skips without credentials.

### Run Static Analysis

```bash
composer stan
# or
vendor/bin/phpstan --no-progress
```

PHPStan level 8 analyzes `src/`.

### Run Full Test Suite

```bash
composer test
# or
vendor/bin/phpunit
```

Run `composer test:unit` for `tests/Unit` only.

### Run Smoke Tests Against A Live Bookurier Account

```bash
composer test:smoke
```

Smoke tests skip unless `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` and
`BOOKURIER_TEST_PICKUP_POINT` are set. Creating an AWB additionally requires
`BOOKURIER_TEST_ALLOW_WRITES=1`; read-only history and label checks use
`BOOKURIER_TEST_AWB`. There is no delete endpoint, so a created AWB is left in
place and is not billed unless scanned. `pickup()->request()` is never
smoke-tested because it dispatches a physical courier.

```bash
BOOKURIER_TEST_USERNAME=... BOOKURIER_TEST_PASSWORD=... \
BOOKURIER_TEST_PICKUP_POINT=... BOOKURIER_TEST_ALLOW_WRITES=1 \
composer test:smoke
```

The vendor documents a `demo` / `demo` portal account with pickup point `5` for
manual trials; keep it out of code and tests.

### Run The Same Checks As CI

```bash
composer ci
```

`composer ci` validates `composer.json`, then runs formatting, static analysis,
and the unit suite. Available Composer scripts:

- `composer pint` runs Pint in test mode
- `composer stan` runs PHPStan against `src/`
- `composer test` runs the full PHPUnit suite
- `composer test:unit` runs the unit test suite
- `composer test:smoke` runs the smoke test suite
- `composer ci` runs the same local checks as CI

### Test Environment Variables

Set these in your shell before running smoke tests:

| Variable | Purpose |
|----------|---------|
| `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` | Portal test account credentials |
| `BOOKURIER_TEST_PICKUP_POINT` | Pickup-point number; required for all smoke tests |
| `BOOKURIER_TEST_ALLOW_WRITES` | Set to `1` to create live AWBs |
| `BOOKURIER_TEST_AWB` | An existing AWB for read-only history and label checks |

The unit suite reads none. Smoke tests never run in `composer ci`.

[See detailed testing docs →](docs/TESTING.md)

## Upgrading From 1.x

2.0.0 is a clean break for Bookurier's October 2026 portal API. The 1.x XML API
at `https://www.bookurier.ro/colete/serv` is not part of 2.0.0; installs that
still need it should stay on 1.x.

| 1.x | 2.0.0 |
|---|---|
| Base URL `/colete/serv` | Base URL `https://portal.bookurier.ro/api` |
| Form POST with `userid`, `pwd` and a `<msg>` XML envelope | JSON or form request with `user` / `pwd`; no envelope |
| `ClientConfig::$clientId` / `Order::$clientCode` | `ClientConfig::$pickupPoint` / `Order::$pickupPoint` |
| `create()` plus `statusByUniqueId()` / `historyByUniqueId()` | `create()` returns the AWB; persist it and use `history($awb)` |
| `delete()` / `deleteByUniqueId()` | No cancellation endpoint; unscanned AWBs are unbilled |
| `ServiceType::BucharestToday` (`7`) | Removed; the enum is `1`, `3`, `5`, `8`, `9`, `11` |
| `labels()->daily()` | No direct replacement; pass the day's AWBs to `labels()->pdf()` |
| `reports()->dailyBordero()` | `reports()->borderou($date)` |
| `pickup()->request(): Document` | `pickup()->request(): string` (`@experimental`) |

Removed in 2.0.0: `BOOKURIER_USERID`, `BOOKURIER_CLIENT_ID`, the `Xml` /
`XmlResponse` support classes, `OrderStatus`, `HistoryEntry`, all by-unique-id
methods, the delete methods, the daily labels method and the old standalone
status endpoints.

## Documentation

Complete guides and references:

| Guide | Purpose |
|-------|---------|
| 📖 [Quickstart](docs/QUICKSTART.md) | First AWB, PDF label, history and error handling |
| 🔧 [Installation](docs/INSTALLATION.md) | Requirements and setup |
| ⚙️ [Configuration](docs/CONFIGURATION.md) | Credentials, pickup point, tracking key, base URL, environment and timeouts |
| 🔐 [Authentication](docs/AUTHENTICATION.md) | Portal credentials, credential placement, tracking key and redaction |
| 🔗 [Resources](docs/RESOURCES.md) | Method-level endpoint reference |
| 📦 [DTOs](docs/DTOS.md) | `Order` field mapping, enums and response objects |
| ✅ [Testing](docs/TESTING.md) | Test commands, fakes and smoke suite |
| ❌ [Errors](docs/ERRORS.md) | Exception hierarchy, rate limits and retry guidance |
| 📚 [Usage](docs/USAGE.md) | Orders, batches, history, labels, bordero, partner cities and pickup |

GPL-3.0-or-later. See [LICENSE](LICENSE).

---

<div align="center">

Made with ❤️

**Questions?** [Check the docs](docs/USAGE.md) or [open an issue](https://github.com/daika7ana/bookurier-php-sdk/issues)

</div>
