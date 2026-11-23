<div align="center">

<h1>Bookurier PHP SDK</h1>

[![CI](https://github.com/daika7ana/bookurier-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/daika7ana/bookurier-php-sdk/actions/workflows/ci.yml)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php)](https://www.php.net/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-12%E2%80%9313-0A7BBB?style=flat-square)](docs/TESTING.md)
[![Smoke Tests](https://img.shields.io/badge/Smoke%20Tests-Live%20API-orange?style=flat-square)](docs/TESTING.md)
[![PSR Standards](https://img.shields.io/badge/PSR-7%2F17%2F18-blue?style=flat-square)](https://www.php-fig.org/)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-green?style=flat-square)](LICENSE)

A modern, type-safe PHP SDK for the **Bookurier courier services API**

</div>

> 🚀 Framework-agnostic, XML-native, fully typed with DTOs, and built for Romanian courier shipments.

The SDK wraps Bookurier's form-POST endpoints behind a PSR-18 client, builds and
parses the XML payloads internally, and returns AWB labels, daily bordero
documents and pickup borderos as the documents Bookurier sent. Authentication is
the Bookurier account username and password sent as `userid` and `pwd` on every
API request — there is no API key, OAuth exchange or token refresh.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Supported Resources](#supported-resources)
- [Framework Support](#framework-support)
- [Testing](#testing)
- [Documentation](#documentation)

## Features

### Core Strengths

- ✅ **PSR-18 HTTP Client** — Bundled Guzzle transport by default; any PSR-18 client can be supplied instead
- ✅ **Typed DTOs** — Build a shipment with `Order`; read status, history and batch results from readonly response objects
- ✅ **XML Built and Parsed Internally** — Order payloads are escaped and wrapped for you; the API's `<cmd>` envelopes are mapped to DTOs
- ✅ **All 15 Vendor Endpoints** — Create, batch create, delete, status, history, labels, daily bordero and pickup
- ✅ **Byte-Exact Documents** — HTML and PDF labels, daily bordero documents and pickup borderos are returned as the raw response body, not re-encoded
- ✅ **In-Band Error Mapping** — HTTP 200 error bodies (`-1`, `-2`, `-3`, `-5`) become `AuthenticationException` or `ValidationException`; transport and unexpected bodies have their own exception types
- ✅ **Opaque AWB Formats** — `BOK` plus seven digits, twelve digits on partner routes, or `CKC` plus six digits for Bucharest Today; all are treated as opaque strings and validated leniently
- ✅ **Document Guards** — PDF labels must start with `%PDF`; the host's HTML challenge page and empty bodies are rejected
- ✅ **Static Analysis with PHPStan** — Level 8 over the `src/` codebase
- ✅ **Zero Framework Coupling** — No Laravel, Symfony or other framework in `require`; no service provider required
- ✅ **Comprehensive Tests** — Unit suite with a fake transport, plus a live smoke suite

### Perfect For

- 🎯 Romanian courier shipments — AWB labels, daily borderos and pickup requests
- 🎯 PHP frameworks and standalone applications alike
- 🎯 Type-safe order creation, status and history workflows
- 🎯 Production integrations validated against the live service with a smoke suite

## Requirements

- **PHP 8.3+**
- **`ext-dom`** for XML parsing
- **Composer 2**
- A PSR-7/17/18 compatible environment

## Installation

### Via Composer

```bash
composer require daika7ana/bookurier-php-sdk
```

## Quick Start

Pass the Bookurier account credentials explicitly, or let the client read
`BOOKURIER_USERNAME` and `BOOKURIER_PASSWORD`:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    clientId: 1234, // your Bookurier client code
));

$awb = $client->orders()->create(new Order(
    uniqueId: 'A1',                       // your own order id
    recipientName: 'Ionescu Vasile',
    phone: '0744-666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',

    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sect 1',
    cashOnDelivery: 100.65,
    notes: 'De sunat cu 1/2 inainte',
));

echo $awb; // e.g. BOK3925457

$label = $client->labels()->pdf($awb);
file_put_contents("awb-{$awb}.pdf", $label->contents);
```

For the full flow — order, label, status, delete and error handling — see
[docs/QUICKSTART.md](docs/QUICKSTART.md).

Resource methods do not accept a timeout; configure one on `ClientConfig`, derive
a client with `withTimeout()`, or use the low-level `Client::post()` /
`Client::send()` methods. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

That's it! You've created an AWB and written its PDF label to disk.

## Configuration

### Base URL

The default base URL is `https://www.bookurier.ro/colete/serv`. The vendor
documentation lists `http://` URLs, but the SDK uses the same host over HTTPS.

### Environment Variables

`Client::create()` with no config calls `ClientConfig::fromEnvironment()`, which
reads `BOOKURIER_USERNAME` (falling back to `BOOKURIER_USERID`),
`BOOKURIER_PASSWORD`, `BOOKURIER_CLIENT_ID`, `BOOKURIER_BASE_URL` and
`BOOKURIER_TIMEOUT`. A `ClientConfig` you construct yourself does not read the
environment.

For every variable and its default, see
[docs/CONFIGURATION.md](docs/CONFIGURATION.md).

### Request Timeouts

Set a default on `ClientConfig::timeout`; per-call overrides exist on the
low-level `Client::post()` / `Client::send()` methods. Precedence: per-call
`Client::post(..., timeout:)` / `Client::send($request, $t)` →
`ClientConfig::timeout` → transport constructor arguments → 30.0/10.0.

For a different budget, `$slow = $client->withTimeout(30.0);` returns a new
client sharing the transport; the original keeps its timeout.

## Authentication

Bookurier authenticates with a Bookurier account username and password, sent as
`userid` and `pwd` on every form POST. There is no API key, no OAuth exchange and
no token refresh. `Client::post()` throws `AuthenticationException` before
sending when either value is empty; `Client::send()` adds no credentials.

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
));
```

Order XML requires `<client>` (your Bookurier client code). It comes from
`Order::$clientCode`, or from `ClientConfig::clientId` when that is `null`. With
neither set, `create()` and `createBatch()` throw `InvalidArgumentException`
before sending. The password is protected only by HTTPS, so redact `userid`,
`pwd` and request bodies from logs.

See [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) for environment variables,
credential handling and redaction guidance, and [docs/ERRORS.md](docs/ERRORS.md)
for rejected-request codes.

## Supported Resources

All endpoints are `POST` requests relative to
`https://www.bookurier.ro/colete/serv`. The SDK adds `userid` and `pwd` to every
form body. Order-creating calls also send your client code as `<client>` inside
the XML, taken from `Order::$clientCode` or, when that is `null`, from
`ClientConfig::clientId`.

| Resource | Methods |
|---|---|
| `orders()` | `create`, `createBatch`, `delete`, `deleteByUniqueId`, `status`, `statusByUniqueId`, `history`, `historyByUniqueId` |
| `labels()` | `html`, `pdf`, `htmlByUniqueId`, `pdfByUniqueId`, `daily` |
| `reports()` | `dailyBordero` |
| `pickup()` | `request` (`@experimental`) |

`history($awb)` is not usable on the live service: it answers `-2` even for a
valid AWB. Use `historyByUniqueId()`. `pickup()->request()` dispatches a physical
courier and has never been exercised live.

See [docs/RESOURCES.md](docs/RESOURCES.md) for the endpoint and return type of
each method, and [docs/DTOS.md](docs/DTOS.md) for the request and response
objects.

### Error Handling

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // userId or password is empty (nothing was sent), or Bookurier answered -1
} catch (ValidationException $e) {
    // Bookurier answered -2, -3 or -5, or a non-empty <err>
} catch (TransportException $e) {
    // DNS, TLS, timeout or connection failure; original is getPrevious()
} catch (UnexpectedStatusException $e) {
    // body was empty, not XML where XML was expected, not the success value,
    // or the host's HTML challenge page
} catch (BookurierException $e) {
    // any other SDK failure
}
```

Bookurier returns HTTP 200 even for errors, so the SDK classifies responses by
body first; a non-2xx status is the fallback (401 → authentication, 400/422 →
validation). See [docs/ERRORS.md](docs/ERRORS.md).

### Documentation Limits

Bookurier publishes no OpenAPI schema and provides no sandbox. This SDK was built
from the documentation, then checked against the live service with Bookurier's
test account, which established these points and corrected several in the
documentation:

- AWBs vary server-side by destination county and service: `BOK` plus seven
  digits on the own network (`BOK3925457`), twelve digits on partner routes
  (`062568455929`), or `CKC` plus six digits for Bucharest Today (`CKC122336`).
  The SDK treats the AWB as an opaque string and validates with the lenient
  `/^[A-Za-z]{0,4}\d{3,}$/`.
- Failures are negative codes in a HTTP 200 body: `-1` rejected request, `-2`
  record not found / invalid data, `-3` incomplete request, `-5` rejected
  order (for example a missing `<district>`).
- `add_cmd.php` requires `<client>` (your client code).
- Every `msg` must be wrapped in `<msg>…</msg>`; the SDK always sends that
  envelope, and a bare `<cmd>` without the wrapper is rejected with `-1`.
- `get_history.php` (history by AWB) answers `-2` even for an existing AWB. Use
  `historyByUniqueId()`.
- `unq` is not enforced unique: two orders with the same `unq` both get an AWB.
- `add_cmds.php` answers with a comma separated AWB list and no `unq`, so batch
  results are paired positionally with the orders you sent.
- The host sits behind a bot filter: a burst of requests eventually gets an HTML
  "One moment, please…" challenge page with HTTP 200 instead of the API answer.
  Every endpoint, including the document endpoints, surfaces that as
  `UnexpectedStatusException`; wait a few minutes before retrying reads, and
  reconcile `create()` / `createBatch()` before retrying them.

`pickup()->request()` (`@experimental`) dispatches a physical courier and was
never exercised against the live service; it is covered by unit tests only.
Validate your own integration against your account before production rollout.

## Framework Support

### Zero Framework Dependencies

This package works equally well everywhere, with no Laravel, Symfony or other
framework in `require` and no service provider required:

- 🌐 **Laravel** applications
- 🌐 **Symfony** projects
- 🌐 **Standalone PHP** applications
- 🌐 **Microservices**
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
per-call timeout:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Http\HttpClientInterface;

/** @var HttpClientInterface $transport */
$client = Client::create(httpClient: $transport);
```

## Testing

### Run Static Analysis

```bash
composer stan       # PHPStan level 8 over src/
# or
vendor/bin/phpstan --no-progress
```

### Run Full Test Suite

```bash
composer test       # full PHPUnit suite
# or
vendor/bin/phpunit
```

The unit suite never touches the network and needs no Bookurier credentials: a
fake transport records the request and returns canned bodies. Every endpoint has
a test covering both the outgoing request and the response mapping. See
[docs/TESTING.md](docs/TESTING.md) for the helpers and an example test.

### Run Live Smoke Tests

```bash
composer test:smoke # tests/Smoke only, needs BOOKURIER_TEST_* env vars
```

The smoke tests hit the live API. They skip themselves unless
`BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` and
`BOOKURIER_TEST_CLIENT_ID` are set; creating an AWB also needs
`BOOKURIER_TEST_ALLOW_WRITES=1`.

### Run The Same Checks As CI

```bash
composer ci         # validate --strict -> pint --test -> phpstan -> phpunit tests/Unit
composer pint       # style check only, never fixes
composer test:unit  # tests/Unit only
```

### Test Environment Variables

Configure these in the environment to enable smoke tests:

| Variable | Purpose |
|----------|---------|
| `BOOKURIER_TEST_USERNAME` | Test account username |
| `BOOKURIER_TEST_PASSWORD` | Test account password |
| `BOOKURIER_TEST_CLIENT_ID` | Bookurier client code |
| `BOOKURIER_TEST_ALLOW_WRITES` | Set to `1` to create (and delete) a live AWB |

## Documentation

Complete guides and references:

| Guide | Purpose |
|-------|---------|
| 📖 [Quickstart](docs/QUICKSTART.md) | First AWB, PDF label, status and delete |
| 🔧 [Installation](docs/INSTALLATION.md) | Requirements and setup |
| ⚙️ [Configuration](docs/CONFIGURATION.md) | Credentials, base URL, environment, timeouts, custom clients |
| 🔐 [Authentication](docs/AUTHENTICATION.md) | Account credentials, client code, rejected requests |
| 📚 [Usage](docs/USAGE.md) | Orders, batches, status, history, labels, bordero documents, pickup |
| 🔗 [Resources](docs/RESOURCES.md) | Method-level endpoint reference |
| 📦 [DTOs](docs/DTOS.md) | `Order` field mapping, enums and response objects |
| ❌ [Errors](docs/ERRORS.md) | Exception hierarchy and failure modes |
| ✅ [Testing](docs/TESTING.md) | Test commands and helpers |

GPL-3.0-or-later. See [LICENSE](LICENSE).

---

<div align="center">

Made with ❤️

**Questions?** [Check the docs](docs/USAGE.md) or [open an issue](../../issues)

</div>
