# Installation

## Requirements

- PHP 8.3 or newer
- Composer 2
- A PSR-7/17/18 compatible environment

The SDK depends on the PSR-7, PSR-17 and PSR-18 interfaces and installs a Guzzle
transport by default. `guzzlehttp/guzzle` supplies that transport;
`guzzlehttp/psr7` supplies the PSR-17 factories used when you do not pass your
own. `daika7ana/simple-collections` provides the `Collection` returned by
`history()`, `partnerCities()` and `BatchResult::references()` / `errors()`. All
of them are runtime dependencies, so no extra install step is required.

The package is a library. It has no server, no web entrypoint and no framework
service provider.

## Install

```bash
composer require daika7ana/bookurier-php-sdk:^2.0
```

## Verify

```php
require 'vendor/autoload.php';

use Daika7ana\Bookurier\Config\ClientConfig;

$config = new ClientConfig(
    userId: 'your-portal-user',
    password: 'your-portal-password',
    pickupPoint: '5',
);

echo $config->baseUrl; // https://portal.bookurier.ro/api
```

Constructing `ClientConfig` does not make a network request. The client only
contacts Bookurier when you call a resource method.

## Development install

```bash
git clone https://github.com/daika7ana/bookurier-php-sdk.git
cd bookurier-php-sdk
composer install
composer ci
```

`composer ci` runs `composer validate --strict`, Pint, PHPStan and the unit
suite. See [TESTING.md](TESTING.md) for the individual commands.
