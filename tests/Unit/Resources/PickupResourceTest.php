<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class PickupResourceTest extends TestCase
{
    public function testRequestPostsTheConfiguredPickupPointAndReturnsTheMessage(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::pickup(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $message = $client->pickup()->request();

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/request_pickup.php', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'user' => 'test-user',
            'pwd' => 'test-password',
            'pickup' => '5',
        ], $httpClient->lastForm());

        self::assertSame('Pickup requested for tomorrow.', $message);
    }

    public function testExplicitPickupPointOverridesTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::pickup(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $client->pickup()->request('9');

        self::assertSame('9', $httpClient->lastForm()['pickup']);
    }

    public function testBlankPickupPointFallsBackToTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::pickup(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $client->pickup()->request('');

        self::assertSame('5', $httpClient->lastForm()['pickup']);
    }

    public function testRequestRequiresAPickupPoint(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::pickup());

        try {
            $client->pickup()->request();

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('BOOKURIER_PICKUP_POINT', $e->getMessage());
        }

        self::assertNull($httpClient->lastRequest);
    }

    public function testLoginFailBecomesAnAuthenticationException(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::error('Login fail'),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $this->expectException(AuthenticationException::class);

        $client->pickup()->request();
    }
}
