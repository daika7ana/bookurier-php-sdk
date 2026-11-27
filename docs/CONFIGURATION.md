# Configuration

`Daika7ana\Bookurier\Config\ClientConfig` is a `final readonly` value object with
public properties. The `with*()` methods return modified copies.

| Option | Default | Purpose |
|--------|---------|---------|
| `userId` | `''` | Portal username, sent as `user` |
| `password` | `''` | Portal password, sent as `pwd` |
| `baseUrl` | `https://portal.bookurier.ro/api` | API base URL |
| `timeout` | `null` | Request timeout in seconds; `null` uses the transport default |
| `pickupPoint` | `null` | Default pickup-point number for orders, pickup and borderou calls |
| `trackingKey` | `null` | `awb_history.php` key; `null` uses `ClientConfig::DEFAULT_TRACKING_KEY` |

## Construct a client

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

`Client::create()` with no `config` argument calls `ClientConfig::fromEnvironment()`.
Constructing `new ClientConfig()` yourself does not read the environment.

## Environment variables

| Variable | Maps to | Behavior when unset or empty |
|----------|---------|------------------------------|
| `BOOKURIER_USERNAME` | `userId` | Empty string; account requests throw `AuthenticationException` |
| `BOOKURIER_PASSWORD` | `password` | Empty string; account requests throw `AuthenticationException` |
| `BOOKURIER_PICKUP_POINT` | `pickupPoint` | `null`; orders need `Order::$pickupPoint` instead |
| `BOOKURIER_TRACKING_KEY` | `trackingKey` | Falls back to `ClientConfig::DEFAULT_TRACKING_KEY` |
| `BOOKURIER_BASE_URL` | `baseUrl` | Falls back to `ClientConfig::BASE_URL` |
| `BOOKURIER_TIMEOUT` | `timeout` | `null`, so the transport default applies |

A non-numeric `BOOKURIER_TIMEOUT` throws `InvalidArgumentException` from
`fromEnvironment()`. Unset and empty timeout and pickup-point values are treated
as `null`; an empty tracking key falls back to the default key.

```bash
export BOOKURIER_USERNAME=your-portal-user
export BOOKURIER_PASSWORD=your-portal-password
export BOOKURIER_PICKUP_POINT=5
```

```php
$client = Client::create(); // reads the variables above
```

## Credentials and pickup point

Bookurier authenticates with the portal account username and password, not an API
key. `Client::postJson()` and `Client::postForm()` add them to every account
request:

```json
{"user": "your-portal-user", "pwd": "your-portal-password", "data": []}
```

If either value is empty, those methods throw
`Daika7ana\Bookurier\Exceptions\AuthenticationException` before sending. This check
lives in `postJson()` / `postForm()`, not in the HTTP transport: a request passed
directly to `Client::send()` does not gain credentials. `Client::get()` also sends
none, because `awb_history.php` is public.

Replace credentials at runtime with `setCredentials()`, which updates the client's
config and returns the same client:

```php
$client->setCredentials('new-user', 'new-password');
```

`ClientConfig::withCredentials()` returns a new value object instead:

```php
$config = $client->getConfig()->withCredentials('new-user', 'new-password');
```

Order creation needs a pickup point. Set a default once on the config:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
));
```

or per order with `Order::$pickupPoint`. A per-order value wins over the config.
`ClientConfig::withPickupPoint()` returns a new config value; pass it to a new
client when you need to derive one:

```php
$config = $client->getConfig()->withPickupPoint('5');
$other = Client::create(config: $config);
```

`withTrackingKey()` does the same for the history key.

The password is sent in a plain field over HTTPS. Redact `user`, `pwd` and full
request bodies from logs.

## Base URL

The default is `https://portal.bookurier.ro/api` (`ClientConfig::BASE_URL`). The
client joins the base URL and an endpoint by trimming the trailing and leading
slashes, so the default produces
`https://portal.bookurier.ro/api/add_cmds.php`.

Override it for a proxy or a test gateway:

```php
$config = new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    baseUrl: 'https://bookurier-proxy.internal/api',
);
```

## Timeouts

`ClientConfig::timeout` is `?float` and defaults to `null`, so the bundled
`GuzzleHttpClient` applies its own 30 s request / 10 s connect defaults.

Precedence, highest first:

1. per-call argument on `Client::postJson()` / `postForm()` / `get()` / `send()`,
   or `reports()->borderou(..., timeout:)`;
2. `ClientConfig::timeout`;
3. `GuzzleHttpClient` constructor arguments
   (`new GuzzleHttpClient(timeout: 10.0, connectTimeout: 5.0)`);
4. the transport's 30 s / 10 s defaults.

`orders()`, `labels()`, `cities()`, `pickup()` and `history()` do not take a
timeout argument. Set a default on `ClientConfig` or derive a client:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    timeout: 30.0,
));

$slow = $client->withTimeout(60.0); // different budget, same transport
```

`withTimeout()` returns a new client sharing the transport, PSR-17 factories,
credentials, pickup point and tracking key; the original keeps its timeout.
`withTimeout(null)` returns to the transport default.

The vendor recommends 60 s for `add_cmds.php` (create and batch) and 30 s for the
other endpoints. For example:

```php
$client = Client::create(config: new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    timeout: 30.0,
));

$orders = $client->withTimeout(60.0);
$awb = $orders->orders()->create($order);
```

## Custom HTTP client

Any PSR-18 client can be passed to `Client::create()`. A client that does not
implement `Daika7ana\Bookurier\Http\HttpClientInterface` is wrapped in
`Psr18HttpClientAdapter`, which keeps that client's own timeout:

```php
use Daika7ana\Bookurier\Client;
use GuzzleHttp\Client as GuzzleClient;

$client = Client::create(
    httpClient: new GuzzleClient(['timeout' => 10.0]),
);
```

Because the adapter cannot forward a timeout, two combinations throw
`InvalidArgumentException`:

- a non-null `ClientConfig::timeout` together with a foreign PSR-18 client, thrown
  by `Client::__construct()`;
- a non-null per-call timeout reaching `Psr18HttpClientAdapter`, thrown by
  `sendRequest()`.

Leave `ClientConfig::timeout` at `null` and do not pass a per-call timeout when
using a foreign client. Because `Client::create()` falls back to
`fromEnvironment()`, a set `BOOKURIER_TIMEOUT` also counts as a non-null config
timeout in that check.

Implement `HttpClientInterface` if you need the SDK's per-call timeout:

```php
use Daika7ana\Bookurier\Http\HttpClientInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MyTransport implements HttpClientInterface
{
    public function __construct(private ClientInterface $client) {}

    public function sendRequest(RequestInterface $request, ?float $timeout = null): ResponseInterface
    {
        return $this->client->sendRequest($request); // forward $timeout when supported
    }
}
```

`Client::create()` also accepts `requestFactory:` and `streamFactory:` arguments
for custom PSR-17 factories. If either is omitted, the bundled
`GuzzleHttp\Psr7\HttpFactory` is used for both.
