# Installation

## Requirements

- PHP 8.3 or newer
- `ext-dom`
- Composer 2
- A PSR-7/17/18 compatible environment

The SDK depends on the PSR-7, PSR-17 and PSR-18 interfaces and installs a Guzzle
transport by default. `guzzlehttp/guzzle` supplies that transport;
`guzzlehttp/psr7` supplies the PSR-17 factories used when you do not pass your
own. `daika7ana/simple-collections` provides the `Collection` returned by
`createBatch()` and the `history*()` methods. All of them are runtime
dependencies, so no extra install step is required.

The package is a library. It has no server, no web entrypoint and no framework
service provider.

## Install

```bash
composer require daika7ana/bookurier-php-sdk
```

## Verify

```php
require 'vendor/autoload.php';

use Daika7ana\Bookurier\Config\ClientConfig;

$config = new ClientConfig(
    userId: 'your-bookurier-user',
    password: 'your-bookurier-password',
);

echo $config->baseUrl; // https://www.bookurier.ro/colete/serv
```

Constructing `ClientConfig` does not make a network request. The client only
contacts Bookurier when you call a resource method.

## Development Install

```bash
git clone https://github.com/daika7ana/bookurier-php-sdk.git
cd bookurier-php-sdk
composer install
composer ci
```

`composer ci` runs `composer validate --strict`, Pint, PHPStan and the unit
suite. See [TESTING.md](TESTING.md) for the individual commands.
