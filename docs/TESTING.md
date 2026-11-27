# Testing

## Commands

```bash
composer ci        # validate --strict -> pint --test -> phpstan -> phpunit tests/Unit
composer pint      # style check only, never fixes
composer stan      # PHPStan level 8 over src/
composer test      # full PHPUnit suite
composer test:unit # tests/Unit only
composer test:smoke # tests/Smoke only, needs BOOKURIER_TEST_* env vars
vendor/bin/pint    # the only way to auto-fix style
```

`composer ci` expands to:

1. `composer validate --strict`
2. `composer pint` (`vendor/bin/pint --test`)
3. `composer stan` (`vendor/bin/phpstan --no-progress`)
4. `composer test:unit` (`vendor/bin/phpunit tests/Unit`)

Run one file or filter:

```bash
composer test -- tests/Unit/Resources/OrdersResourceTest.php
composer test -- --filter=OrdersResourceTest
```

## How the tests work

Unit tests never touch the network and need no Bookurier credentials.
`TestClientFactory` wires a `Client` to a fake transport and the Guzzle PSR-17
factory:

```php
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;

[$client, $httpClient] = TestClientFactory::create($handler, $config);
```

- `tests/Support/TestClientFactory.php` — `create(callable $handler, ?ClientConfig $config = null)`
  returns `[Client, FakeHttpClient]`. The default config uses
  `userId: 'test-user'`, `password: 'test-password'`.
- `tests/Support/FakeHttpClient.php` — implements the SDK transport contract,
  records `lastRequest` and `lastTimeout`, and returns the handler's response.
  With no handler it returns an empty `200` response. `lastJson()` and
  `lastForm()` decode the recorded body.
- `tests/Support/Fixture.php` — canned PSR-7 responses: `response()`, `json()`,
  `addSuccess()`, `addSuccessWithoutMap()`, `batchSuccess()`, `batchPartial()`,
  `orderError()`, `history()`, `historyWithPartnerText()`, `partnerCities()`,
  `pickup()`, `error()`, `rateLimited()`, `html()`, `pdf()` and `challenge()`.

A test asserts both the outgoing request and the mapped result:

```php
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class OrdersResourceTest extends TestCase
{
    public function testCreatePostsTheOrderAndReturnsTheAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn (): ResponseInterface => Fixture::addSuccess(),
            new ClientConfig(
                userId: 'test-user',
                password: 'test-password',
                pickupPoint: '5',
            ),
        );

        $awb = $client->orders()->create(new Order(
            uniqueId: 'CMD-10001',
            recipientName: 'Ion Popescu',
            phone: '0712345678',
            city: 'Bucharest',
            street: 'Piața Unirii nr. 10',
            service: ServiceType::Bucharest24h,
            weight: 2.5,
            district: 'Sector 1',
        ));

        self::assertSame('BOK6000123456', $awb);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://portal.bookurier.ro/api/add_cmds.php',
            (string) $request->getUri(),
        );

        $body = $httpClient->lastJson();
        self::assertSame('test-user', $body['user']);
        self::assertSame('test-password', $body['pwd']);
        self::assertSame('5', $body['data'][0]['pickup_point']);
        self::assertSame('CMD-10001', $body['data'][0]['unq']);
    }
}
```

Every endpoint needs a test covering both the request (method, path, headers,
JSON or form body) and the response mapping. Use `Fixture::error()` for a
`status: "error"` envelope, `Fixture::rateLimited()` for a `429`, and
`Fixture::challenge()` for the bot-filter page.

## Live smoke tests

`tests/Smoke` talks to the real portal API. The suite skips itself unless all
three of `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` and
`BOOKURIER_TEST_PICKUP_POINT` are set. This applies to the read-only tests too,
because `SmokeTestCase::setUp()` checks all three for every test.

| Variable | Purpose |
|---|---|
| `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` | Portal test account credentials |
| `BOOKURIER_TEST_PICKUP_POINT` | Pickup-point number used by orders and the bordero |
| `BOOKURIER_TEST_ALLOW_WRITES` | Must be `1` to create live AWBs |
| `BOOKURIER_TEST_AWB` | An existing AWB for read-only history and label checks |

```bash
BOOKURIER_TEST_USERNAME=... BOOKURIER_TEST_PASSWORD=... \
BOOKURIER_TEST_PICKUP_POINT=... BOOKURIER_TEST_ALLOW_WRITES=1 \
composer test:smoke
```

Coverage:

- `ClientSmokeTest` — write lifecycle: create one order and fetch its history and
  PDF/HTML labels; create a two-order batch and read the first reference's
  history. There is no delete endpoint; the created AWB is left in place and is
  not billed unless scanned.
- `OrderLookupSmokeTest` — read-only history and PDF/HTML labels for an existing
  AWB from `BOOKURIER_TEST_AWB`; skips without it.
- `ReportsSmokeTest` — the daily bordero for today.
- `CitiesSmokeTest` — partner-served cities.

`SmokeTestCase` holds the credential skip, the sample order and the 0.5 s pause
the host bot filter needs. `pickup()->request()` is never smoke-tested: it is
`@experimental` and dispatches a physical courier.

The vendor documents a `demo` / `demo` portal account with pickup point `5` for
manual trials. It is mentioned here for orientation only; the smoke suite reads
its own environment variables and must never hardcode an account.

## CI

`.github/workflows/ci.yml` runs the same checks in separate jobs:

- `composer validate --strict`, Pint and PHPStan on PHP 8.3.
- PHPUnit (`composer test:unit`) on PHP 8.3, 8.4 and 8.5.

All jobs run on `master` pushes and pull requests. Jobs are skipped for commits
or PR titles starting with `docs` or `chore`, and for messages containing
`[skip ci]` or `[skip-ci]`.
