# Errors

Every Bookurier-specific exception extends
`Daika7ana\Bookurier\Exceptions\BookurierException`, which extends
`RuntimeException`. Argument errors, such as an empty batch, duplicate non-empty
`uniqueId` values, a missing client code, or a timeout on a foreign client, throw
`InvalidArgumentException` instead.

```text
RuntimeException
└── BookurierException
    ├── AuthenticationException      (final)
    ├── ApiException                 (final)
    ├── TransportException           (implements ClientExceptionInterface)
    └── UnexpectedResponseException  (final)
```

| Exception | Raised when |
|-----------|-------------|
| `AuthenticationException` | `userId` or `password` is empty; thrown by `Client::post()` before sending |
| `ApiException` | Bookurier answers a negative code (`-1`, `-2`, `-3`, …), or an XML endpoint answers with a non-empty `<err>` |
| `TransportException` | The HTTP transport fails: DNS, TLS, timeout, connection reset |
| `UnexpectedResponseException` | The body is empty, is not the documented success value, is not parseable XML where XML was expected, or is the host's HTML challenge page |

## HTTP 200 Does Not Mean Success

Bookurier returns HTTP 200 even for errors. The bundled `GuzzleHttpClient` sends
`http_errors => false` and `allow_redirects => false`, and the SDK never inspects
the status code. A response is classified by its body:

```text
trim(body) matches /^-\d+$/            -> ApiException (the code is in the message)
XML body with non-empty <err>          -> ApiException (XML endpoints)
scalar body expected to be "1"         -> UnexpectedResponseException otherwise
XML body expected                      -> UnexpectedResponseException when empty,
                                          non-XML, or missing <cmd>/<cmds>
document endpoint, any other body      -> Document
document endpoint, challenge page      -> UnexpectedResponseException
```

`UnexpectedResponseException` is also used when `orders()->create()` receives a
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
endpoints, surfaces that as `UnexpectedResponseException`. Wait a few minutes and
retry reads. For `create()` and `createBatch()`, the caller cannot tell whether
the request reached Bookurier, so check the daily label list or the order status
before retrying.

## Error Codes

Bookurier reports failures in-band with HTTP 200. Most are a bare negative
number; the XML endpoints can also return a non-empty `<err>`. The SDK turns
each negative code into an `ApiException` whose message names the code:

| Body | Meaning (observed on the live API) |
|------|------------------------------------|
| `-1` | Request rejected: bad credentials or a malformed/absent `msg` envelope |
| `-2` | Record not found or invalid data: an unknown AWB, or `get_history.php` by AWB |
| `-3` | Incomplete request; observed on a label request without its `msg` envelope |
| `-5` | Order rejected by the service; observed when `<district>` is missing or empty |

The documentation specifies only `1` = success and `-1` = error
(`1=succes; -1=eroare`). The finer readings above come from probing the live
service. Treat any other negative value as a generic rejection.

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

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;

try {
    $client->orders()->status('BOK0000000');
} catch (AuthenticationException $e) {
    // Fix BOOKURIER_USERNAME / BOOKURIER_PASSWORD or ClientConfig.
}
```

This check lives in `Client::post()`. `Client::send()` sends a request you built
yourself and does not add credentials.

## ApiException

`ApiException` represents an error reported by Bookurier in the body:

- A trimmed scalar or document body matching `/^-\d+$/`. The message names the
  code:
  - `-1` — `Bookurier returned -1: the request was rejected (bad credentials or malformed msg).`
  - `-2` — `Bookurier returned -2: the record was not found, or the request data is invalid.`
  - `-3` — `Bookurier returned -3: the request was incomplete (a label request without its msg envelope).`
  - `-5` — `Bookurier returned -5: the service rejected the order (observed when <district> is missing or empty).`
  - any other code — the message echoes it, e.g.
    `Bookurier returned -4: the request was rejected.`
- A non-empty `<err>` element on the XML-mapped endpoints (`status`,
  `statusByUniqueId`, `history`, `historyByUniqueId`, `createBatch`). The
  exception message is the trimmed `<err>` text. On `createBatch`, a response
  with no `<cmds>` element raises `UnexpectedResponseException` instead.

`create()` and `createBatch()` reject a missing client code locally with
`InvalidArgumentException` before sending, so the SDK does not rely on the API's
`-2` answer for that case.

See [Error Codes](#error-codes) for what each code means.

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

## UnexpectedResponseException

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

When the exception carries the offending body, the message includes up to its
first 200 characters:

```text
Expected an AWB number. Got: "<body>".
Expected an XML response. Got: "<body>".
Expected "1" for success. Got: "<body>".
Response XML has no <cmd> element. Got: "<body>".
Response XML has no <cmd> elements. Got: "<body>".
```

## Catch All SDK Failures

Catch specific types first when the reaction differs, then the base class as a
fallback:

```php
use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;

try {
    $awb = $client->orders()->create($order);
} catch (AuthenticationException $e) {
    // Configuration error; nothing was sent.
    throw $e;
} catch (ApiException $e) {
    // Bookurier answered a negative code or a non-empty <err>.
    throw $e;
} catch (TransportException $e) {
    // Network failure; retrying may help.
    throw $e;
} catch (UnexpectedResponseException $e) {
    // The endpoint returned something the SDK does not understand.
    throw $e;
} catch (BookurierException $e) {
    // Any SDK exception not matched above.
    throw $e;
}
```
