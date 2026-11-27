<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ReportsResourceTest extends TestCase
{
    public function testBorderouPostsTheConfiguredPickupPointAndDate(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::html(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $document = $client->reports()->borderou('2026-11-26');

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/print_borderou.php', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'user' => 'test-user',
            'pwd' => 'test-password',
            'pickup' => '5',
            'filter_date' => '2026-11-26',
        ], $httpClient->lastForm());

        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertFalse($document->isPdf());
    }

    public function testExplicitPickupPointOverridesTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::html(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $client->reports()->borderou('26-11-2026', '9', 15.0);

        self::assertSame('9', $httpClient->lastForm()['pickup']);
        self::assertSame('26-11-2026', $httpClient->lastForm()['filter_date']);
        self::assertSame(15.0, $httpClient->lastTimeout);
    }

    public function testBlankPickupPointFallsBackToTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::html(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $client->reports()->borderou('2026-11-26', ' ');

        self::assertSame('5', $httpClient->lastForm()['pickup']);
    }

    public function testBorderouRequiresAPickupPoint(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        try {
            $client->reports()->borderou('2026-11-26');

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('BOOKURIER_PICKUP_POINT', $e->getMessage());
        }

        self::assertNull($httpClient->lastRequest);
    }

    public function testBorderouClassifiesAJsonErrorEnvelope(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::error('No data'),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No data');

        $client->reports()->borderou('2026-11-26');
    }
}
