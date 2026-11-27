<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\TestCase;

/**
 * Live API base. Skipped unless BOOKURIER_TEST_USERNAME, BOOKURIER_TEST_PASSWORD
 * and BOOKURIER_TEST_PICKUP_POINT are set.
 */
abstract class SmokeTestCase extends TestCase
{
    private ?Client $client = null;

    protected function setUp(): void
    {
        foreach (['BOOKURIER_TEST_USERNAME', 'BOOKURIER_TEST_PASSWORD', 'BOOKURIER_TEST_PICKUP_POINT'] as $name) {
            $value = getenv($name);

            if ($value === false || $value === '') {
                $this->markTestSkipped($name . ' must be set to run live smoke tests.');
            }
        }
    }

    protected function client(): Client
    {
        return $this->client ??= Client::create(config: new ClientConfig(
            userId: (string) getenv('BOOKURIER_TEST_USERNAME'),
            password: (string) getenv('BOOKURIER_TEST_PASSWORD'),
            pickupPoint: (string) getenv('BOOKURIER_TEST_PICKUP_POINT'),
        ));
    }

    protected function requireWriteAccess(): void
    {
        if (getenv('BOOKURIER_TEST_ALLOW_WRITES') !== '1') {
            $this->markTestSkipped('BOOKURIER_TEST_ALLOW_WRITES=1 is required: the smoke test creates live AWBs (there is no delete endpoint; unscanned AWBs are not billed).');
        }
    }

    protected function existingAwb(): string
    {
        $value = getenv('BOOKURIER_TEST_AWB');

        if ($value === false || $value === '') {
            $this->markTestSkipped('Skipping: BOOKURIER_TEST_AWB must be set to an existing AWB.');
        }

        return $value;
    }

    protected function order(string $uniqueId): Order
    {
        return new Order(
            uniqueId: $uniqueId,
            recipientName: 'Smoke Test',
            phone: '0722123456',
            city: 'Deva',
            district: 'Hunedoara',
            street: 'Strada Testului',
            number: '1',
            service: ServiceType::National24,
            weight: 0.5,
            zip: '330020',
        );
    }

    // The host bot filter answers request bursts with an HTML challenge page.
    protected function pause(): void
    {
        usleep(500_000);
    }
}
