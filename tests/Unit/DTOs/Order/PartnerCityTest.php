<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Order;

use Daika7ana\Bookurier\DTOs\Order\PartnerCity;
use PHPUnit\Framework\TestCase;

final class PartnerCityTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $city = PartnerCity::fromArray([
            'city' => 'Ploiesti',
            'district' => 'Prahova',
            'zip' => '100087',
            'zone' => 'MUNTENIA',
            'sort' => 'PH',
            'driver' => '20102',
            'kg' => 300,
        ]);

        self::assertSame('Ploiesti', $city->city);
        self::assertSame('Prahova', $city->district);
        self::assertSame('100087', $city->zip);
        self::assertSame('MUNTENIA', $city->zone);
        self::assertSame('PH', $city->sort);
        self::assertSame('20102', $city->driver);
        self::assertSame(300, $city->kg);
    }

    public function testMissingFieldsFallBackToDefaults(): void
    {
        $city = PartnerCity::fromArray(['city' => 'Ploiesti']);

        self::assertSame('', $city->district);
        self::assertSame('', $city->zip);
        self::assertSame(0, $city->kg);
    }
}
