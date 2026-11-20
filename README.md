# Bookurier PHP SDK

<div align="center">

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
- [Custom HTTP Client](#custom-http-client)
- [Error Handling](#error-handling)
- [Documentation Limits](#documentation-limits)
- [Testing](#testing)
- [Documentation](#documentation)
- [License](#license)

## Features

- **PSR-18 core** — the bundled Guzzle transport is used by default; any PSR-18
  client can be supplied instead.
- **Typed DTOs** — build a shipment with `Order` and read status, history and
  batch results from readonly response objects.
- **XML built and parsed internally** — order payloads are escaped and wrapped
  for you; the API's `<cmd>` envelopes are mapped to DTOs.
- **Byte-exact documents** — HTML and PDF labels, daily bordero documents and
  pickup borderos are returned as the raw response body, not re-encoded.
- **All vendor endpoints** — create, batch create, delete, status, history,
  labels, daily bordero and pickup.
- **Typed error mapping** — authentication, in-band API errors, transport
  failures and unexpected bodies are separate exception types.
- **No framework coupling** — no Laravel, Symfony or other framework in
  `require`; no service provider required.

## Requirements

- **PHP 8.3+**
- **`ext-dom`** for XML parsing
- **Composer 2**
- A PSR-7/17/18 compatible environment

## Installation

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
    street: 'Nicolae Oncescu',
    number: '6',
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

Resource methods do not accept a timeout; configure one on `ClientConfig` or use
the low-level `Client::post()` / `Client::send()` methods. See
[docs/CONFIGURATION.md](docs/CONFIGURATION.md).

## Configuration

`Client::create()` with no config calls `ClientConfig::fromEnvironment()`, which
reads `BOOKURIER_USERNAME` (falling back to `BOOKURIER_USERID`),
`BOOKURIER_PASSWORD`, `BOOKURIER_CLIENT_ID`, `BOOKURIER_BASE_URL` and
`BOOKURIER_TIMEOUT`. A `ClientConfig` you construct yourself does not read the
environment.

For every variable, its default and the timeout precedence, see
[docs/CONFIGURATION.md](docs/CONFIGURATION.md). The default base URL is
`https://www.bookurier.ro/colete/serv`; the documentation lists `http://` URLs,
but the SDK uses the same host over HTTPS.

## Authentication

Bookurier authenticates with a Bookurier account username and password, sent as
`userid` and `pwd` on every form POST. There is no API key, no OAuth exchange and
no token refresh. `Client::post()` throws `AuthenticationException` before
sending when either value is empty; `Client::send()` adds no credentials.

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

## Custom HTTP Client

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

## Error Handling

```php
use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // userId or password is empty; no request was sent
} catch (ApiException $e) {
    // Bookurier answered a negative code or a non-empty <err>
} catch (TransportException $e) {
    // DNS, TLS, timeout or connection failure; original is getPrevious()
} catch (UnexpectedResponseException $e) {
    // body was empty, not XML where XML was expected, not the success value,
    // or the host's HTML challenge page
} catch (BookurierException $e) {
    // any other SDK failure
}
```

Bookurier returns HTTP 200 even for errors, so the SDK classifies responses by
body rather than status code. See [docs/ERRORS.md](docs/ERRORS.md).

## Documentation Limits

Bookurier publishes no OpenAPI schema and provides no sandbox. This SDK was built
from the documentation, then checked against the live service with Bookurier's
test account, which established these points and corrected several in the
documentation:

- AWBs come in two shapes, chosen server-side by destination county: `BOK` plus
  seven digits on the own network (`BOK3925457`), or twelve digits on partner
  routes (`062568455929`). The SDK treats either as an opaque string and
  validates with the lenient `/^[A-Za-z]{0,4}\d{3,}$/`.
- Failures are negative codes in a HTTP 200 body: `-1` rejected request, `-2`
  record not found / invalid data, `-3` incomplete request.
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
  `UnexpectedResponseException`; wait a few minutes before retrying reads, and
  reconcile `create()` / `createBatch()` before retrying them.

`pickup()->request()` (`@experimental`) dispatches a physical courier and was
never exercised against the live service; it is covered by unit tests only.
Validate your own integration against your account before production rollout.

## Testing

```bash
composer ci         # validate --strict -> pint --test -> phpstan -> phpunit tests/Unit
composer pint       # style check only, never fixes
composer stan       # PHPStan level 8 over src/
composer test       # full PHPUnit suite
composer test:unit  # tests/Unit only
```

The unit suite never touches the network and needs no Bookurier credentials: a
fake transport records the request and returns canned bodies. Every endpoint has
a test covering both the outgoing request and the response mapping. See
[docs/TESTING.md](docs/TESTING.md) for the helpers and an example test.

## Documentation

| Guide | Purpose |
|-------|---------|
| [Installation](docs/INSTALLATION.md) | Requirements and setup |
| [Quickstart](docs/QUICKSTART.md) | First AWB, PDF label, status and delete |
| [Configuration](docs/CONFIGURATION.md) | Credentials, base URL, environment, timeouts, custom clients |
| [Authentication](docs/AUTHENTICATION.md) | Account credentials, client code, rejected requests |
| [Usage](docs/USAGE.md) | Orders, batches, status, history, labels, bordero documents, pickup |
| [Resources](docs/RESOURCES.md) | Method-level endpoint reference |
| [DTOs](docs/DTOS.md) | `Order` field mapping, enums and response objects |
| [Errors](docs/ERRORS.md) | Exception hierarchy and failure modes |
| [Testing](docs/TESTING.md) | Test commands and helpers |

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
