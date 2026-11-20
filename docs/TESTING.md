# Testing

## Commands

```bash
composer ci        # validate --strict -> pint --test -> phpstan -> phpunit tests/Unit
composer pint      # style check only, never fixes
composer stan      # PHPStan level 8 over src/
composer test      # full PHPUnit suite
composer test:unit # tests/Unit only
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

## How the Tests Work

Unit tests never touch the network and need no Bookurier credentials.
`TestClientFactory` wires a `Client` to a fake transport and the Guzzle PSR-17
factory:

```php
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;

[$client, $httpClient] = TestClientFactory::create($handler, $config);
```

- `tests/Support/TestClientFactory.php` — `create(?callable $handler = null, ?ClientConfig $config = null)`
  returns `[Client, FakeHttpClient]`. The default config uses
  `userId: 'test-user'`, `password: 'test-password'`.
- `tests/Support/FakeHttpClient.php` — implements the SDK transport contract,
  records `lastRequest` and `lastTimeout`, and returns the handler's response.
  With no handler it returns an empty `200` response.
- `tests/Support/Fixture.php` — canned PSR-7 responses: `response()`, `awb()`,
  `success()`, `error()`, `xml()`, `html()`, `pdf()`, `status()`, `history()`,
  `batch()`.

A test asserts both the outgoing request and the mapped result:

```php
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
            static fn (): ResponseInterface => Fixture::awb(),
        );

        $awb = $client->orders()->create(new Order(
            uniqueId: 'A1',
            recipientName: 'Ion Popescu',
            phone: '0712345678',
            city: 'Bucharest',
            street: 'Piața Unirii',
            number: '10',
            service: ServiceType::BucharestExpress,
            weight: 2.5,
        ));

        self::assertSame('BOK3925457', $awb);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/colete/serv/add_cmd.php', $request->getUri()->getPath());

        parse_str((string) $request->getBody(), $form);
        self::assertSame('test-user', $form['userid']);
        self::assertSame('test-password', $form['pwd']);
        self::assertStringContainsString('<unq>A1</unq>', $form['msg']);
    }
}
```

Every endpoint needs a test covering both the request (method, path, form fields,
XML body) and the response mapping. `Fixture::error()` produces an in-band
`<err>` envelope for `ApiException` tests; `Fixture::response('-1')` covers the
scalar `-1` path.

## Live Smoke Tests

`tests/Smoke` talks to the real Bookurier API. It is skipped unless the
environment is configured:

| Variable | Purpose |
|---|---|
| `BOOKURIER_TEST_USERNAME`, `BOOKURIER_TEST_PASSWORD` | Test account credentials; required to run anything |
| `BOOKURIER_TEST_CLIENT_ID` | Client code sent as `<client>`; required to create an order |
| `BOOKURIER_TEST_ALLOW_WRITES` | Must be `1` to create and delete a live AWB |

```bash
BOOKURIER_TEST_USERNAME=... BOOKURIER_TEST_PASSWORD=... \
BOOKURIER_TEST_CLIENT_ID=... BOOKURIER_TEST_ALLOW_WRITES=1 \
composer test:smoke
```

The lifecycle test creates one order, checks status and that the PDF is a real
PDF, and deletes the AWB in a `finally` block. Smoke tests never run in
`composer ci`.

## CI

`.github/workflows/ci.yml` runs the same checks in separate jobs:

- `composer validate --strict`, Pint and PHPStan on PHP 8.3.
- PHPUnit (`composer test:unit`) on PHP 8.3, 8.4 and 8.5.

All jobs run on `master` pushes and pull requests.
