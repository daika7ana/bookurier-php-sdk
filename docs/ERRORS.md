# Errors

Every Bookurier-specific exception extends
`Daika7ana\Bookurier\Exceptions\BookurierException`, which extends `Exception`.
Argument errors throw `InvalidArgumentException` instead: a missing pickup point,
an empty `district`, an empty batch, duplicate non-empty `uniqueId` values, or a
timeout that a foreign PSR-18 client cannot honour.

```text
Exception
└── BookurierException
    ├── AuthenticationException
    ├── TransportException          (implements ClientExceptionInterface)
    ├── UnexpectedStatusException
    └── ValidationException
```

| Exception | Raised when |
|-----------|-------------|
| `AuthenticationException` | `userId` or `password` is empty (before sending), a JSON error message contains `Invalid user` or `Login fail`, or HTTP 401 |
| `ValidationException` | Any other JSON `status: "error"` message, or HTTP 400/422 |
| `UnexpectedStatusException` | A non-2xx status not mapped above (429 included), an empty body, a non-JSON body where JSON is expected, a challenge page, a JSON success where a document is expected, or a missing AWB |
| `TransportException` | The HTTP transport fails: DNS, TLS, timeout, connection reset |
| `InvalidArgumentException` | Local argument or configuration error; nothing is sent |

## JSON error envelope

Bookurier reports most failures with HTTP 200 and a JSON body:

```json
{"status": "error", "message": "Invalid user"}
```

`ResponseBody::json()` checks the HTTP status first, then the body:

```text
non-2xx                                      -> status table below
2xx, empty body                              -> UnexpectedStatusException
2xx, non-JSON body                           -> UnexpectedStatusException
2xx, {"status":"error","message":...}        -> AuthenticationException
                                                when the message contains
                                                "invalid user" or "login fail"
                                             -> ValidationException otherwise
2xx, any other JSON object                   -> success payload
```

Messages are matched case-insensitively by substring. There is no negative-code
error model in this API.

## Status mapping

| HTTP status | Exception |
|---|---|
| `401` | `AuthenticationException` |
| `400`, `422` | `ValidationException` |
| `429` | `UnexpectedStatusException`, with `$retryAfter` from `Retry-After` |
| any other non-2xx | `UnexpectedStatusException` |

The status wins over the body: a `400` with a JSON error envelope is a
`ValidationException`, not an `AuthenticationException`.

`$e->getCode()` is the HTTP status when a response exists, else `0`. The
non-2xx and envelope exceptions expose `?ResponseInterface $response`. The
body-level exceptions also expose `?BookurierError $error` and `?int $retryAfter`.

## Document endpoints

`labels()->pdf()`, `labels()->html()` and `reports()->borderou()` return raw
documents. They still classify JSON error envelopes:

- a JSON `status: "error"` body raises `AuthenticationException` or
  `ValidationException` as above;
- an empty body, a JSON success body, or the host's HTML challenge page raises
  `UnexpectedStatusException`;
- `labels()->pdf()` additionally requires a body starting with `%PDF` (leading
  whitespace allowed);
- any other body is returned as a `Document`.

`Document::isPdf()` sniffs the body. Check `$document->mimeType` and
`$document->isPdf()` when the content matters; a proxy or gateway page can still
arrive as a `Document` if it does not match a known marker.

## Rate limits

The vendor declares these limits, per minute:

| Endpoint | Requests per minute |
|---|---|
| `add_cmds.php` | 30 |
| `print_awbs.php` | 30 |
| `request_pickup.php` | 10 |
| `print_borderou.php` | 10 |
| `awb_history.php` | 120 |

The SDK does not throttle or retry. A `429` raises
`UnexpectedStatusException`; read `$e->retryAfter` for the server's suggested
delay. The vendor also recommends 60 s timeouts for `add_cmds.php` and 30 s for
the other endpoints.

## Bot filter

The Bookurier host runs a bot filter. A sustained burst of requests can return
HTTP 200 with an HTML page such as "One moment, please…" or "Your request is
being verified" instead of the API answer. Every endpoint surfaces that as
`UnexpectedStatusException`.

Wait a few minutes before retrying reads. For `create()` and `createBatch()`, the
request may already have reached Bookurier: check the daily bordero and your own
order data before retrying, because there is no by-unique-id lookup and a retry
can create a second AWB.

## BookurierError

`Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError` describes the JSON error:

| Property | Value |
|---|---|
| `int $code` | `0`: this API sends no numeric error code |
| `string $message` | The envelope `message`, or `Bookurier returned an error.` when empty |
| `string $id` | Empty |
| `array $raw` | `['message' => ...]` |
| `?string $context`, `?string $component` | `null` |

`ValidationException::$errors` is always an empty array: the API sends no
field-level error list. For a single `create()`, a per-order error in the
response `errors` list is raised as `ValidationException` with the message and
suggestion appended. For `createBatch()`, per-order errors are returned in
`BatchResult::errors()` instead of being thrown.

## Retrying safely

| Operation | Guidance |
|---|---|
| `history()`, labels, borderou, `partnerCities()` | Safe to retry: read-only |
| `create()` and `createBatch()` | Reconcile first: `uniqueId` is not a dedupe key, so a timeout or challenge page may already have created an AWB. Check the daily bordero and your own data before resending |
| `pickup()->request()` | Never auto-retry: it dispatches a physical courier |

A retry after `TransportException` is always a judgement call: the request may
have reached Bookurier even though the response did not.

## Cancellation

There is no delete or cancellation endpoint in this API. An AWB that never enters
the courier network is not billed. To abandon a shipment, do not hand the parcel
to the courier and mark the AWB ignored in your own system.

## AuthenticationException

Thrown before the request is sent by `Client::postJson()` or `postForm()` when
the configuration has an empty `userId` or `password`:

```text
Bookurier credentials are missing. Pass them to ClientConfig or set BOOKURIER_USERNAME and BOOKURIER_PASSWORD.
```

It is also thrown when the service answers `Invalid user` or `Login fail`, or
when the HTTP status is `401`:

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;

try {
    $client->cities()->partnerCities();
} catch (AuthenticationException $e) {
    // Missing credentials, or the portal rejected the login.
}
```

`Client::get()` and `Client::send()` do not add credentials. Replace credentials
at runtime with `$client->setCredentials('new-user', 'new-password')`.

## ValidationException

Raised for a JSON `status: "error"` message other than the two authentication
messages, and for HTTP `400`/`422`:

```php
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $awb = $client->orders()->create($order);
} catch (ValidationException $e) {
    $e->getMessage();   // the API message, with the suggestion when present
    $e->error?->message;
    $e->errors;         // always []
}
```

A single `create()` raises this for a per-order error. A batch does not: inspect
`BatchResult::errors()`.

## UnexpectedStatusException

Raised when the response is not what the endpoint documents:

- empty JSON or document body;
- a non-JSON body where JSON is expected;
- a JSON success body where a document is expected;
- the host's HTML challenge page;
- a non-`%PDF` body from `labels()->pdf()`;
- `orders()->create()` returning neither a map entry nor a first `data` AWB;
- HTTP `429` or any other unmapped non-2xx status.

When the exception carries an offending body, the message includes up to its
first 200 characters.

## TransportException

Wraps a failure from the HTTP layer. The original exception is available through
`getPrevious()`:

```php
use Daika7ana\Bookurier\Exceptions\TransportException;

try {
    $awb = $client->orders()->create($order);
} catch (TransportException $e) {
    $e->getMessage();
    $e->getPrevious(); // the Guzzle or PSR-18 exception, when one exists
}
```

`TransportException` implements `Psr\Http\Client\ClientExceptionInterface`, so
code that already catches PSR-18 client failures can catch it too. Exceptions
from a foreign PSR-18 client are wrapped the same way.

An HTTP error status is not a `TransportException` with the bundled transport:
the response reaches the body classifier because Guzzle's `http_errors` is
disabled. A custom PSR-18 client that throws on 4xx/5xx is wrapped in
`TransportException` instead.

## Catch all SDK failures

Catch specific types first when the reaction differs, then the base class as a
fallback:

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // Missing credentials, or the service rejected the login.
} catch (ValidationException $e) {
    // The service rejected the order data.
} catch (TransportException $e) {
    // Network failure; retrying may help.
} catch (UnexpectedStatusException $e) {
    // Unexpected body, challenge page, rate limit or missing AWB.
} catch (BookurierException $e) {
    // Any SDK exception not matched above.
}
```
