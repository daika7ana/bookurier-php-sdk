# Usage

Examples assume this client:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    clientId: 1234,
));
```

## Create an Order

`Order` is a `final readonly` request DTO. `uniqueId` is your own order
identifier. Bookurier does **not** enforce it as unique — submitting the same
`uniqueId` twice creates two orders — so keep it unique in your own system to
correlate status, history and labels.

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$order = new Order(
    uniqueId: 'A1',
    recipientName: 'Ionescu Vasile',
    phone: '0744-666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',

    service: ServiceType::Bucharest24h,
    weight: 0.5,
    packages: 1,
    district: 'Sect 1',
    email: 'IV@yahoo.com',
    block: '114',
    entrance: '1',
    floor: '7',
    apartment: '29',
    interphone: '29',
    cashOnDelivery: 100.65,
    notes: 'De sunat cu 1/2 inainte',
);

$awb = $client->orders()->create($order);

echo $awb; // e.g. BOK3925457
```

Optional address and shipment fields may be omitted. Null optional values are not
sent; booleans are sent as `1` or `0`. `country` defaults to `Romania` and
`packages` defaults to `1`. `cashOnDelivery` and `insuredValue` are amounts in
RON. `clientCode` is your Bookurier client code; when it is `null` the value of
`ClientConfig::clientId` is used, and with neither set `create()` throws
`InvalidArgumentException` before sending.

`create()` returns the AWB as a string. Formats vary server-side by destination
county and service: `BOK` plus seven digits (`BOK3925457`), twelve digits
(`062568455929`), or `CKC` plus six digits for Bucharest Today
(`CKC122336`). The SDK treats the AWB as an opaque string and validates with the
lenient `/^[A-Za-z]{0,4}\d{3,}$/`. A body matching `/^-\d+$/` raises
`ApiException`; any other non-AWB body raises `UnexpectedResponseException`.

## Create Several Orders

`createBatch()` accepts a non-empty list of `Order` objects:

```php
$references = $client->orders()->createBatch([
    $order,
    new Order(
        uniqueId: 'A2',
        recipientName: 'Popescu Maria',
        phone: '0745-123456',
        city: 'Cluj-Napoca',
        street: 'Memorandumului nr. 12',

        service: ServiceType::National24,
        weight: 1.2,
        district: 'Cluj',
    ),
]);

foreach ($references as $reference) {
    echo $reference->uniqueId, ' => ', $reference->awb, PHP_EOL;
}
```

The result is a `Daika7ana\SimpleCollections\Collection` of `OrderReference`
objects. An empty array, two orders sharing a non-empty `uniqueId`, or a missing
client code throw `InvalidArgumentException` before any request is made.

Bookurier answers the batch call with a comma separated AWB list
(`<msg><cmds>BOK1,BOK2</cmds></msg>`) and does not echo your `unq`, so the SDK
pairs each AWB with the order at the same position. A non-empty `<err>` raises
`ApiException`; a missing `<cmds>`, mismatched counts, or a malformed AWB raises
`UnexpectedResponseException` — check the daily label list before retrying,
because the orders may already exist. The positional-pairing rule is documented
canonically in [Resources](RESOURCES.md#createbatcharray-orders-collection).

## Read Status and History

Look up by AWB or by your own `uniqueId`:

```php
$status = $client->orders()->status($awb);

echo $status->uniqueId;          // your order id
echo $status->awb;               // Bookurier AWB
echo $status->statusId;          // integer status code
echo $status->statusDescription; // human-readable status
echo $status->observations;      // ?string

$sameStatus = $client->orders()->statusByUniqueId('A1');
```

History returns every recorded status change:

```php
$history = $client->orders()->historyByUniqueId('A1');

foreach ($history as $entry) {
    echo $entry->date ?? '', ' ', $entry->statusDescription, PHP_EOL;
}
```

`HistoryEntry::$date` is the raw `Y-m-d H:i:s` string Bookurier sends, with no
timezone attached. `HistoryEntry::$observations` and `$date` are `null` when the
element is empty.

`historyByUniqueId()` is the working history endpoint. `history($awb)` answers
`-2` on the live service even for a valid AWB; see the canonical note in
[Resources](RESOURCES.md#historystring-awb-collection). `status($awb)` and
`statusByUniqueId()` do work.

`statusByUniqueId()` and `historyByUniqueId()` take the same `uniqueId` you sent
when creating the order.

## Delete an Order

```php
$client->orders()->delete($awb);          // by AWB
$client->orders()->deleteByUniqueId('A1'); // by your order id
```

Both methods return `void`. Bookurier must answer `1`; a body matching `/^-\d+$/`
raises `ApiException`, and any other body is reported as
`UnexpectedResponseException`.

## Labels and Documents

Labels are returned as `Daika7ana\Bookurier\Support\Document` objects:

```php
$pdf = $client->labels()->pdf($awb);
file_put_contents("awb-{$awb}.pdf", $pdf->contents);

$html = $client->labels()->html($awb);
file_put_contents("awb-{$awb}.html", $html->contents);
```

The same label can be fetched by your own order id:

```php
$pdf = $client->labels()->pdfByUniqueId('A1');
$html = $client->labels()->htmlByUniqueId('A1');
```

`Document` exposes `$contents`, `$mimeType` and `isPdf()`; the field reference is
in [DTOs](DTOS.md#document).

`isPdf()` inspects the body, not `$mimeType`. The `pdf()` and `pdfByUniqueId()`
methods reject a body that does not start with `%PDF` with
`UnexpectedResponseException`; the check remains useful for any document you
receive through another path.

## Daily Labels, Bordero and Pickup

```php
$labels = $client->labels()->daily();       // all labels for the day, HTML
$bordero = $client->reports()->dailyBordero(); // daily order list, HTML
$pickup = $client->pickup()->request();      // courier pickup bordero, HTML
```

All three return `Document`. Write `$document->contents` to a file or pass the
string to your own renderer. The pickup bordero is HTML; the endpoint does not
send a separate request body, so the SDK posts only `userid` and `pwd`.

`pickup()->request()` is `@experimental`: it dispatches a physical courier, has
never been exercised against the live service, and is covered by unit tests only.

## Low-Level Requests

The client exposes the form POST it uses internally:

```php
use Psr\Http\Message\ResponseInterface;

$response = $client->post('get_AWB_list.php', timeout: 15.0);

assert($response instanceof ResponseInterface);
echo (string) $response->getBody();
```

`Client::post()` adds credentials and returns an unparsed PSR-7 response. Use it
for an endpoint the SDK does not wrap, or when you need a per-call timeout. The
resource methods are preferable when one exists, because they map the body to
typed objects and raise the SDK exceptions.

`Client::send()` sends a `Psr\Http\Message\RequestInterface` you built yourself.
It does not add credentials; use `Client::post()` unless you are supplying a fully
formed request.
