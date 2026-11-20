# AGENTS.md

## Project

Typed PHP 8.3+ SDK for the Bookurier courier services API
(`daika7ana/bookurier-php-sdk`, namespace `Daika7ana\Bookurier`). Library only:
no server, no web entrypoint, no framework in `require`. PSR-18 core, one
resource class per API area, readonly DTOs, enums.

Base URL `https://www.bookurier.ro/colete/serv` (`ClientConfig::BASE_URL`). Every
endpoint is a form POST carrying `userid`, `pwd` and an optional `msg` XML string.
There is no OpenAPI schema and no sandbox; the wire contract follows the
documentation and was verified against the live service.

## Commands

```bash
composer install
composer ci         # validate --strict, pint --test, phpstan, phpunit tests/Unit
composer pint       # style check only, never fixes
composer stan       # PHPStan level 8 over src/
composer test       # full PHPUnit suite
composer test:unit  # tests/Unit only
composer test:smoke # tests/Smoke only, needs BOOKURIER_TEST_* env vars
vendor/bin/pint     # the only way to auto-fix style
```

- `composer.lock` is gitignored, so CI resolves fresh; keep `guzzlehttp/psr7` at
  `>=2` because `Client::create()` instantiates `GuzzleHttp\Psr7\HttpFactory`.
- Quality gates: PHPStan level 8 over `src/` (vendor/tests excluded), Laravel
  Pint (`per` preset), PHPUnit strict (`.dist`), `composer validate --strict`.
  CI runs validate, Pint and PHPStan on PHP 8.3 and unit tests on 8.3/8.4/8.5,
  branch `master`.

## Layout

`src/` holds `Client.php`, `Config/`, `Http/`, `Support/`, `Exceptions/`,
`Enums/`, `DTOs/Order/` and `Resources/`. `tests/Support/` holds the fakes,
`tests/Unit/` mirrors `src/`; `docs/` holds the user guides.

## Conventions

- Never import Symfony, Laravel, or another framework into `src/`, `tests/`, or
  docs, and never make one a runtime dependency; framework integration is out of
  scope. No new runtime dependencies without an explicit decision. Requests are
  built through the injected PSR-17 factories and absolute URIs assembled
  internally; callers never pass a URL.
- Request DTO: `final readonly class Order` with public camelCase properties and
  `toXml()`. Null optionals are omitted, booleans serialize as `1`/`0`, and text
  is escaped with `ENT_QUOTES | ENT_XML1`. Response DTOs are `final readonly`;
  `OrderStatus` and `HistoryEntry` have `fromArray(array $data): self`,
  `OrderReference` is constructed positionally, and list results are
  `Daika7ana\SimpleCollections\Collection`.
- Resources call `$this->client->post(ENDPOINT, ['msg' => Xml::message(...)])`
  and map with `ResponseBody` / `XmlResponse`; docblock `@throws` for the four
  exceptions. Endpoints are fixed filenames under the base URL, never dynamic
  paths. XML tags keep the vendor's names (mostly snake_case); PHP properties stay
  camelCase.
- Never commit credentials, tokens, or a populated `.env`: smoke tests read
  `BOOKURIER_TEST_*` environment variables and must never contain literal account
  values, and documentation uses placeholders only.
- `docs/` is user-facing documentation: behavior changes go in the matching file,
  not just in code. `CHANGELOG.md` is Keep a Changelog, newest first, tags `vX.Y.Z`.
- Commits and PR titles use Conventional Commits (`<type>(<scope>): <subject>`,
  lowercase imperative, no trailing period).

## HTTP Transport And Timeouts

- `Client` builds requests with the injected PSR-17 `RequestFactoryInterface` /
  `StreamFactoryInterface`, consumes PSR-7 `ResponseInterface`, and depends only
  on PSR-18 `Psr\Http\Client\ClientInterface` — never a concrete client.
- `src/Http/HttpClientInterface.php` widens PSR-18 with
  `sendRequest(RequestInterface $request, ?float $timeout = null)`.
  `src/Http/GuzzleHttpClient.php` is the bundled transport (Guzzle `http_errors`
  and redirects disabled, so 4xx/5xx reach the response classifiers);
  `src/Http/Psr18HttpClientAdapter.php` wraps any other PSR-18 client, rejects a
  non-null per-call timeout with `InvalidArgumentException`, and rethrows a
  foreign `ClientExceptionInterface` as `TransportException` (original as
  `previous`). `TransportException` implements `ClientExceptionInterface`.
- `Client::__construct()` throws `InvalidArgumentException` when
  `ClientConfig::timeout` is non-null and the client is not this package's
  `HttpClientInterface`. `ClientConfig::timeout` is `?float`, default `null`;
  `connectTimeout` exists only on
  `GuzzleHttpClient::__construct(?GuzzleClient $guzzleClient = null, ?float $timeout = null, ?float $connectTimeout = null)`,
  which applies `DEFAULT_TIMEOUT = 30.0` / `DEFAULT_CONNECT_TIMEOUT = 10.0` only
  when it creates its own Guzzle client. Precedence: per-call
  `Client::post(..., timeout:)` / `Client::send($request, $t)` →
  `ClientConfig::timeout` → transport constructor arguments → 30.0/10.0.
- `guzzlehttp/guzzle` and `guzzlehttp/psr7` are runtime dependencies (default
  transport and `GuzzleHttp\Psr7\HttpFactory`), so `Client::create()` needs no
  arguments; no `class_exists` guard, no `suggest` entry. `ext-dom` is required
  for XML parsing.

## API Quirks

- Auth is the Bookurier account username and password, sent as `userid` and `pwd`
  form fields on every POST — not an API key and not OAuth. `Client::post()`
  throws `AuthenticationException` before sending when either is empty; the env
  vars are `BOOKURIER_USERNAME` (with `BOOKURIER_USERID` as a fallback) and
  `BOOKURIER_PASSWORD`, and the client code for `<client>` is
  `BOOKURIER_CLIENT_ID`. A non-numeric `BOOKURIER_TIMEOUT` or
  `BOOKURIER_CLIENT_ID` throws `InvalidArgumentException` from
  `fromEnvironment()`. Redact those fields and full request bodies from logs.
  `Client::send()` does not add credentials.
- Every `msg` must be wrapped as `<msg>...</msg>`; the SDK always sends that
  envelope. A bare `<cmd>` without the wrapper is rejected with `-1` (or `-3` on
  label endpoints).
- Error model is in-band while HTTP status is 200: a body matching `/^-\d+$/` is
  `ApiException` naming the code — `-1` rejected request (bad credentials or
  malformed `msg`), `-2` record not found or invalid data (unknown AWB), `-3`
  incomplete request. A non-empty `<err>` on an XML-mapped
  endpoint (`status`, `statusByUniqueId`, `history`, `historyByUniqueId`,
  `createBatch`) is `ApiException` with the `<err>` text; on `createBatch` a
  response without `<cmds>` is `UnexpectedResponseException`. Delete endpoints
  expect `1`; `create` expects an AWB matching `/^[A-Za-z]{0,4}\d{3,}$/`;
  challenge pages and empty or unparseable bodies are
  `UnexpectedResponseException`. Status codes are never inspected.
- XML tag mapping from `Order`: `uniqueId`→`unq`, `recipientName`→`recv`,
  `number`→`no`, `packages`→`packs`, `block`→`bl`, `entrance`→`ent`,
  `apartment`→`apt`, `exchangePackage`→`exchange_pack`,
  `cashOnDelivery`→`rbs_val`, `returnDocument`→`ret_doc`,
  `returnDocumentDescription`→`ret_doc_desc`, `insuredValue`→`insurance_val`,
  `clientCode`→`client`. The documentation's `<exange_pack>` and `< status_desc >`
  are typos; emit `exchange_pack` and `status_desc`.
- `unq` is the caller's own order id. It is **not** a deduplication key: two
  orders with the same `unq` both get AWBs on the live service. AWBs come in two
  shapes, chosen server-side by destination county (2026-11-20 probe): `BOK` plus
  seven digits (`BOK3925457`) on the own network, or twelve digits
  (`062568455929`) on partner routes (seen for Cluj, Iasi, Timis, Suceava,
  Bihor). The SDK treats either as an opaque string and validates with the
  lenient `/^[A-Za-z]{0,4}\d{3,}$/`; a mixed batch returns both shapes
  comma-separated. `<client>`
  (your client code) is required in the order XML — `Order::$clientCode`, or
  `ClientConfig::clientId` when null; with neither, `create()`/`createBatch()`
  throw `InvalidArgumentException` before sending. `Order::$district` (sector or
  county) is required as well: an empty value throws locally, and the live
  service answers `-5` when the tag is missing. `createBatch()` also rejects
  duplicate non-empty `uniqueId` values. Batch `add_cmds.php` takes
  `<cmds><cmd>...</cmd></cmds>` and answers `<msg><cmds>BOK1,BOK2</cmds></msg>`: a
  comma separated AWB list with no `unq`, which the SDK pairs positionally with
  the orders you sent (count mismatch is an error).
- `get_history.php` (history by AWB) answers `-2` on the live service even for a
  valid AWB; `get_history_unq.php` (`historyByUniqueId()`) works. `get_stat.php`
  works with a wrapped `<msg>` and returns `<status_desc >` with a stray space
  that DOM parsing normalizes.
- The host has a bot filter: sustained request bursts return HTTP 200 with an HTML
  "One moment, please…" challenge page, which surfaces as
  `UnexpectedResponseException` on every endpoint, document endpoints included.
  Space live tests out; a blocked window clears after a few minutes.
- Services are Romanian only: `1` Bucharest 24h, `2` Bucharest express,
  `3` Metropolitan, `9` National 24h (`ServiceType`).
- HTTPS is the default despite the documentation listing `http://` URLs. The
  documentation also has endpoint typos (`add_ cmds.php` with a space); use
  `ClientConfig::BASE_URL`.
- HTML/PDF endpoints return `Support\Document` with the raw body, not JSON.
  `$mimeType` defaults to `application/octet-stream` when `Content-Type` is
  absent. `Document::isPdf()` sniffs `%PDF`, not the header; `pdf()` and
  `pdfByUniqueId()` require a body that starts with `%PDF`. `get_AWB.php` returns
  an HTML fragment, not a full document.
- The documentation is the design source; its wire claims were checked against
  the live service with Bookurier's test account (2026-11-20), which
  produced both AWB shapes (`BOK` + 7 digits and 12-digit partner numbers), the
  `-1`/`-2`/`-3` codes, the mandatory `<msg>`
  wrapper, the required `<client>` and the `get_history.php` failure above. Treat
  anything not covered by that check — field validation rules, label pagination,
  pickup dispatch — as unverified. `pickup()->request()` is `@experimental`: it
  dispatches a physical courier and is unit-tested only.

## Tests

- Unit tests never touch the network. `TestClientFactory::create($handler)`
  returns `[Client, FakeHttpClient]`; the default config uses test credentials,
  and `FakeHttpClient` records `lastRequest` and `lastTimeout`.
- Assert method, path, form fields and XML body on `$httpClient->lastRequest`;
  every endpoint needs a test covering both request and response mapping.
  `tests/Support/Fixture.php` holds the canned bodies.
- `tests/Smoke` runs against the live API and skips itself unless
  `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` and
  `BOOKURIER_TEST_CLIENT_ID` are set; creating an AWB also needs
  `BOOKURIER_TEST_ALLOW_WRITES=1`. It never runs in `composer ci`.
- `composer ci` must pass before a change is done.
