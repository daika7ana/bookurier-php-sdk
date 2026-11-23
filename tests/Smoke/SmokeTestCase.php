<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\TestCase;

/**
 * Live API base. Skipped unless BOOKURIER_TEST_USERNAME,
 * BOOKURIER_TEST_PASSWORD and BOOKURIER_TEST_CLIENT_ID are set.
 */
abstract class SmokeTestCase extends TestCase
{
    private ?Client $client = null;

    protected function setUp(): void
    {
        $username = getenv('BOOKURIER_TEST_USERNAME');
        $password = getenv('BOOKURIER_TEST_PASSWORD');

        if ($username === false || $username === '' || $password === false || $password === '') {
            $this->markTestSkipped('BOOKURIER_TEST_USERNAME and BOOKURIER_TEST_PASSWORD must be set to run live smoke tests.');
        }

        $clientId = getenv('BOOKURIER_TEST_CLIENT_ID');

        if ($clientId === false || $clientId === '') {
            $this->markTestSkipped('BOOKURIER_TEST_CLIENT_ID must be set: Bookurier requires a client code to create an order.');
        }
    }

    protected function client(): Client
    {
        return $this->client ??= Client::create(config: new ClientConfig(
            userId: (string) getenv('BOOKURIER_TEST_USERNAME'),
            password: (string) getenv('BOOKURIER_TEST_PASSWORD'),
            clientId: (int) getenv('BOOKURIER_TEST_CLIENT_ID'),
        ));
    }

    protected function requireWriteAccess(): void
    {
        if (getenv('BOOKURIER_TEST_ALLOW_WRITES') !== '1') {
            $this->markTestSkipped('BOOKURIER_TEST_ALLOW_WRITES=1 is required: the smoke test writes live AWBs (and deletes them afterwards).');
        }
    }

    protected function existingAwb(): string
    {
        return $this->requiredEnv('BOOKURIER_TEST_AWB', 'an existing AWB');
    }

    protected function existingUniqueId(): string
    {
        return $this->requiredEnv('BOOKURIER_TEST_UNIQUE_ID', 'an existing order unique id');
    }

    protected function order(string $uniqueId): Order
    {
        return new Order(
            uniqueId: $uniqueId,
            recipientName: 'Smoke Test',
            phone: '0722123456',
            city: 'București',
            district: 'Sector 1',
            street: 'Strada Testului',
            number: '1',
            service: ServiceType::National24,
            weight: 0.5,
        );
    }

    // The host bot filter answers request bursts with an HTML challenge page.
    protected function pause(): void
    {
        usleep(500_000);
    }

    private function requiredEnv(string $key, string $description): string
    {
        $value = getenv($key);

        if ($value === false || $value === '') {
            $this->markTestSkipped(sprintf('Skipping: %s must be set to %s.', $key, $description));
        }

        return $value;
    }
}
