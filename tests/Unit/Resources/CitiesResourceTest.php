<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\DTOs\Order\PartnerCity;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class CitiesResourceTest extends TestCase
{
    public function testPartnerCitiesPostsAnEmptyDataObjectAndMapsTheList(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::partnerCities());

        $cities = $client->cities()->partnerCities();

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/get_partner_cities.php', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $body = $httpClient->lastJson();
        self::assertSame('test-user', $body['user']);
        self::assertSame('test-password', $body['pwd']);
        self::assertSame([], $body['data']);
        self::assertStringContainsString('"data":{}', (string) $request->getBody());

        self::assertCount(2, $cities);
        $items = $cities->all();
        self::assertInstanceOf(PartnerCity::class, $items[0]);
        self::assertSame('Ploiesti', $items[0]->city);
        self::assertSame('Prahova', $items[0]->district);
        self::assertSame('100087', $items[0]->zip);
        self::assertSame('MUNTENIA', $items[0]->zone);
        self::assertSame('PH', $items[0]->sort);
        self::assertSame('20102', $items[0]->driver);
        self::assertSame(300, $items[0]->kg);
    }

    public function testDistrictFilterIsSentInsideData(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::partnerCities());

        $client->cities()->partnerCities('Prahova');

        self::assertSame(['district' => 'Prahova'], $httpClient->lastJson()['data']);
    }

    public function testAnInvalidDistrictBecomesAValidationException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::error('Judet invalid: Prahova'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Judet invalid: Prahova');

        $client->cities()->partnerCities('Prahova');
    }
}
