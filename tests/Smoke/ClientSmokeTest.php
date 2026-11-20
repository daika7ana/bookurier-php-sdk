<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Live API check. Skipped unless BOOKURIER_TEST_USERNAME,
 * BOOKURIER_TEST_PASSWORD and BOOKURIER_TEST_CLIENT_ID are set; writes
 * additionally need BOOKURIER_TEST_ALLOW_WRITES=1.
 */
#[Group('smoke')]
final class ClientSmokeTest extends TestCase
{
    public function testCreateStatusPdfDelete(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueId = 'smoke-' . bin2hex(random_bytes(4));
        $awb = null;

        try {
            $awb = $client->orders()->create($this->order($uniqueId));

            $this->assertMatchesRegularExpression('/^[A-Za-z]{0,4}\d{3,}$/', $awb);

            $status = $client->orders()->statusByUniqueId($uniqueId);
            $this->assertSame($awb, $status->awb);

            $this->assertTrue($client->labels()->pdf($awb)->isPdf());
        } finally {
            if ($awb !== null) {
                $client->orders()->delete($awb);
            }
        }
    }

    public function testStatusByUniqueId(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueId = 'smoke-' . bin2hex(random_bytes(4));
        $awb = null;

        try {
            $awb = $client->orders()->create($this->order($uniqueId));

            $status = $client->orders()->statusByUniqueId($uniqueId);
            $this->assertSame($uniqueId, $status->uniqueId);
            $this->assertSame($awb, $status->awb);
        } finally {
            if ($awb !== null) {
                $client->orders()->delete($awb);
            }
        }
    }

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

    private function requireWriteAccess(): void
    {
        if (getenv('BOOKURIER_TEST_ALLOW_WRITES') !== '1') {
            $this->markTestSkipped('BOOKURIER_TEST_ALLOW_WRITES=1 is required: the smoke test creates a live AWB (and deletes it afterwards).');
        }
    }

    private function client(): Client
    {
        return Client::create(config: new ClientConfig(
            userId: (string) getenv('BOOKURIER_TEST_USERNAME'),
            password: (string) getenv('BOOKURIER_TEST_PASSWORD'),
            clientId: $this->clientId(),
        ));
    }

    private function clientId(): ?int
    {
        $clientId = getenv('BOOKURIER_TEST_CLIENT_ID');

        return $clientId === false || $clientId === '' ? null : (int) $clientId;
    }

    private function order(string $uniqueId): Order
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
}
