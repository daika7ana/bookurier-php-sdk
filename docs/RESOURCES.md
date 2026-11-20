# Resources

All endpoints are `POST` form submissions relative to
`https://www.bookurier.ro/colete/serv`. Every request carries `userid`, `pwd` and,
where the endpoint takes input, a `msg` XML string. The client returns the
response body to the resource method, which maps it to a typed value.

Every resource method below can throw:

- `Daika7ana\Bookurier\Exceptions\AuthenticationException` — `userId` or
  `password` is empty; thrown before a request is sent.
- `Daika7ana\Bookurier\Exceptions\TransportException` — the HTTP transport failed.
- `Daika7ana\Bookurier\Exceptions\ApiException` — Bookurier answered a negative
  code matching `/^-\d+$/`, or a non-empty `<err>` on an XML endpoint.
- `Daika7ana\Bookurier\Exceptions\UnexpectedResponseException` — the body was
  empty, could not be parsed, was a bot-filter challenge page, or was not the
  documented success value.

The lists below name additional exceptions where they apply. See
[ERRORS.md](ERRORS.md) for the response-classification rules.

Examples assume `$client` is configured as in the
[README quick start](../README.md#quick-start).

Bookurier publishes no sandbox and no OpenAPI schema. The payloads and return
shapes here follow the documentation and the SDK implementation, as checked
against the live service.

## Orders — `$client->orders()`

| Method | Endpoint | Returns |
|---|---|---|
| `create(Order $order)` | `add_cmd.php` | `string` (AWB) |
| `createBatch(array $orders)` | `add_cmds.php` | `Collection<int, OrderReference>` |
| `delete(string $awb)` | `del_cmd.php` | `void` |
| `deleteByUniqueId(string $uniqueId)` | `del_cmd_unq.php` | `void` |
| `status(string $awb)` | `get_stat.php` | `OrderStatus` |
| `statusByUniqueId(string $uniqueId)` | `get_stat_unq.php` | `OrderStatus` |
| `history(string $awb)` | `get_history.php` | `Collection<int, HistoryEntry>` |
| `historyByUniqueId(string $uniqueId)` | `get_history_unq.php` | `Collection<int, HistoryEntry>` |

### `create(Order $order): string`

Creates one order and returns the generated AWB.

- **Endpoint:** `add_cmd.php`
- **Form:** `msg=<msg><cmd>...</cmd></msg>`
- **Parameters:** `$order` — a populated `Order` DTO. The `<client>` element comes
  from `Order::$clientCode`, or from `ClientConfig::clientId` when that is null;
  with neither set the call throws `InvalidArgumentException` before sending.
  `Order::$district` (sector or county) is required too: an empty value throws
  `InvalidArgumentException` locally, and the service answers `-5` if it reaches
  the API.
- **Returns:** the AWB as a string. Formats vary server-side by destination
  county and service: `BOK` plus seven digits (`BOK3925457`), twelve digits
  (`062568455929`), or `CKC` plus six digits for Bucharest Today service `7`
  (`CKC122336`). The SDK treats the AWB as an opaque string and validates with
  the lenient `/^[A-Za-z]{0,4}\d{3,}$/`.
- **Throws:** `AuthenticationException`, `ApiException`, `TransportException`,
  `InvalidArgumentException` (no client code or empty district), and `UnexpectedResponseException`
  when the body is not a negative code and does not match the AWB pattern.
  Negative codes raise `ApiException` naming the code (see [Errors](ERRORS.md)).

```php
$awb = $client->orders()->create(new Order(
    uniqueId: 'A1',
    recipientName: 'Ionescu Vasile',
    phone: '0744-666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',

    service: ServiceType::Bucharest24h,
    weight: 0.5,
));
```

### `createBatch(array $orders): Collection`

Creates several orders in one call.

- **Endpoint:** `add_cmds.php`
- **Form:** `msg=<msg><cmds><cmd>...</cmd><cmd>...</cmd></cmds></msg>`
- **Parameters:** `$orders` — a non-empty list of `Order` objects with distinct
  `uniqueId` values. The resolved client code rules are the same as `create()`.
- **Returns:** `Collection<int, OrderReference>` with one entry per order, in the
  order you sent them.
- **Throws:** `AuthenticationException`, `ApiException`, `TransportException`,
  `UnexpectedResponseException`, and `InvalidArgumentException` when `$orders` is
  empty, two entries share a non-empty `uniqueId`, or no client code is available.
- **Body handling:** a non-empty `<err>` raises `ApiException` (even when
  `<cmds>` is also present). Otherwise the API answers
  `<msg><cmds>BOK1,BOK2</cmds></msg>` — a comma separated list of AWBs with no
  `unq` echoed. A missing `<cmds>` element raises `UnexpectedResponseException`
  before pairing, as does an AWB count that does not match the input list or a
  malformed AWB.

```php
$references = $client->orders()->createBatch([$order, $secondOrder]);

foreach ($references as $reference) {
    echo $reference->uniqueId, ' => ', $reference->awb, PHP_EOL;
}
```

### `delete(string $awb): void`

Deletes an order by AWB.

- **Endpoint:** `del_cmd.php`
- **Form:** `msg=<msg><cmd><awb>...</awb></cmd></msg>`
- **Parameters:** `$awb` — the complete AWB.
- **Returns:** `void`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** Bookurier must answer `1`; a body matching `/^-\d+$/`
  raises `ApiException`, and any other non-`1` body raises
  `UnexpectedResponseException`.

### `deleteByUniqueId(string $uniqueId): void`

Deletes an order by your own order id.

- **Endpoint:** `del_cmd_unq.php`
- **Form:** `msg=<msg><cmd><unq>...</unq></cmd></msg>`
- **Parameters:** `$uniqueId` — the `uniqueId` you sent when creating the order.
- **Returns:** `void`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `delete()`.

### `status(string $awb): OrderStatus`

Returns the current status of an order.

- **Endpoint:** `get_stat.php`
- **Form:** `msg=<msg><cmd><awb>...</awb></cmd></msg>`
- **Parameters:** `$awb` — the complete AWB.
- **Returns:** `OrderStatus`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** parsed as XML. A non-empty `<err>` raises `ApiException`.

### `statusByUniqueId(string $uniqueId): OrderStatus`

Returns the current status by your own order id.

- **Endpoint:** `get_stat_unq.php`
- **Form:** `msg=<msg><cmd><unq>...</unq></cmd></msg>`
- **Parameters:** `$uniqueId` — your order id.
- **Returns:** `OrderStatus`.
- **Throws:** the four common exceptions listed above.

### `history(string $awb): Collection`

Returns every recorded status change for an order.

- **Endpoint:** `get_history.php`
- **Form:** `msg=<msg><cmd><awb>...</awb></cmd></msg>`
- **Parameters:** `$awb` — the complete AWB.
- **Returns:** `Collection<int, HistoryEntry>`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** parsed as XML. A non-empty `<err>` raises `ApiException`.
- **Known limitation:** the live service answers `-2` on this endpoint even for a
  valid AWB, so it raises `ApiException`. Use `historyByUniqueId()` instead.

```php
foreach ($client->orders()->history($awb) as $entry) {
    echo $entry->date ?? '', ' ', $entry->statusDescription, PHP_EOL;
}
```

### `historyByUniqueId(string $uniqueId): Collection`

Returns the status history by your own order id.

- **Endpoint:** `get_history_unq.php`
- **Form:** `msg=<msg><cmd><unq>...</unq></cmd></msg>`
- **Parameters:** `$uniqueId` — your order id.
- **Returns:** `Collection<int, HistoryEntry>`.
- **Throws:** the four common exceptions listed above.

## Labels — `$client->labels()`

| Method | Endpoint | Returns |
|---|---|---|
| `html(string $awb)` | `get_AWB.php` | `Document` |
| `pdf(string $awb)` | `get_AWB_pdf.php` | `Document` |
| `htmlByUniqueId(string $uniqueId)` | `get_AWB_unq.php` | `Document` |
| `pdfByUniqueId(string $uniqueId)` | `get_AWB_unq_pdf.php` | `Document` |
| `daily()` | `get_AWB_list.php` | `Document` |

### `html(string $awb): Document`

Returns the AWB label as HTML.

- **Endpoint:** `get_AWB.php`
- **Form:** `msg=<msg><cmd><awb>...</awb></cmd></msg>`
- **Parameters:** `$awb` — the complete AWB.
- **Returns:** `Document` with the raw HTML body.
- **Throws:** the four common exceptions listed above.
- **Body handling:** a trimmed body matching `/^-\d+$/` raises `ApiException`; an
  empty body or the host's HTML challenge page raises
  `UnexpectedResponseException`. Any other body is returned as a `Document`.

### `pdf(string $awb): Document`

Returns the AWB label as PDF.

- **Endpoint:** `get_AWB_pdf.php`
- **Form:** `msg=<msg><cmd><awb>...</awb></cmd></msg>`
- **Parameters:** `$awb` — the complete AWB.
- **Returns:** `Document` with the raw PDF body.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `html()`, but the body must also begin with `%PDF`
  after leading whitespace; otherwise `UnexpectedResponseException` is thrown.
  `Document::isPdf()` reflects the same check.

### `htmlByUniqueId(string $uniqueId): Document`

Returns the AWB label as HTML by your own order id.

- **Endpoint:** `get_AWB_unq.php`
- **Form:** `msg=<msg><cmd><unq>...</unq></cmd></msg>`
- **Parameters:** `$uniqueId` — your order id.
- **Returns:** `Document`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `html()`.

### `pdfByUniqueId(string $uniqueId): Document`

Returns the AWB label as PDF by your own order id.

- **Endpoint:** `get_AWB_unq_pdf.php`
- **Form:** `msg=<msg><cmd><unq>...</unq></cmd></msg>`
- **Parameters:** `$uniqueId` — your order id.
- **Returns:** `Document`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `pdf()`.

### `daily(): Document`

Returns the printable labels for every order of the day in one HTML document.

- **Endpoint:** `get_AWB_list.php`
- **Form:** `userid` and `pwd` only.
- **Returns:** `Document` whose `mimeType` is the response `Content-Type`, or
  `application/octet-stream` when the header is absent.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `html()`.

## Reports — `$client->reports()`

### `dailyBordero(): Document`

Returns the daily order list (bordero) as HTML.

- **Endpoint:** `get_cmd_list.php`
- **Form:** `userid` and `pwd` only.
- **Returns:** `Document`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `html()`.

## Pickup — `$client->pickup()`

### `request(): Document` — `@experimental`

Asks Bookurier for a courier pickup and returns the pickup bordero as HTML. This
method dispatches a physical courier, has never been exercised against the live
service, and is covered by unit tests only.

- **Endpoint:** `request_delivery.php`
- **Form:** `userid` and `pwd` only.
- **Returns:** `Document`.
- **Throws:** the four common exceptions listed above.
- **Body handling:** same as `dailyBordero()`.

## Request and Response Objects

The request DTO, the service enum and the response objects are documented once,
in [DTOs](DTOS.md):

- [`Order` request fields](DTOS.md#order-request-dto) — XML tag for every
  constructor argument and the serialization rules.
- [`ServiceType` enum](DTOS.md#servicetype-enum) — the service codes and their labels.
- [Response DTOs](DTOS.md#response-dtos) — `OrderStatus`, `HistoryEntry` and
  `OrderReference`.
- [`Document`](DTOS.md#document) — `$contents`, `$mimeType` and `isPdf()`.

`createBatch()` builds each `OrderReference` positionally from your `Order` and
the AWB at the same index; `OrderReference::fromArray()` no longer exists.

## Client Helpers

| Method | Purpose |
|---|---|
| `Client::create(httpClient:, requestFactory:, streamFactory:, config:)` | Build a client with defaults; all arguments optional |
| `getConfig(): ClientConfig` | Current configuration |
| `setCredentials(string $userId, string $password): self` | Replace credentials and return the same client |
| `createRequest(string $method, string $path): RequestInterface` | Build a PSR-7 request for a path under the configured base URL |
| `post(string $endpoint, array $form = [], ?float $timeout = null): ResponseInterface` | Form POST with credentials; no body mapping |
| `send(RequestInterface $request, ?float $timeout = null): ResponseInterface` | Send a prepared request; no credentials added |

`post()` throws `AuthenticationException`, `TransportException` and
`InvalidArgumentException` (a per-call timeout that cannot be applied to the
transport). `send()` throws `TransportException` and `InvalidArgumentException`.
Neither method classifies the response body; use a resource method for typed
results.
