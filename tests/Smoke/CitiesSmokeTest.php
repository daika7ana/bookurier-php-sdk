<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\DTOs\Order\PartnerCity;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live read-only check for partner-served cities.
 */
#[Group('smoke')]
final class CitiesSmokeTest extends SmokeTestCase
{
    public function testPartnerCitiesReturnList(): void
    {
        $cities = $this->client()->cities()->partnerCities();

        $this->assertGreaterThan(0, $cities->count());
        foreach ($cities as $city) {
            $this->assertInstanceOf(PartnerCity::class, $city);
            $this->assertNotSame('', $city->city);
        }
    }
}
