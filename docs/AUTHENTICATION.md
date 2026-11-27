# Authentication

Bookurier's October 2026 portal API authenticates with the username and password
of a portal account. There is no API key, OAuth exchange, bearer token or refresh
flow.

## Where credentials go

| Endpoint | Transport | Credentials |
|---|---|---|
| `add_cmds.php` | JSON POST | `user` and `pwd` in the JSON body |
| `print_awbs.php` | JSON POST | `user` and `pwd` in the JSON body |
| `get_partner_cities.php` | JSON POST | `user` and `pwd` in the JSON body |
| `request_pickup.php` | form POST | `user` and `pwd` form fields |
| `print_borderou.php` | form POST | `user` and `pwd` form fields |
| `awb_history.php` | GET | none; only the shared `key` tracking key |

`Client::postJson()` and `Client::postForm()` add the credentials to every
request. `Client::get()` and `Client::send()` do not.

## Providing credentials

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

`userId` is the portal username; on the wire it is sent as `user`. The password
is sent as `pwd`.

Or through the environment:

```bash
export BOOKURIER_USERNAME=your-portal-user
export BOOKURIER_PASSWORD=your-portal-password
export BOOKURIER_PICKUP_POINT=5
```

```php
$client = Client::create(); // reads the variables above
```

`Client::create()` with no `config` argument calls `ClientConfig::fromEnvironment()`.
A `ClientConfig` you construct yourself does not read the environment.

## Pickup point

A portal account also has a pickup-point number. It is not an authentication
credential, but every order needs one: `Order::$pickupPoint` or
`ClientConfig::pickupPoint` (`BOOKURIER_PICKUP_POINT`). Without either,
`orders()->create()` and `orders()->createBatch()` throw
`InvalidArgumentException` before sending. `pickup()->request()` and
`reports()->borderou()` use the configured pickup point as their default too.

## Missing credentials

`Client::postJson()` and `Client::postForm()` throw
`Daika7ana\Bookurier\Exceptions\AuthenticationException` before sending when
either value is empty:

```php
use Daika7ana\Bookurier\Exceptions\AuthenticationException;

try {
    $client->cities()->partnerCities();
} catch (AuthenticationException $e) {
    // userId or password is empty; nothing was sent.
}
```

`Client::get()` does not check credentials, because `awb_history.php` is public.
`Client::send()` transmits a request you built yourself and adds no credentials.
Replace credentials at runtime with
`$client->setCredentials('new-user', 'new-password')`, which updates the client in
place.

## Rejected requests

Bookurier answers HTTP 200 with a JSON body such as:

```json
{"status": "error", "message": "Invalid user"}
```

The SDK raises `AuthenticationException` when the message contains
`Invalid user` or `Login fail` (case-insensitive), and `ValidationException` for
every other `status: "error"` message. HTTP 401 also raises
`AuthenticationException`.

There is no negative-code error model in this API. The JSON message is the only
error detail; the attached `BookurierError` carries the message and has
`code = 0`.

## Tracking key

`awb_history.php` needs no account credentials. It needs the shared tracking key,
sent as the `key` query parameter:

```text
GET https://portal.bookurier.ro/api/awb_history.php?key=...&awb=BOK6000123456
```

`ClientConfig::DEFAULT_TRACKING_KEY` supplies the vendor's default
(`7BzvpY3PuANhhMajURYWn`). Override it with `ClientConfig::$trackingKey` or
`BOOKURIER_TRACKING_KEY` when Bookurier issues you a different key. The key is
shared with the public history endpoint; treat it as configuration, not as a
per-account secret.

## Redaction

The password travels in a plain JSON or form field, protected only by HTTPS.
Redact `user`, `pwd`, the tracking key and full request bodies from logs,
exception trackers and HTTP dumps. The SDK does not log requests itself, but a
PSR-18 middleware or Guzzle handler you add can.
