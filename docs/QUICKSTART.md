# Quickstart

A complete flow against the October 2026 portal API: point the client at your
portal account and pickup point, create one AWB, persist it, download its label,
and read its history.

## 1. Install

```bash
composer require daika7ana/bookurier-php-sdk:^2.0
```

PHP 8.3+ and Composer 2 are required. See [Installation](INSTALLATION.md).

## 2. Configure credentials and pickup point

Set the environment variables (preferred for a service) and let the client read
them:

```bash
export BOOKURIER_USERNAME=your-portal-user
export BOOKURIER_PASSWORD=your-portal-password
export BOOKURIER_PICKUP_POINT=5
```

```php
use Daika7ana\Bookurier\Client;

$client = Client::create();
```

Or pass the same values explicitly:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

The pickup point is the portal pickup-point number. It is required on every
order; `Order::$pickupPoint` overrides the configured value. See
[Configuration](CONFIGURATION.md) and [Authentication](AUTHENTICATION.md).

## 3. Create an order

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$order = new Order(
    uniqueId: 'A1',                    // your own order id
    recipientName: 'Ionescu Vasile',
    phone: '0744666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',
    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sector 1',              // county, or Sector 1..6 for Bucharest
    cashOnDelivery: 100.65,
    notes: 'De sunat cu 1/2 inainte',
);

$awb = $client->orders()->create($order);

// Persist this immediately. Labels, history and every later call use the AWB;
// there are no by-unique-id lookups.
// store $awb in your own database, keyed by 'A1'
```

`create()` returns the AWB as a string, for example `BOK6000123456`. Treat it as
an opaque string: the SDK does not validate or parse its shape. `uniqueId` is
your correlation id; it is not a deduplication key across separate calls.

To create several orders in one call, pass a list to `createBatch()`. It returns
a `BatchResult` with references and per-order errors, and partial success is
possible. See [Usage](USAGE.md#create-a-batch).

## 4. Download the PDF label

```php
$label = $client->labels()->pdf($awb); // default mode, page 0

if (! $label->isPdf()) {
    throw new \RuntimeException('Expected a PDF label, got ' . $label->mimeType);
}

file_put_contents("awb-{$awb}.pdf", $label->contents);
```

`labels()->html($awb)` returns the HTML variant. Both accept one AWB or a list.
`Document::isPdf()` sniffs the body for a `%PDF` header; `pdf()` also rejects a
non-PDF body with `UnexpectedStatusException` before returning.

## 5. Read the history and current status

```php
$history = $client->orders()->history($awb);

foreach ($history as $event) {
    echo $event->date, ' ', $event->statusName, PHP_EOL;
}

$events = $history->all();
$current = $events === [] ? null : $events[count($events) - 1];

if ($current !== null && $current->status()?->isFinal()) {
    // No need to poll any more.
}
```

The vendor returns events chronologically, so the last event is the current
status. `HistoryEvent::status()` returns a `Status` enum or `null`; `null` occurs
for a non-numeric id, an unknown numeric id, and partner-courier free text
(empty `status_id`).
`Status::isFinal()` covers `NotPicked`, `Completed`, `ReturnToSenderConfirmed`,
`RefundPaid`, `Delivered` and `ReturnCompleted`. See [DTOs](DTOS.md#status-enum).

## 6. Handle errors

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
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
    // DNS, TLS, timeout or connection failure; getPrevious() holds the cause.
} catch (UnexpectedStatusException $e) {
    // Unexpected body, challenge page, rate limit or missing AWB.
}
```

A `429` is an `UnexpectedStatusException` with `$e->retryAfter`. There is no
automatic retry. For a create call, reconcile the daily bordero and your own data
before retrying, because a retry can create a second AWB. See [Errors](ERRORS.md).

## 7. Cancellation

There is no delete endpoint. An AWB that is never handed to the courier is not
billed: do not hand over the parcel and mark the AWB ignored in your own system.
