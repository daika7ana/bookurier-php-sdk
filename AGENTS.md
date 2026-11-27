# AGENTS.md

## Project

Typed PHP 8.3+ SDK for Bookurier's October 2026 portal API
(`daika7ana/bookurier-php-sdk`, namespace `Daika7ana\Bookurier`). Library only:
no server, no web entrypoint, no framework in `require`. PSR-18 core, one
resource class per API area, readonly DTOs, enums.

Base URL `https://portal.bookurier.ro/api` (`ClientConfig::BASE_URL`). Account
requests carry the portal username and password as `user` and `pwd`; JSON
endpoints in the JSON body, form endpoints in form fields. `awb_history.php` is a
public GET that needs only the shared tracking key. There is no OpenAPI schema and
no sandbox; the wire contract follows the vendor's October 2026 documentation and
is covered by the unit suite and a live smoke suite.

2.0.0 is a clean break for this API. The 1.x XML API under
`https://www.bookurier.ro/colete/serv` is removed; external users who still need
it stay on 1.x.

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
  branch `master`. CI skips every job for commits/PRs starting `docs` or
  `chore`, and for messages containing `[skip ci]` / `[skip-ci]`.
- `composer ci` must pass before a change is done.

## Layout

`src/` holds `Client.php`, `Config/`, `Http/`, `Support/` (`ResponseBody.php`,
`Document.php`), `Exceptions/`, `Enums/` (`ServiceType.php`, `Status.php`),
`DTOs/Order/` (`Order`, `BatchResult`, `OrderReference`, `OrderError`,
`HistoryEvent`, `PartnerCity`), `DTOs/Common/Errors/BookurierError.php` and
`Resources/` (`OrdersResource`, `LabelsResource`, `ReportsResource`,
`PickupResource`, `CitiesResource`). `tests/Support/` holds the fakes,
`tests/Unit/` mirrors `src/`, `tests/Smoke/` holds the live checks, and `docs/`
holds the user guides.

## Conventions

- Never import Symfony, Laravel, or another framework into `src/`, `tests/`, or
  docs, and never make one a runtime dependency; framework integration is out of
  scope. No new runtime dependencies without an explicit decision. Requests are
  built through the injected PSR-17 factories and absolute URIs assembled
  internally; callers never pass a URL.
- Request DTO: `final readonly class Order` with public camelCase properties and
  `toArray()`. Null optionals are omitted; booleans serialize as `1`/`0`; zero
  amounts are kept. Response DTOs are `final readonly`; `HistoryEvent`,
  `PartnerCity`, `OrderError` and `BookurierError` have
  `fromArray(array $data): self`; `OrderReference` is constructed positionally;
  list results are `Daika7ana\SimpleCollections\Collection`.
- Resources call `$this->client->postJson(...)`, `postForm(...)` or `get(...)` and
  map with `ResponseBody`; docblock `@throws` for the four exceptions. Endpoints
  are fixed filenames under the base URL, never dynamic paths. Wire field names
  keep the vendor's abbreviations and snake_case; PHP properties stay camelCase.
- Never commit credentials, tokens, or a populated `.env`: smoke tests read
  `BOOKURIER_TEST_*` environment variables and must never contain literal account
  values, and documentation uses placeholders only. The vendor's public
  `demo` / `demo` account with pickup point `5` may be mentioned in prose but
  never hardcoded into code or tests.
- `docs/` is user-facing documentation: behavior changes go in the matching file,
  not just in code. `CHANGELOG.md` is Keep a Changelog, newest first, tags
  `vX.Y.Z`.
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
  `Client::postJson(..., timeout:)` / `postForm(..., timeout:)` /
  `get(..., timeout:)` / `send($request, $t)` or
  `reports()->borderou(..., timeout:)` → `ClientConfig::timeout` → transport
  constructor arguments → 30.0/10.0.
- `guzzlehttp/guzzle` and `guzzlehttp/psr7` are runtime dependencies (default
  transport and `GuzzleHttp\Psr7\HttpFactory`), so `Client::create()` needs no
  arguments; no `class_exists` guard, no `suggest` entry. There is no `ext-dom`
  requirement; the API is JSON, not XML.

## API Quirks

- Auth is the portal username and password, sent as `user` and `pwd` on every
  account request — not an API key and not OAuth. `Client::postJson()` and
  `postForm()` throw `AuthenticationException` before sending when either is
  empty; `Client::get()` and `Client::send()` add no credentials. Env vars:
  `BOOKURIER_USERNAME`, `BOOKURIER_PASSWORD`, `BOOKURIER_PICKUP_POINT`,
  `BOOKURIER_TRACKING_KEY`, `BOOKURIER_BASE_URL`, `BOOKURIER_TIMEOUT`. There is
  no `BOOKURIER_USERID` and no `BOOKURIER_CLIENT_ID`. A non-numeric
  `BOOKURIER_TIMEOUT` throws `InvalidArgumentException` from `fromEnvironment()`.
  Redact `user`, `pwd`, the tracking key and full request bodies from logs.
- A pickup point is required on every order: `Order::$pickupPoint`, else
  `ClientConfig::pickupPoint`; with neither, `create()` and `createBatch()` throw
  `InvalidArgumentException` before sending. `pickup()->request()` and
  `reports()->borderou()` use the configured pickup point as their default.
  `Order::$district` is required in practice too: an empty value throws locally;
  Bucharest uses `Sector 1`..`Sector 6`, elsewhere the county.
- `orders()->create()` returns the AWB string. Persist it immediately: labels and
  history are AWB-only, and there are no by-unique-id lookups. The AWB is opaque
  to the SDK (for example `BOK6000123456`); the SDK does not validate or parse
  its shape. `uniqueId` is the caller's order id, sent as `unq`. It is not a
  deduplication key across separate calls: two orders with the same value both
  get AWBs.
- `orders()->createBatch()` returns `BatchResult`, not a collection. The response
  `map` is keyed by `unq`, and `references()` contains one `OrderReference` for
  each order the map names. Partial success is expected: some orders can get AWBs
  while others are rejected. Always check `hasErrors()` / `errors()`; per-order
  failures are not thrown. A top-level JSON error envelope, auth failure,
  transport failure or unexpected body still throws. `createBatch()` rejects an
  empty list and duplicate non-empty `uniqueId` values locally.
- `orders()->create()` raises `ValidationException` for a per-order error, with
  the message and suggestion appended. If the response has no AWB for the order,
  it falls back to the first `data` entry; with neither it raises
  `UnexpectedStatusException`.
- `orders()->history($awb)` is a public GET to
  `awb_history.php?key=<tracking-key>&awb=<awb>`; no account credentials. The key
  comes from `ClientConfig::$trackingKey`, else
  `ClientConfig::DEFAULT_TRACKING_KEY` (`7BzvpY3PuANhhMajURYWn`). Events are
  chronological; the last one is the current status. A freshly created AWB
  returns an empty event list until the parcel is scanned (live probe,
  2026-11-27). An empty `status_id` is
  partner-courier free text and `HistoryEvent::status()` returns `null`;
  non-numeric and unknown numeric ids also return `null`.
  `Enums\Status` maps ids; `Status::isFinal()` is true for `-1`, `4`, `9`, `10`,
  `18` and `99`. Recommend stopping polling at a final status.
- `labels()->pdf($awbs, ?string $mode = null, int $page = 0)` and
  `labels()->html(...)` post JSON to `print_awbs.php` with `data` (one AWB or a
  list), `format`, `page`, and `mode` only when non-null. `$mode` is `'s'` or
  `'m'`; the vendor default (`m`) applies when omitted. Both return `Document`.
  `pdf()` requires a body starting with `%PDF`; challenge pages, empty bodies and
  JSON success bodies raise `UnexpectedStatusException`.
- `reports()->borderou(string $date, ?string $pickupPoint = null, ?float $timeout = null)`
  posts form fields `pickup` and `filter_date` to `print_borderou.php`. The date
  is required and accepts `yyyy-mm-dd` or `dd-mm-yyyy`. It returns an HTML
  `Document`.
- `pickup()->request(?string $pickupPoint = null): string` posts `pickup` to
  `request_pickup.php` and returns the confirmation message. It is
  `@experimental`: it dispatches a physical courier, is unit-tested only, and is
  never smoke-tested. Never auto-retry it.
- `cities()->partnerCities(?string $district = null)` posts JSON to
  `get_partner_cities.php` with `data` as `{}` or `{"district": ...}` and returns
  `Collection<PartnerCity>`. It lists partner-served cities only; Bucharest and
  Ilfov are excluded. The district filter is diacritics-insensitive.
- `ServiceType` is `1` Bucharest 24h, `3` Metropolitan, `5` Ilfov Extins, `8`
  National Economic, `9` National 24, `11` National Premium. The 1.x code `7`
  (Bucharest Today) and documentation-only code `2` (Bucharest express) are gone.
- Error model: HTTP 200 plus `{"status":"error","message":...}`. The SDK checks
  the HTTP status first: `401` → `AuthenticationException`, `400`/`422` →
  `ValidationException`, `429` and other non-2xx → `UnexpectedStatusException`.
  On 2xx, an error envelope maps `Invalid user` / `Login fail`
  (case-insensitive substring) to `AuthenticationException` and every other
  message to `ValidationException`. An empty or non-JSON body, or a challenge
  page, raises `UnexpectedStatusException`. Document endpoints also classify JSON
  error envelopes; `pdf()` additionally requires `%PDF`. `BookurierError`
  carries the message with `code = 0`; `ValidationException::$errors` is always
  empty. `$e->getCode()` is the HTTP status when a response exists, else `0`.
  `retryAfter` is parsed from `Retry-After`; 429 is the common case.
- The vendor rate-limits `add_cmds.php` and `print_awbs.php` at 30 requests per
  minute, `request_pickup.php` and `print_borderou.php` at 10, and
  `awb_history.php` at 120. The SDK does not throttle or retry. Recommended
  timeouts: 60 s for `add_cmds.php`, 30 s for the others.
- The host runs a bot filter: sustained request bursts return HTTP 200 with an
  HTML "One moment, please…" challenge page instead of the API answer. Every
  endpoint surfaces that as `UnexpectedStatusException`; wait a few minutes
  before retrying. For create calls, reconcile the daily bordero and your own
  data first, because a retry can create a second AWB.
- There is no delete or cancellation endpoint. An AWB that never enters the
  courier network is not billed. Tell users not to hand over parcels they want to
  abandon and to mark the AWB ignored internally.
- The vendor's October 2026 documentation is the design source. Treat field
  validation rules, label `page` semantics and pickup dispatch as
  vendor-controlled; the SDK does not validate most field values.

## Tests

- Unit tests never touch the network. `TestClientFactory::create($handler, $config)`
  returns `[Client, FakeHttpClient]`; the default config uses test credentials,
  and `FakeHttpClient` records `lastRequest` and `lastTimeout`, with `lastJson()`
  and `lastForm()` decoding the body.
- Assert method, path, headers and JSON/form body on `$httpClient->lastRequest`;
  every endpoint needs a test covering both request and response mapping.
  `tests/Support/Fixture.php` holds the canned bodies, including partial batch,
  rate-limit, PDF, HTML and challenge responses.
- `tests/Smoke` runs against the live API and skips itself unless
  `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` and
  `BOOKURIER_TEST_PICKUP_POINT` are set. Creating an AWB also needs
  `BOOKURIER_TEST_ALLOW_WRITES=1`; read-only history/label checks use
  `BOOKURIER_TEST_AWB`. It never runs in `composer ci`.
- Smoke coverage: `SmokeTestCase` holds the credential skip, the sample order and
  the 0.5 s pause the host bot filter needs. `ClientSmokeTest` covers the write
  lifecycle: one order (create, history, PDF and HTML labels) and a two-order
  batch (create, references, history); there is no delete, so the AWB is left
  unbilled. `OrderLookupSmokeTest` is read-only against `BOOKURIER_TEST_AWB`;
  `ReportsSmokeTest` fetches today's bordero; `CitiesSmokeTest` lists partner
  cities. `pickup()->request()` is never smoke-tested: it is `@experimental` and
  dispatches a physical courier.
- `composer ci` must pass before a change is done.
