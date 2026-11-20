# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-20

### Added

- Initial release of the typed Bookurier PHP SDK.
- `Client` with PSR-18 core, a bundled Guzzle transport, PSR-17 request/stream
  factories and lazy resources (`orders()`, `labels()`, `reports()`, `pickup()`).
- Injected PSR-18 clients are wrapped automatically; `HttpClientInterface` adds
  per-call timeout support, and foreign clients keep their own timeout.
- All 15 endpoints: create, batch create, delete by AWB, delete by unique id,
  status by AWB, status by unique id, history by AWB, history by unique id, four
  AWB label variants, daily labels, the daily bordero and courier pickup.
- `Order` request DTO with XML serialization, null omission, `1`/`0` booleans and
  XML escaping; `OrderStatus`, `HistoryEntry`, `OrderReference` and `Document`
  response objects.
- `ServiceType` enum: Bucharest 24h, Bucharest express, Metropolitan and
  National 24h.
- In-band error mapping: any negative code (`-1` rejected request, `-2` record not
  found or invalid data, `-3` incomplete request, `-5` order rejected by the
  service), `<err>`, missing success values, invalid XML and empty bodies raise
  typed exceptions; transport failures preserve the original exception.
- HTML and PDF labels, daily bordero documents and pickup borderos returned as
  byte-exact `Document` objects.
- `clientId` in `ClientConfig` (`BOOKURIER_CLIENT_ID`) fills the required
  `<client>` element in order XML when `Order::$clientCode` is null.
- Environment-based configuration through `BOOKURIER_USERNAME` (with
  `BOOKURIER_USERID` as a fallback), `BOOKURIER_PASSWORD`,
  `BOOKURIER_CLIENT_ID`, `BOOKURIER_BASE_URL` and `BOOKURIER_TIMEOUT`.
- PHPStan level 8, Laravel Pint and a PHPUnit unit suite wired into `composer ci`.
- A retry/idempotency table in `docs/ERRORS.md` covering reads, `create`,
  `createBatch`, `delete` and pickup.

### Changed

- `ResponseBody::document()` rejects the host's HTML bot-filter challenge page on
  every document endpoint with `UnexpectedResponseException`; `pdf()` and
  `pdfByUniqueId()` additionally require a body starting with `%PDF`.
- `Document::$mimeType` defaults to `application/octet-stream` when the response
  has no `Content-Type` header.
- `createBatch()` parses the response as XML: a non-empty `<err>` raises
  `ApiException` even when `<cmds>` is present, and a body without `<cmds>` raises
  `UnexpectedResponseException`.
- Creating an order without a client code throws `InvalidArgumentException`
  locally, before sending, when neither `Order::$clientCode` nor
  `ClientConfig::clientId` is set.
- `ClientConfig::fromEnvironment()` rejects a non-numeric `BOOKURIER_TIMEOUT` or
  `BOOKURIER_CLIENT_ID` with `InvalidArgumentException`; unset and empty values
  still mean `null`.
- `createBatch()` rejects duplicate `uniqueId` values.
- `pickup()->request()` is marked `@experimental` (physical courier dispatch,
  never exercised live).
- `OrderReference` is constructed positionally; `OrderReference::fromArray()` was
  removed.
- The `-3` error message reads
  `Bookurier returned -3: the request was incomplete (a label request without its msg envelope).`
- Documentation is de-duplicated: `docs/DTOS.md` owns the DTO/enum/`Document`
  tables, `docs/CONFIGURATION.md` owns environment variables, `docs/RESOURCES.md`
  owns the endpoint list, and `docs/ERRORS.md#error-codes` owns the error codes.

### Fixed

- `Order::$district` is validated locally (`InvalidArgumentException`) because the
  live service rejects an order without it; the `-5` code it returns is mapped in
  `ApiException` instead of the generic rejection message.
- AWBs are not `BOK`-only. A live probe across thirteen localities showed two
  shapes: `BOK` plus seven digits on the own network and twelve bare digits on
  partner routes (Cluj, Iași, Timiș, Suceava, Bihor); a mixed batch returns both
  comma-separated. `AWB_PATTERN` already accepted both and is now commented as
  deliberately lenient, with tests for the 12-digit shape and mixed-shape pairing.

### Notes

- There is no OpenAPI schema and no sandbox. The wire contract was built from the
  documentation and checked against the live service with Bookurier's test
  account, which corrected or confirmed the documentation on: the `BOK` AWB prefix, the `-1`/`-2`/`-3` error codes,
  the mandatory `<msg>` envelope, the required `<client>` code, and that `unq` is
  not enforced unique. `get_history.php` (history by AWB) answers `-2` even for a
  valid AWB; use `historyByUniqueId()`. `add_cmds.php` answers a comma separated
  AWB list with no `unq`, so batch results are paired positionally. The host's bot
  filter answers an HTML challenge page after a burst of requests; see
  `docs/ERRORS.md`.
- Every endpoint was exercised against the live test account except
  `pickup()->request()` (it dispatches a physical courier, so it is covered by
  unit tests only).
- Bookurier returns HTTP 200 even for errors; the SDK classifies responses by
  body, not status code.
- The documentation lists `http://` URLs. The SDK defaults to the same host over
  HTTPS (`https://www.bookurier.ro/colete/serv`).
- Credentials are the Bookurier account username and password, sent as `userid`
  and `pwd` on every form POST. They are not an API key.
