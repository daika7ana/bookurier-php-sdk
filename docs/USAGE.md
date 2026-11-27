# Usage

Examples assume this client:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

`pickupPoint` is the portal pickup-point number. `Order::$pickupPoint` overrides
it per order.

## Create an order

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$order = new Order(
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
);

$awb = $client->orders()->create($order);

// Persist the AWB now: labels, history and every later call use it.
```

`create()` returns the AWB as a string, for example `BOK6000123456`. There are no
by-unique-id lookups, so an AWB you do not persist cannot be recovered through
the SDK. `uniqueId` is your correlation id, not a deduplication key: two separate
calls with the same value create two orders.

`district` is required in practice: a county, or `Sector 1`..`Sector 6` for
Bucharest. An empty value throws `InvalidArgumentException` before sending.

Field-level wire names and vendor length limits are in [DTOs](DTOS.md#order-request-dto).

## Create a batch

```php
$result = $client->orders()->createBatch([
    $order,
    new Order(
        uniqueId: 'A2',
        recipientName: 'Popescu Maria',
        phone: '0745123456',
        city: 'Cluj-Napoca',
        street: 'Memorandumului nr. 12',
        service: ServiceType::National24,
        weight: 1.2,
        district: 'Cluj',
    ),
]);

foreach ($result->references() as $reference) {
    echo $reference->uniqueId, ' => ', $reference->awb, PHP_EOL;
}

foreach ($result->errors() as $error) {
    echo $error->order, ': ', $error->message;
    echo $error->suggestion === null ? '' : ' ' . $error->suggestion;
    echo PHP_EOL;
}
```

`createBatch()` posts all orders in one `add_cmds.php` call and returns a
`BatchResult`:

| Member | Meaning |
|---|---|
| `references()` | `Collection<OrderReference>` for every order the response map names |
| `errors()` | `Collection<OrderError>` for orders the service rejected |
| `hasErrors()` | `true` when `errors()` is not empty |

Partial success is normal: some orders can receive AWBs while others are
rejected. Always inspect `hasErrors()` and process both lists; do not treat a
returned `BatchResult` as all-or-nothing. Per-order failures are not thrown as
exceptions. A top-level JSON error envelope still raises the usual SDK
exceptions.

Local argument errors:

- an empty list throws `InvalidArgumentException`;
- two orders with the same non-empty `uniqueId` throw
  `InvalidArgumentException`, because the response map is keyed by `unq`;
- a missing pickup point or empty `district` throws `InvalidArgumentException`
  before sending.

## Read history and current status

A freshly created AWB has no tracking events yet: expect an empty list until the parcel is scanned.

```php
$history = $client->orders()->history($awb);

foreach ($history as $event) {
    echo $event->date, ' ', $event->statusName, PHP_EOL;
}

$events = $history->all();
$current = $events === [] ? null : $events[count($events) - 1];
```

The vendor returns events chronologically, so the last event is the current
status. `HistoryEvent::status()` returns a `Status` enum or `null`:

```php
if ($current !== null) {
    echo $current->statusName, PHP_EOL;
    echo $current->status()?->name ?? 'partner text', PHP_EOL;

    if ($current->status()?->isFinal()) {
        // Stop polling.
    }
}
```

An empty `status_id` means partner-courier free text; `statusName` still carries
the message and `status()` returns `null`. Non-numeric and unknown numeric ids
also return `null`. See the [Status enum](DTOS.md#status-enum) for ids and final
states.

`history($awb)` is a public GET: it needs the tracking key, not the account
credentials. The configured key is `ClientConfig::$trackingKey`, falling back to
`ClientConfig::DEFAULT_TRACKING_KEY`.

## Labels

```php
$pdf = $client->labels()->pdf($awb);
file_put_contents("awb-{$awb}.pdf", $pdf->contents);

$html = $client->labels()->html($awb);
file_put_contents("awb-{$awb}.html", $html->contents);
```

Both methods accept one AWB or a list:

```php
$pdf = $client->labels()->pdf([$awb1, $awb2], 'm', 0);
```

| Argument | Default | Meaning |
|---|---|---|
| `$awbs` | required | One AWB or a list of AWBs |
| `$mode` | `null` | `'s'` or `'m'`; when null the field is omitted and the vendor default (`m`) applies |
| `$page` | `0` | Passed to the vendor as `page` |

`labels()->pdf()` requires a body starting with `%PDF`; a non-PDF body raises
`UnexpectedStatusException`. `labels()->html()` returns the body as-is.
`Document::isPdf()` sniffs the body, not the header.

## Bordero

```php
$document = $client->reports()->borderou('2026-11-26');

file_put_contents('bordero.html', $document->contents);
```

The date is required and accepts `yyyy-mm-dd` or `dd-mm-yyyy`. The return value is
an HTML `Document`. The pickup point defaults to `ClientConfig::pickupPoint`; pass
one explicitly to override it. `borderou()` also accepts a per-call timeout:

```php
$document = $client->reports()->borderou('2026-11-26', '5', 15.0);
```

## Partner cities

```php
$cities = $client->cities()->partnerCities('Prahova');

foreach ($cities as $city) {
    echo $city->city, ' ', $city->district, ' ', $city->zip, PHP_EOL;
}
```

`partnerCities()` returns `Collection<PartnerCity>` for partner-served cities.
Bucharest and Ilfov are excluded. Pass a district to filter; the filter is
diacritics-insensitive. Omit it for the full list.

## Pickup

```php
$message = $client->pickup()->request(); // @experimental
```

`request()` posts the pickup point to `request_pickup.php` and returns the
confirmation message string. It dispatches a physical courier, has never been
exercised against the live service, and is covered by unit tests only. Never
auto-retry it. The pickup point defaults to `ClientConfig::pickupPoint`; pass one
explicitly to override it.

## Cancellation

There is no delete or cancellation endpoint. An AWB that never enters the courier
network is not billed. If a shipment must be abandoned, do not hand the parcel to
the courier and mark the AWB ignored in your own system.

## Low-level requests

The client exposes the request builders it uses internally:

```php
use Psr\Http\Message\ResponseInterface;

$response = $client->postForm(
    'print_borderou.php',
    ['pickup' => '5', 'filter_date' => '2026-11-26'],
    timeout: 30.0,
);

assert($response instanceof ResponseInterface);
echo (string) $response->getBody();
```

| Method | Credentials | Body |
|---|---|---|
| `postJson(string $endpoint, array $payload, ?float $timeout = null)` | `user` / `pwd` added | JSON |
| `postForm(string $endpoint, array $fields = [], ?float $timeout = null)` | `user` / `pwd` added | form-encoded |
| `get(string $endpoint, array $query = [], ?float $timeout = null)` | none | query string |
| `send(RequestInterface $request, ?float $timeout = null)` | none | as supplied |

These methods return an unparsed PSR-7 response. Use a resource method when one
exists: it maps the body to typed objects and raises the SDK exceptions.
