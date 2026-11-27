# Resources

All endpoints are relative to `https://portal.bookurier.ro/api`. Account
endpoints receive the portal `user` and `pwd`; `awb_history.php` is a public GET
that needs only the tracking key.

Every resource method below can throw:

- `Daika7ana\Bookurier\Exceptions\AuthenticationException` — credentials are
  missing (before sending), the portal answers `Invalid user` / `Login fail`, or
  HTTP 401.
- `Daika7ana\Bookurier\Exceptions\ValidationException` — the portal answers any
  other JSON `status: "error"` message, or HTTP 400/422.
- `Daika7ana\Bookurier\Exceptions\TransportException` — the HTTP transport failed.
- `Daika7ana\Bookurier\Exceptions\UnexpectedStatusException` — an unexpected
  body, a challenge page, a rate limit, or a missing AWB.

Local argument errors throw `InvalidArgumentException` and send nothing. See
[ERRORS.md](ERRORS.md) for the classification rules.

Examples assume `$client` is configured as in the
[README quick start](../README.md#quick-start).

## Endpoint map

| Resource | Method | Endpoint | Transport |
|---|---|---|---|
| `orders()` | `create`, `createBatch` | `add_cmds.php` | JSON POST |
| `orders()` | `history` | `awb_history.php` | public GET |
| `labels()` | `pdf`, `html` | `print_awbs.php` | JSON POST |
| `reports()` | `borderou` | `print_borderou.php` | form POST |
| `pickup()` | `request` | `request_pickup.php` | form POST |
| `cities()` | `partnerCities` | `get_partner_cities.php` | JSON POST |

There is no cancellation/delete endpoint and no standalone status endpoint.

## Orders — `$client->orders()`

| Method | Returns |
|---|---|
| `create(Order $order)` | `string` (AWB) |
| `createBatch(array $orders)` | `BatchResult` |
| `history(string $awb)` | `Collection<int, HistoryEvent>` |

### `create(Order $order): string`

Creates one order and returns its AWB.

- **Endpoint:** `add_cmds.php`, JSON `{"data": [order]}`.
- **Required:** a pickup point (`Order::$pickupPoint` or
  `ClientConfig::pickupPoint`) and a non-empty `district`. Either missing throws
  `InvalidArgumentException` before sending.
- **Returns:** the AWB, for example `BOK6000123456`. Treat it as opaque; the SDK
  does not validate the pattern.
- **Persist it immediately.** Labels and history are AWB-only; there are no
  by-unique-id lookups.
- **Per-order error:** if the response `errors` list names the order, `create()`
  throws `ValidationException` with the message and suggestion appended.
- **Missing AWB:** a success envelope with neither a map entry for the order nor
  a first `data` AWB raises `UnexpectedStatusException`.

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$awb = $client->orders()->create(new Order(
    uniqueId: 'A1',
    recipientName: 'Ionescu Vasile',
    phone: '0744666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',
    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sector 1',
));
```

### `createBatch(array $orders): BatchResult`

Creates several orders in one call.

- **Endpoint:** `add_cmds.php`, JSON `{"data": [order, order, ...]}`.
- **Parameters:** a non-empty list of `Order` objects. Each needs a pickup point
  and a non-empty `district`.
- **Returns:** `BatchResult` with `references()`, `errors()` and `hasErrors()`.
  The response map is keyed by `unq`, so `references()` contains one
  `OrderReference` per order the map names.
- **Partial success:** expected. Some orders can get AWBs while others are
  rejected. Process both `references()` and `errors()`; per-order failures are
  not thrown.
- **Local errors:** an empty list, or two orders sharing a non-empty `uniqueId`,
  throws `InvalidArgumentException` before sending. The duplicate check exists
  because the response map cannot distinguish two identical `unq` values.
- **Top-level errors:** a JSON error envelope, an authentication failure, a
  transport failure or an unexpected body still throws instead of returning a
  result.

```php
$result = $client->orders()->createBatch([$firstOrder, $secondOrder]);

foreach ($result->references() as $reference) {
    echo $reference->uniqueId, ' => ', $reference->awb, PHP_EOL;
}

if ($result->hasErrors()) {
    foreach ($result->errors() as $error) {
        echo $error->order, ': ', $error->message, PHP_EOL;
    }
}
```

### `history(string $awb): Collection`

Returns every recorded event for an AWB.

- **Endpoint:** `awb_history.php?key=<tracking-key>&awb=<awb>`, public GET.
- **Credentials:** none. The key is `ClientConfig::$trackingKey`, falling back to
  `ClientConfig::DEFAULT_TRACKING_KEY`.
- **Returns:** `Collection<int, HistoryEvent>`, chronological; the last event is
  the current status.
- **Partner text:** an empty `status_id` means partner-courier free text.
  `statusName` carries the message and `status()` returns `null`. Non-numeric and
  unknown numeric ids also return `null`.
- **Final states:** `Status::isFinal()` covers `NotPicked`, `Completed`,
  `ReturnToSenderConfirmed`, `RefundPaid`, `Delivered` and `ReturnCompleted`.
  Stop polling once the latest event resolves to one.

```php
$events = $client->orders()->history($awb)->all();
$current = $events === [] ? null : $events[count($events) - 1];
```

## Labels — `$client->labels()`

| Method | Returns |
|---|---|
| `pdf(string\|array $awbs, ?string $mode = null, int $page = 0)` | `Document` |
| `html(string\|array $awbs, ?string $mode = null, int $page = 0)` | `Document` |

Both methods post JSON to `print_awbs.php` with `data` (one AWB or a list),
`format` (`pdf` or `html`), `page`, and `mode` when it is not `null`.

- **`$mode`:** `'s'` or `'m'`. When `null`, the field is omitted and the vendor
  default (`m`) applies.
- **`$page`:** defaults to `0`.
- **Returns:** a `Document` whose `contents` is the raw body.
- **PDF guard:** `pdf()` rejects a body that does not start with `%PDF` with
  `UnexpectedStatusException`.
- **Errors:** a JSON error envelope raises `AuthenticationException` or
  `ValidationException`; an empty body, JSON success body or challenge page
  raises `UnexpectedStatusException`.

```php
$label = $client->labels()->pdf($awb);
file_put_contents("awb-{$awb}.pdf", $label->contents);

$labels = $client->labels()->html([$awb1, $awb2], 'm', 0);
```

## Reports — `$client->reports()`

### `borderou(string $date, ?string $pickupPoint = null, ?float $timeout = null): Document`

Returns the printable bordero for one pickup point and date.

- **Endpoint:** `print_borderou.php`, form POST.
- **`$date`:** required; `yyyy-mm-dd` or `dd-mm-yyyy`.
- **`$pickupPoint`:** defaults to `ClientConfig::pickupPoint`; a missing value
  throws `InvalidArgumentException` before sending.
- **`$timeout`:** optional per-call timeout.
- **Returns:** an HTML `Document`.

```php
$document = $client->reports()->borderou('2026-11-26');
file_put_contents('bordero.html', $document->contents);
```

## Pickup — `$client->pickup()`

### `request(?string $pickupPoint = null): string` — `@experimental`

Asks Bookurier for a courier pickup.

- **Endpoint:** `request_pickup.php`, form POST.
- **`$pickupPoint`:** defaults to `ClientConfig::pickupPoint`; a missing value
  throws `InvalidArgumentException` before sending.
- **Returns:** the confirmation message from the JSON response.
- **Experimental:** it dispatches a physical courier, has never been exercised
  against the live service, and may change shape in a minor release. Never
  auto-retry it.

```php
$message = $client->pickup()->request();
```

## Cities — `$client->cities()`

### `partnerCities(?string $district = null): Collection`

Returns partner-served cities.

- **Endpoint:** `get_partner_cities.php`, JSON POST with `data` as `{}` or
  `{"district": "..."}`.
- **Returns:** `Collection<int, PartnerCity>`.
- **Scope:** partner-served cities only; Bucharest and Ilfov are excluded.
- **Filter:** the district filter is diacritics-insensitive.

```php
foreach ($client->cities()->partnerCities() as $city) {
    echo $city->city, ' ', $city->district, PHP_EOL;
}
```

## Client helpers

| Method | Purpose |
|---|---|
| `Client::create(httpClient:, requestFactory:, streamFactory:, config:)` | Build a client with defaults; all arguments optional |
| `getConfig(): ClientConfig` | Current configuration |
| `withTimeout(?float $timeout): self` | New client sharing the transport and configuration, with a different timeout |
| `setCredentials(string $userId, string $password): self` | Replace credentials and return the same client |
| `createRequest(string $method, string $path): RequestInterface` | Build a PSR-7 request for a path under the configured base URL |
| `postJson(string $endpoint, array $payload, ?float $timeout = null): ResponseInterface` | JSON POST with credentials; no body mapping |
| `postForm(string $endpoint, array $fields = [], ?float $timeout = null): ResponseInterface` | Form POST with credentials; no body mapping |
| `get(string $endpoint, array $query = [], ?float $timeout = null): ResponseInterface` | Public GET; no credentials |
| `send(RequestInterface $request, ?float $timeout = null): ResponseInterface` | Send a prepared request; no credentials added |

The low-level methods do not classify the response body; use a resource method for
typed results. `postJson()` and `postForm()` throw `AuthenticationException` when
credentials are missing. `send()` and `get()` do not.
