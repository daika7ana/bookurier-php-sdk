# DTOs and Enums

Request objects are `final readonly` classes with public camelCase properties and
a `toXml()` method. Response objects are `final readonly` classes; `OrderStatus`
and `HistoryEntry` are built with a `fromArray(array $data): self` factory, while
`OrderReference` is constructed positionally by `createBatch()`. The XML tags keep
the vendor's names, so most of them are abbreviated or snake_case.

## `Order` request DTO

`Daika7ana\Bookurier\DTOs\Order\Order` maps to the `<cmd>` body of
`add_cmd.php` / `add_cmds.php`. Constructor arguments without a default are
required; everything else may be omitted.

| Property | XML tag | Type | Required |
|---|---|---|---|
| `uniqueId` | `unq` | `string` | Yes |
| `recipientName` | `recv` | `string` | Yes |
| `phone` | `phone` | `string` | Yes |
| `city` | `city` | `string` | Yes |
| `street` | `street` | `string` | Yes |
| `number` | `no` | `string` | Yes |
| `service` | `service` | `ServiceType` | Yes |
| `weight` | `weight` | `float` | Yes |
| `packages` | `packs` | `int` | No, default `1` |
| `country` | `country` | `string` | No, default `Romania` |
| `zip` | `zip` | `?string` | No |
| `district` | `district` | `?string` | Yes; sector (`Sect 1`) or county — the service answers `-5` without it |
| `email` | `email` | `?string` | No |
| `block` | `bl` | `?string` | No |
| `entrance` | `ent` | `?string` | No |
| `floor` | `floor` | `?string` | No |
| `apartment` | `apt` | `?string` | No |
| `interphone` | `interphone` | `?string` | No |
| `exchangePackage` | `exchange_pack` | `bool` | No, default `false` |
| `cashOnDelivery` | `rbs_val` | `?float` | No; amount in RON |
| `confirmation` | `confirmation` | `bool` | No, default `false` |
| `returnDocument` | `ret_doc` | `bool` | No, default `false` |
| `returnDocumentDescription` | `ret_doc_desc` | `?string` | No |
| `insuredValue` | `insurance_val` | `?float` | No; amount in RON |
| `notes` | `notes` | `?string` | No |
| `clientCode` | `client` | `?int` | No; defaults to `ClientConfig::clientId` |

`toXml(?int $defaultClientCode = null)` builds `<cmd>...</cmd>` and, when a code
exists, emits `<client>` first using `clientCode ?? $defaultClientCode`. The API
requires that element; `create()` / `createBatch()` throw
`InvalidArgumentException` when neither value is set.

### Serialization rules

- **Null omission** — a `null` property is left out entirely, not sent empty.
  Optional address parts therefore disappear when unset.
- **Booleans** — `exchangePackage`, `confirmation` and `returnDocument` are
  non-nullable and always emitted, as `1` or `0`. The documentation spells the
  tag `<exange_pack>`; the SDK emits `exchange_pack`.
- **Escaping** — every value is cast to string and escaped with
  `htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8')`, so `&`, `<`, `>`,
  `"` and `'` in an address or note cannot break the XML.
- **Envelope** — the resources wrap the result themselves:
  `msg=<msg><cmd>...</cmd></msg>`. A bare `<cmd>` without the `<msg>` wrapper is
  rejected with `-1`.

```php
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;

$order = new Order(
    uniqueId: 'A1',
    recipientName: 'Ionescu Vasile',
    phone: '0744-666847',
    city: 'Bucuresti',
    street: 'Nicolae Oncescu',
    number: '6',
    service: ServiceType::Bucharest24h,
    weight: 0.5,
    district: 'Sect 1',
    cashOnDelivery: 100.65,
);

echo $order->toXml(1234);
// <cmd><client>1234</client><unq>A1</unq><recv>Ionescu Vasile</recv>...
```

## `ServiceType` enum

`Daika7ana\Bookurier\Enums\ServiceType` is an `int`-backed enum; the backing value
is what goes into `<service>`. `label()` returns the English description.

| Case | Value | `label()` |
|---|---|---|
| `Bucharest24h` | `1` | `Bucharest 24h` |
| `BucharestExpress` | `2` | `Bucharest express` |
| `Metropolitan` | `3` | `Metropolitan` |
| `National24` | `9` | `National 24h` |

## Response DTOs

`OrderStatus::fromArray()` and `HistoryEntry::fromArray()` read the flattened
`<cmd>` element. Missing elements become `''` or `0`; an empty `obs` or `date`
becomes `null`.

| DTO | Property | Source element | Type |
|---|---|---|---|
| `OrderStatus` | `uniqueId` | `unq` | `string` |
| | `awb` | `awb` | `string` |
| | `statusId` | `status_id` | `int` |
| | `statusDescription` | `status_desc` | `string` |
| | `observations` | `obs` | `?string` |
| `HistoryEntry` | `uniqueId` | `unq` | `string` |
| | `awb` | `awb` | `string` |
| | `statusId` | `status_id` | `int` |
| | `statusDescription` | `status_desc` | `string` |
| | `date` | `date` | `?string`; raw `Y-m-d H:i:s`, no timezone |
| | `observations` | `obs` | `?string` |
| `OrderReference` | `uniqueId` | your `Order::$uniqueId` | `string` |
| | `awb` | AWB at the same index | `string` |

`OrderReference` is not parsed from the batch response: `add_cmds.php` sends no
`unq`, so `createBatch()` constructs each one positionally. See
[Resources](RESOURCES.md#createbatcharray-orders-collection) for the pairing and
error rules.

## `Document`

Labels, the daily bordero and the pickup bordero are returned as
`Daika7ana\Bookurier\Support\Document`, not parsed DTOs.

| Member | Type | Meaning |
|---|---|---|
| `$contents` | `string` | Raw response body, byte for byte |
| `$mimeType` | `string` | `Content-Type`, defaulting to `application/octet-stream` when the header is absent |
| `isPdf()` | `bool` | `true` when `$contents` starts with `%PDF` after leading whitespace |

`isPdf()` sniffs the body, not the header. The SDK rejects the host's challenge
page on every document endpoint and `pdf()` / `pdfByUniqueId()` require a `%PDF`
body; the check still helps when a different proxy page arrives with HTTP 200.

## Collections

`createBatch()` returns `Collection<int, OrderReference>`; `history($awb)` and
`historyByUniqueId($uniqueId)` return `Collection<int, HistoryEntry>`. All are
`Daika7ana\SimpleCollections\Collection` instances, iterable, countable and
array-accessible.

`history($awb)` answers `-2` even for a valid AWB on the live service; see the
canonical note in [Resources](RESOURCES.md#historystring-awb-collection).
`historyByUniqueId()` is the working path.
