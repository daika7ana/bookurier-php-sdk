<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Enums;

use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\TestCase;

final class ServiceTypeTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame(1, ServiceType::Bucharest24h->value);
        self::assertSame(3, ServiceType::Metropolitan->value);
        self::assertSame(5, ServiceType::IlfovExtins->value);
        self::assertSame(8, ServiceType::NationalEconomic->value);
        self::assertSame(9, ServiceType::National24->value);
        self::assertSame(11, ServiceType::NationalPremium->value);
    }

    public function testLabels(): void
    {
        self::assertSame('Bucharest 24h', ServiceType::Bucharest24h->label());
        self::assertSame('Metropolitan', ServiceType::Metropolitan->label());
        self::assertSame('Ilfov Extins', ServiceType::IlfovExtins->label());
        self::assertSame('National Economic', ServiceType::NationalEconomic->label());
        self::assertSame('National 24', ServiceType::National24->label());
        self::assertSame('National Premium', ServiceType::NationalPremium->label());
    }
}
