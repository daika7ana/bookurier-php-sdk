# Configuration

`Daika7ana\Bookurier\Config\ClientConfig` is a `final readonly` value object with
public properties. The `with*()` methods return modified copies.

| Option | Default | Purpose |
|--------|---------|---------|
| `userId` | `''` | Bookurier account username, sent as `userid` |
| `password` | `''` | Bookurier account password, sent as `pwd` |
| `clientId` | `null` | Your Bookurier client code, sent as `<client>` in order XML; `create()` / `createBatch()` throw `InvalidArgumentException` when neither this nor `Order::$clientCode` is set |
| `baseUrl` | `https://www.bookurier.ro/colete/serv` | API base URL |
| `timeout` | `null` | Request timeout in seconds; `null` uses the transport default |

## Construct a Client

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    clientId: 1234, // your Bookurier client code
));
```

`Client::create()` with no `config` argument calls
`ClientConfig::fromEnvironment()`. Constructing `new ClientConfig()` yourself
does not read the environment.

## Environment Variables

| Variable | Maps to | Behavior when unset or empty |
|----------|---------|------------------------------|
| `BOOKURIER_USERNAME` | `userId` | Empty string; every resource call throws `AuthenticationException` |
| `BOOKURIER_USERID` | `userId` | Fallback for `BOOKURIER_USERNAME`, for existing deployments |
| `BOOKURIER_PASSWORD` | `password` | Empty string; every resource call throws `AuthenticationException` |
| `BOOKURIER_CLIENT_ID` | `clientId` | `null`; `create()` / `createBatch()` throw unless `Order::$clientCode` is set |
| `BOOKURIER_BASE_URL` | `baseUrl` | Falls back to `ClientConfig::BASE_URL` |
| `BOOKURIER_TIMEOUT` | `timeout` | `null`, so the transport default applies |

A non-numeric `BOOKURIER_TIMEOUT`, or a `BOOKURIER_CLIENT_ID` that is not an
integer, throws `InvalidArgumentException` from `fromEnvironment()`. Unset and
empty `BOOKURIER_TIMEOUT` / `BOOKURIER_CLIENT_ID` values are treated as `null`.

```bash
export BOOKURIER_USERNAME=your-bookurier-user
export BOOKURIER_PASSWORD=your-bookurier-password
export BOOKURIER_CLIENT_ID=1234
```

```php
$client = Client::create(); // reads the variables above
```

`BOOKURIER_TIMEOUT` is cast to `float` when it is set to a non-empty value; a
non-numeric value throws `InvalidArgumentException`. Set it to a number of
seconds.

## Credentials

Bookurier authenticates with the account username and password, not an API key.
`Client::post()` adds them to every form body:

```text
userid=your-bookurier-user&pwd=your-bookurier-password&msg=...
```

If either value is empty, `Client::post()` throws
`Daika7ana\Bookurier\Exceptions\AuthenticationException` before sending the
request. This check lives in `post()`, not in the HTTP transport: a request built
and passed directly to the low-level `Client::send()` method does not gain
credentials automatically.

Replace credentials at runtime with `setCredentials()`, which updates the client's
config and returns the same client:

```php
$client->setCredentials('new-user', 'new-password');
```

`ClientConfig::withCredentials()` returns a new value object instead of mutating
the client:

```php
$config = $client->getConfig()->withCredentials('new-user', 'new-password');
```

The password is sent in plain form fields over HTTPS. Redact `userid`, `pwd` and
full request bodies from logs.

## Base URL

The default is `https://www.bookurier.ro/colete/serv`
(`ClientConfig::BASE_URL`). The documentation lists `http://` URLs for the same
paths; the SDK uses HTTPS. Override the base URL for a proxy or a test gateway:

```php
$config = new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    baseUrl: 'https://bookurier-proxy.internal/colete/serv',
);
```

The client joins `baseUrl` and an endpoint by trimming the trailing and leading
slashes, so the default value with no trailing slash produces
`https://www.bookurier.ro/colete/serv/add_cmd.php`.

## Timeouts

`ClientConfig::timeout` is `?float` and defaults to `null`, so the bundled
`GuzzleHttpClient` applies its own 30 s request / 10 s connect defaults.
Precedence: the per-call argument passed to `Client::post()` / `Client::send()` →
`ClientConfig::timeout` → the transport's constructor arguments
(`new GuzzleHttpClient(timeout: 10.0, connectTimeout: 5.0)`) → its 30 s / 10 s
defaults. Resource methods such as `orders()->create()` do not take a timeout
argument; set the default on `ClientConfig` or use the low-level methods:

```php
use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;

$client = Client::create(config: new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
    timeout: 10.0,
));

$response = $client->post('get_AWB_list.php', timeout: 15.0);
```

A per-call timeout replaces only the request timeout. The connect timeout comes
from `GuzzleHttpClient`'s `connectTimeout:` argument; the SDK has no per-call
connect timeout.

When some calls need a different budget than the rest, derive a client:
`$slowClient = $client->withTimeout(30.0);` returns a new client sharing the
transport and the PSR-17 factories, carrying the credentials configured when it
is derived, so no new transport is built; the original client keeps its
timeout. `withTimeout(null)` returns to the transport default.

`BOOKURIER_TIMEOUT` feeds `ClientConfig::timeout` through
`ClientConfig::fromEnvironment()`, so it sits in the same chain and a non-numeric
value throws `InvalidArgumentException`. Because `Client::create()` falls back to
`fromEnvironment()`, a set `BOOKURIER_TIMEOUT` also counts as a non-null config
timeout when you inject a foreign PSR-18 client.

If you inject a `GuzzleHttp\Client`, its own configuration is the fallback when
no SDK timeout is set. Explicit `timeout:` and `connectTimeout:` arguments on
`GuzzleHttpClient` are still applied on each request.

## Custom HTTP Client

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
using a foreign client. See [Timeouts](#timeouts) for how `BOOKURIER_TIMEOUT`
participates in that check.

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
