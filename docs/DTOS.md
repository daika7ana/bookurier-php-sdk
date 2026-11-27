# DTOs and Enums

Request objects are `final readonly` classes with public camelCase properties and
a `toArray()` method. Response objects are `final readonly` classes; most are
built with a `fromArray(array $data): self` factory, while `OrderReference` is
constructed positionally. Wire names keep the vendor's abbreviations and
snake_case; PHP properties stay camelCase.

## `Order` request DTO

`Daika7ana\Bookurier\DTOs\Order\Order` maps to one entry in the `data` list of
`add_cmds.php`. Constructor arguments without a default are required in PHP;
`district` and a pickup point are also required by the service.

| Property | Wire name | Type | Default | Notes |
|---|---|---|---|---|
| `uniqueId` | `unq` | `string` | required | Your order id; vendor limit 20 characters |
| `recipientName` | `recv` | `string` | required | Vendor limit 80 characters |
| `phone` | `phone` | `string` | required | |
| `city` | `city` | `string` | required | |
| `street` | `street` | `string` | required | Vendor limit 300 characters |
| `service` | `service` | `ServiceType` | required | Backing int is sent |
| `weight` | `weight` | `float` | required | Parcel weight |
| `packages` | `packs` | `int` | `1` | |
| `pickupPoint` | `pickup_point` | `?string` | `null` | Falls back to `ClientConfig::pickupPoint` |
| `country` | `country` | `string` | `'Romania'` | |
| `zip` | `zip` | `?string` | `null` | |
| `district` | `district` | `?string` | `null` | Required in practice: county, or `Sector 1`..`Sector 6` for Bucharest |
| `email` | `email` | `?string` | `null` | |
| `number` | `no` | `?string` | `null` | Street number; omit it when the number is part of `street` |
| `block` | `bl` | `?string` | `null` | |
| `entrance` | `ent` | `?string` | `null` | |
| `floor` | `floor` | `?string` | `null` | |
| `apartment` | `apt` | `?string` | `null` | |
| `interphone` | `interphone` | `?string` | `null` | |
| `cashOnDelivery` | `rbs_val` | `?float` | `null` | Amount in RON |
| `insuredValue` | `insurance_val` | `?float` | `null` | Amount in RON |
| `returnDocument` | `ret_doc` | `bool` | `false` | |
| `weekend` | `weekend` | `bool` | `false` | |
| `unpack` | `unpack` | `bool` | `false` | |
| `exchangePackage` | `exchange_pack` | `bool` | `false` | |
| `confirmation` | `confirmation` | `bool` | `false` | |
| `notes` | `notes` | `?string` | `null` | Vendor limit 800 characters |
| `ref1` | `ref1` | `?string` | `null` | Free reference |
| `ref2` | `ref2` | `?string` | `null` | Free reference |

The SDK does not enforce the vendor character limits; it sends what you give it.

### Serialization rules

- **Null omission** — a `null` property is left out entirely, not sent empty.
- **Booleans** — `returnDocument`, `weekend`, `unpack`, `exchangePackage` and
  `confirmation` are always emitted, as `1` or `0`.
- **Zero values** — `0.0` cash-on-delivery and insured values are kept.
- **Pickup point** — the resource adds `pickup_point` to every order from
  `Order::$pickupPoint`, else `ClientConfig::pickupPoint`; a missing value throws
  `InvalidArgumentException` before sending.
- **Unique id** — `uniqueId` is your correlation id. It is not a deduplication
  key across separate calls: two orders with the same value both get AWBs.
  Within one batch the response map is keyed by `unq`, so duplicate non-empty
  values are rejected locally.

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
);

$payload = $order->toArray();
// ['unq' => 'A1', 'recv' => 'Ionescu Vasile', ..., 'service' => 1, ...]
```

## `ServiceType` enum

`Daika7ana\Bookurier\Enums\ServiceType` is an `int`-backed enum; the backing value
goes into `service`. `label()` returns the English description.

| Case | Value | `label()` |
|---|---|---|
| `Bucharest24h` | `1` | `Bucharest 24h` |
| `Metropolitan` | `3` | `Metropolitan` |
| `IlfovExtins` | `5` | `Ilfov Extins` |
| `NationalEconomic` | `8` | `National Economic` |
| `National24` | `9` | `National 24` |
| `NationalPremium` | `11` | `National Premium` |

The 1.x codes `2` (Bucharest express) and `7` (Bucharest Today) are not part of
this enum.

## `Status` enum

`Daika7ana\Bookurier\Enums\Status` maps the `status_id` values in
`awb_history.php`. `Status::isFinal()` is true for `NotPicked`, `Completed`,
`ReturnToSenderConfirmed`, `RefundPaid`, `Delivered` and `ReturnCompleted`; stop
polling an AWB once its latest event resolves to one of them.

| Id | Case | Id | Case |
|---|---|---|---|
| `-1` | `NotPicked` | `12` | `PickedUp` |
| `0` | `NewParcel` | `14` | `OutForDelivery` |
| `1` | `NewOrder` | `15` | `RedirectedByPartner` |
| `2` | `Received` | `16` | `Collected` |
| `3` | `Assigned` | `17` | `Compensation` |
| `4` | `Completed` | `18` | `Delivered` |
| `5` | `CashOnDeliveryHandover` | `20` | `InTransit` |
| `6` | `ReturnedToWarehouse` | `22` | `ReturnWarehouse` |
| `7` | `ReturnedToSender` | `30` | `DeliveryLine` |
| `8` | `Delayed` | `33` | `DeliveryFailed` |
| `9` | `ReturnToSenderConfirmed` | `70` | `ReturnLine` |
| `10` | `RefundPaid` | `77` | `ReturnFailed` |
| `11` | `Redirected` | `99` | `ReturnCompleted` |

An empty or non-numeric `status_id`, and an unknown numeric id, do not resolve
to a case.

## Response DTOs

| DTO | Members | Source |
|---|---|---|
| `OrderReference` | `string $uniqueId`, `string $awb` | Built by `createBatch()` from the response map |
| `BatchResult` | `references(): Collection<OrderReference>`, `errors(): Collection<OrderError>`, `hasErrors(): bool` | `add_cmds.php` |
| `OrderError` | `string $order`, `string $message`, `?string $suggestion` | One entry in `errors` |
| `HistoryEvent` | `string $date`, `?string $statusId`, `string $statusName`, `string $obs`, `string $sortDate`; `status(): ?Status` | One entry in `awb_history.php` `data` |
| `PartnerCity` | `string $city`, `string $district`, `string $zip`, `string $zone`, `string $sort`, `string $driver`, `int $kg` | One entry in `get_partner_cities.php` `data` |

`HistoryEvent::$date` is the vendor's `data` value, formatted `dd.mm.yyyy`.
`$sortDate` is the vendor's `sort_date`, formatted `Y-m-d H:i:s`. An empty
`status_id` becomes `null`; this is partner-courier free text, and
`status()` returns `null` for it. Non-numeric and unknown numeric ids also
return `null`.

`OrderError::$suggestion` is `null` when the service sends none.

## `Document`

Labels and the bordero are returned as `Daika7ana\Bookurier\Support\Document`;
the SDK does not parse them.

| Member | Type | Meaning |
|---|---|---|
| `$contents` | `string` | Raw response body, byte for byte |
| `$mimeType` | `string` | `Content-Type`, defaulting to `application/octet-stream` when the header is absent |
| `isPdf()` | `bool` | `true` when `$contents` starts with `%PDF` after leading whitespace |

`isPdf()` sniffs the body, not the header. `labels()->pdf()` rejects a body that
does not start with `%PDF`; `labels()->html()` and `reports()->borderou()` return
the body as-is.

## Collections

`createBatch()->references()` and `->errors()`, `history()` and
`partnerCities()` return `Daika7ana\SimpleCollections\Collection` instances,
iterable, countable and array-accessible. The `Order` payload itself is a plain
array from `toArray()`.

## Errors

`Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError` describes a JSON
`status: "error"` envelope. See [Errors](ERRORS.md#bookuriererror).
