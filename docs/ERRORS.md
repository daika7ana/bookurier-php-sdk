# Errors

Every Bookurier-specific exception extends
`Daika7ana\Bookurier\Exceptions\BookurierException`, which extends
`Exception`. Argument errors, such as an empty batch, duplicate non-empty
`uniqueId` values, a missing client code, or a timeout on a foreign client, throw
`InvalidArgumentException` instead.

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
| `AuthenticationException` | `userId` or `password` is empty (thrown by `Client::post()` before sending), or Bookurier answers `-1` |
| `ValidationException` | Bookurier rejects the request data in-band: `-2`, `-3`, `-5`, or a non-empty `<err>` |
| `UnexpectedStatusException` | The body is empty, is not the documented success value, is not parseable XML where XML was expected, is the host's HTML challenge page, or an AWB is malformed |
| `TransportException` | The HTTP transport fails: DNS, TLS, timeout, connection reset |

## HTTP 200 Does Not Mean Success

Bookurier returns HTTP 200 even for errors. The bundled `GuzzleHttpClient` sends
`http_errors => false` and `allow_redirects => false`. Classification is
first-match: an in-band signal wins over the status; otherwise a non-2xx status
maps to the standard table; otherwise the 2xx shape rules apply. Body rules:

```text
trim(body) matches /^-\d+$/            -> AuthenticationException (-1)
                                           ValidationException (-2, -3, -5)
                                           UnexpectedStatusException (any other code)
XML body with non-empty <err>          -> ValidationException
scalar body expected to be "1"         -> UnexpectedStatusException otherwise
XML body expected                      -> UnexpectedStatusException when empty,
                                          non-XML, or missing <cmd>/<cmds>
document endpoint, any other body      -> Document
document endpoint, challenge page      -> UnexpectedStatusException
```

## Status Mapping

Precedence, highest first:

1. In-band signal: a bare negative code or a non-empty `<err>` picks the
   exception regardless of the HTTP status.
2. Status table, when the body carries no in-band signal and the status is
   non-2xx: `401` → `AuthenticationException`, `400`/`422`
   → `ValidationException`, any other non-2xx → `UnexpectedStatusException`.
3. 2xx shape rules: challenge page, empty or unparseable body, or a body that
   does not match the endpoint's expected shape → `UnexpectedStatusException`.

`$e->getCode()` is the HTTP status when `$e->response` is present, else `0`, and
the in-band code lives only on `$e->error->code`. An in-band error always arrives
with a response, so its `getCode()` is `200`. A non-2xx status whose body
carries an in-band signal still raises the in-band exception (with the status in
`getCode()`); the table applies only when the body carries no in-band signal.

`UnexpectedStatusException` is also used when `orders()->create()` receives a
body that does not match the SDK's lenient AWB validator
(`/^[A-Za-z]{0,4}\d{3,}$/`). AWB formats vary server-side by destination county
and service: `BOK` plus seven digits (`BOK3925457`), twelve digits
(`062568455929`), or `CKC` plus six digits (`CKC122336`); all are opaque strings
to the SDK.

Document endpoints reject a body matching `/^-\d+$/` and the host's HTML
challenge page. Any other body is returned as a `Document`, so a proxy or gateway
error page can still arrive as one; check `$document->mimeType` and
`$document->isPdf()` when the content matters. `pdf()` and `pdfByUniqueId()`
additionally require a body beginning with `%PDF`.

Bookurier's host also runs a bot filter. After a burst of requests it answers
HTTP 200 with an HTML "One moment, please… / your request is being verified"
challenge page instead of the API body. Every endpoint, including the document
endpoints, surfaces that as `UnexpectedStatusException`. Wait a few minutes and
retry reads. For `create()` and `createBatch()`, the caller cannot tell whether
the request reached Bookurier, so check the daily label list or the order status
before retrying.

## Error Codes

Bookurier reports failures in-band with HTTP 200. Most are a bare negative
number; the XML endpoints can also return a non-empty `<err>`.

| Body | Exception | Meaning (observed on the live API) |
|------|-----------|------------------------------------|
| `-1` | `AuthenticationException` | Request rejected: bad credentials or a malformed/absent `msg` envelope |
| `-2` | `ValidationException` | Record not found or invalid data: an unknown AWB, or `get_history.php` by AWB |
| `-3` | `ValidationException` | Incomplete request; observed on a label request without its `msg` envelope |
| `-5` | `ValidationException` | Order rejected by the service; observed when `<district>` is missing or empty |
| other negative | `UnexpectedStatusException` | Generic rejection not covered above |
| `<err>` text | `ValidationException` | The message is the trimmed `<err>` text |

`-1` is the code the service also answers for bad credentials, so the SDK treats
it as an authentication failure; if the credentials are correct, inspect the
`msg` XML. The documentation specifies only `1` = success and `-1` = error
(`1=succes; -1=eroare`). The finer readings above come from probing the live
service.

All three body-level exceptions (`AuthenticationException`,
`ValidationException`, `UnexpectedStatusException`) expose:

- `?ResponseInterface $response` — the response that produced the failure, when
  one exists;
- `?int $retryAfter` — parsed from the `Retry-After` header, when present;
- `?BookurierError $error` — the in-band error, when the failure carried one.

## Retrying Safely

| Operation | Guidance |
|---|---|
| `status()`, `statusByUniqueId()`, `history()`, `historyByUniqueId()` | Safe to retry: read-only |
| `labels()` reads and `reports()->dailyBordero()` | Safe to retry: read-only |
| `create()` and `createBatch()` | Reconcile first: `unq` is not a dedupe key, so a timeout may already have created a shipment. Check the daily label list or status before resending |
| `pickup()->request()` | Never auto-retry: it dispatches a physical courier |
| `delete()` / `deleteByUniqueId()` | `-2` is an error, not a successful delete; confirm the order state |

A retry after `TransportException` is always a judgement call: the request may
have reached Bookurier even though the response did not.

## AuthenticationException

Thrown before the request is sent by `Client::post()` when the configuration has
an empty `userId` or `password`:

```text
Bookurier credentials are missing. Pass them to ClientConfig or set BOOKURIER_USERNAME and BOOKURIER_PASSWORD.
```

It is also thrown when the service answers `-1`, with the response and the
`BookurierError` attached:

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;

try {
    $client->orders()->status('BOK0000000');
} catch (AuthenticationException $e) {
    if ($e->response !== null) {
        // The service rejected the request in-band; check the credentials first.
    }
    // Otherwise a credential is missing and nothing was sent.
}
```

This check lives in `Client::post()`. `Client::send()` sends a request you built
yourself and does not add credentials.

## ValidationException

`ValidationException` represents an error reported by Bookurier in the body:

- a bare negative code `-2`, `-3` or `-5`, with the message naming the code;
- a non-empty `<err>` element on the XML-mapped endpoints (`status`,
  `statusByUniqueId`, `history`, `historyByUniqueId`, `createBatch`). The
  exception message is the trimmed `<err>` text. On `createBatch`, a response
  with no `<cmds>` element raises `UnexpectedStatusException` instead.

Bookurier has no field-level error payload, so `$errors` is always an empty
array. The in-band code and message are on `$e->error`; `$e->getCode()` is the
HTTP status (see [Status Mapping](#status-mapping)), not the negative code:

```php
use Daika7ana\Bookurier\Exceptions\ValidationException;

try {
    $client->orders()->status('BOK0000000');
} catch (ValidationException $e) {
    $e->error?->code;    // -2
    $e->error?->message; // the API message
}
```

`create()` and `createBatch()` reject a missing client code locally with
`InvalidArgumentException` before sending, so the SDK does not rely on the API's
`-2` answer for that case.

## UnexpectedStatusException

Raised when the body does not match what the endpoint documents:

- `orders()->create()` — the body is not a negative code and does not match
  `/^[A-Za-z]{0,4}\d{3,}$/`.
- `orders()->createBatch()` — the body has no `<cmds>` element, the AWB count
  differs from the order count, or an AWB is malformed.
- `orders()->delete()` / `deleteByUniqueId()` — the body is not `1`.
- XML endpoints — the body is empty, is not valid XML, or has no `<cmd>` element
  (or no `<cmd>` elements for the history endpoints).
- Scalar endpoints — the body is empty.
- Document endpoints — the body is empty or is the host's HTML challenge page;
  the PDF methods also reject a body that does not start with `%PDF`.

These shape errors have no parsed payload to attach, so they carry no `$error`.

When the exception carries the offending body, the message includes up to its
first 200 characters:

```text
Expected an AWB number. Got: "<body>".
Expected an XML response. Got: "<body>".
Expected "1" for success. Got: "<body>".
Response XML has no <cmd> element. Got: "<body>".
Response XML has no <cmd> elements. Got: "<body>".
```

## TransportException

Wraps a failure from the HTTP layer. The original exception is available through
`getPrevious()`:

```php
use Daika7ana\Bookurier\Exceptions\TransportException;

try {
    $client->orders()->create($order);
} catch (TransportException $e) {
    $e->getMessage();
    $e->getPrevious(); // the Guzzle or PSR-18 exception, when one exists
}
```

`TransportException` implements `Psr\Http\Client\ClientExceptionInterface`, so
code that already catches PSR-18 client failures can catch it too. Exceptions
from a foreign PSR-18 client are wrapped the same way.

An HTTP error status is not a `TransportException` with the bundled transport: the
response reaches the body classifier because `http_errors` is disabled. A custom
PSR-18 client that throws on 4xx/5xx is wrapped in `TransportException` instead.

## BookurierError

The in-band error is shaped by
`Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError`:

| Property | Source |
|---|---|
| `int $code` | The negative body code, or `0` for an `<err>` |
| `string $message` | The SDK message for a code, or the trimmed `<err>` text |
| `string $id` | Empty: Bookurier sends no error id |
| `array $raw` | The parsed error data |
| `?string $context`, `?string $component` | Carrier-defined: passed through when the in-band payload supplies them; Bookurier sends neither, so they are `null` |

`isAuthenticationRelated()` is true for `-1` and for messages that mention
credentials; `isValidationRelated()` is true for `-2`, `-3`, `-5` and for
messages that look like data errors. Use them when you handle a
`UnexpectedStatusException` generically.

## Catch All SDK Failures

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
    // Missing credentials, or the service rejected the request with -1.
    throw $e;
} catch (ValidationException $e) {
    // Bookurier rejected the data with a negative code or a non-empty <err>.
    throw $e;
} catch (TransportException $e) {
    // Network failure; retrying may help.
    throw $e;
} catch (UnexpectedStatusException $e) {
    // The endpoint returned something the SDK does not understand.
    throw $e;
} catch (BookurierException $e) {
    // Any SDK exception not matched above.
    throw $e;
}
```
