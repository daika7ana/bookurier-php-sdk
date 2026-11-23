# Quickstart

A complete flow: install, configure credentials, create one AWB, download its PDF
label, read its status, then delete the order.

## 1. Install

```bash
composer require daika7ana/bookurier-php-sdk
```

PHP 8.3+, `ext-dom` and Composer 2 are required. See
[Installation](INSTALLATION.md).

## 2. Configure credentials

Set the environment variables (preferred for a service) and let the client read
them:

```bash
export BOOKURIER_USERNAME=your-bookurier-user
export BOOKURIER_PASSWORD=your-bookurier-password
export BOOKURIER_CLIENT_ID=1234
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
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    clientId: 1234, // your Bookurier client code; required as <client>
));
```

`clientId` is your Bookurier client code. Order creation needs it in the XML as
`<client>`; with neither `clientId` nor `Order::$clientCode` set, `create()` and
`createBatch()` throw `InvalidArgumentException` before sending. See
[Authentication](AUTHENTICATION.md).

## 3. Create an order

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$order = new Order(
    uniqueId: 'A1',                    // your own order id; not a dedup key
    recipientName: 'Ionescu Vasile',
    phone: '0744-666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu nr. 6',

    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sect 1',
    cashOnDelivery: 100.65,
    notes: 'De sunat cu 1/2 inainte',
);

$awb = $client->orders()->create($order);

echo $awb; // e.g. BOK3925457
```

`create()` returns the AWB as a string. Formats vary server-side by destination
county and service: `BOK` plus seven digits (`BOK3925457`), twelve digits
(`062568455929`), or `CKC` plus six digits (`CKC122336`); the validator is the
lenient `/^[A-Za-z]{0,4}\d{3,}$/`. `uniqueId` is your correlation id only;
Bookurier does not enforce it, and submitting the same value twice creates two
orders. To create several orders in one call, pass the list to `createBatch()`; it
returns one `OrderReference` per order, paired positionally. See [DTOs](DTOS.md)
for every field.

## 4. Download the PDF label

```php
$label = $client->labels()->pdf($awb);

if (! $label->isPdf()) {
    throw new \RuntimeException('Expected a PDF label, got ' . $label->mimeType);
}

file_put_contents("awb-{$awb}.pdf", $label->contents);
```

`labels()->html($awb)` returns the HTML variant. `Document::isPdf()` sniffs the
body for a `%PDF` header rather than trusting `Content-Type`. The SDK already
rejects the host's bot-filter challenge page with `UnexpectedStatusException`,
and `pdf()` requires a `%PDF` body; the explicit check catches any other
mislabeled response before you write a broken file.

## 5. Read the status

```php
$status = $client->orders()->status($awb);

echo $status->statusId, ' ', $status->statusDescription, PHP_EOL;
echo $status->observations ?? '', PHP_EOL;
```

`statusByUniqueId('A1')` looks the order up by your own id instead.

History by AWB (`history($awb)`) answers `-2` on the live service even for a
valid AWB. Use the unique-id path (see
[Resources](RESOURCES.md#historystring-awb-collection)):

```php
foreach ($client->orders()->historyByUniqueId('A1') as $entry) {
    echo $entry->date ?? '', ' ', $entry->statusDescription, PHP_EOL;
}
```

## 6. Delete the order

```php
$client->orders()->delete($awb);           // by AWB
$client->orders()->deleteByUniqueId('A1'); // by your order id
```

Both return `void` and expect the body `1`.

## 7. Handle errors

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // userId or password is empty (nothing was sent), or Bookurier answered -1.
} catch (ValidationException $e) {
    // Bookurier answered -2, -3 or -5, or a non-empty <err>.
} catch (TransportException $e) {
    // DNS, TLS, timeout or connection failure; getPrevious() holds the cause.
} catch (UnexpectedStatusException $e) {
    // Empty, non-XML, non-AWB or bot-filter body.
}
```

Bookurier returns HTTP 200 for errors, so the SDK classifies by body. See
[Errors](ERRORS.md) before retrying: a failed batch may already have created
orders.
