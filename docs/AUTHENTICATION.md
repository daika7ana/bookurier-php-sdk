# Authentication

Bookurier authenticates with the username and password of a Bookurier account.
Both are sent as `userid` and `pwd` form fields on every POST. There is no API
key, no OAuth exchange, no bearer token and no refresh flow.

```text
userid=your-bookurier-user&pwd=your-bookurier-password&msg=<msg><cmd>...</cmd></msg>
```

## Providing credentials

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    clientId: 1234, // your Bookurier client code
));
```

Or through the environment. `Client::create()` with no `config` argument calls
`ClientConfig::fromEnvironment()`:

```bash
export BOOKURIER_USERNAME=your-bookurier-user
export BOOKURIER_PASSWORD=your-bookurier-password
export BOOKURIER_CLIENT_ID=1234
```

With those set, `Client::create()` needs no arguments.

| Variable | Maps to | Notes |
|---|---|---|
| `BOOKURIER_USERNAME` | `ClientConfig::$userId` | Preferred variable |
| `BOOKURIER_USERID` | `ClientConfig::$userId` | Fallback read only when `BOOKURIER_USERNAME` is unset or empty |
| `BOOKURIER_PASSWORD` | `ClientConfig::$password` | Required for any request |
| `BOOKURIER_CLIENT_ID` | `ClientConfig::$clientId` | Cast to `int`; fills the `<client>` element |

A non-integer `BOOKURIER_CLIENT_ID` throws `InvalidArgumentException`. For
`BOOKURIER_BASE_URL`, `BOOKURIER_TIMEOUT` and the full default table, see
[Configuration](CONFIGURATION.md#environment-variables).

A `ClientConfig` you construct yourself does not read the environment.

## The client code

Order XML requires a `<client>` element carrying your Bookurier client code.
`Order::$clientCode` is used when it is set; otherwise the SDK falls back to
`ClientConfig::clientId`. With neither set, `orders()->create()` and
`orders()->createBatch()` throw `InvalidArgumentException` before sending.

`Order::toXml()` resolves the value as `clientCode ?? $defaultClientCode`, and the
resources pass `ClientConfig::clientId` as that default. Pass `clientId` once on
the config, or set `clientCode` per order when one integration submits for
several Bookurier accounts.

## Missing credentials

`Client::post()` throws
`Daika7ana\Bookurier\Exceptions\AuthenticationException` before sending when
either value is empty:

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;

try {
    $client->orders()->status('BOK3925457');
} catch (AuthenticationException $e) {
    // userId or password is empty; nothing was sent.
}
```

The check lives in `post()`; `Client::send()` transmits a request you built
yourself and adds no credentials. Replace credentials at runtime with
`$client->setCredentials('new-user', 'new-password')`, which updates the client in
place.

## Rejected requests

Bookurier answers HTTP 200 and puts a negative code in the body. The SDK raises
`AuthenticationException` for `-1` and `ValidationException` for `-2`, `-3` and
`-5`, naming the code. `-1` is generic: it does not distinguish a wrong password
from a bad payload. Check the credentials first, then the XML body. The per-code
table lives in [Error Codes](ERRORS.md#error-codes).

## Redaction

The password travels as a plain form field, protected only by HTTPS. Redact
`userid`, `pwd` and full request bodies from logs, exception trackers and HTTP
dumps. The SDK does not log requests itself, but a PSR-18 middleware or Guzzle
handler you add can.
