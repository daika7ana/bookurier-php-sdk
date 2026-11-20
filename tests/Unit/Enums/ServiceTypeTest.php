<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Enums;

use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\TestCase;

final class ServiceTypeTest extends TestCase
{
    public function testLabels(): void
    {
        self::assertSame('Bucharest 24h', ServiceType::Bucharest24h->label());
        self::assertSame('Bucharest express', ServiceType::BucharestExpress->label());
        self::assertSame('Metropolitan', ServiceType::Metropolitan->label());
        self::assertSame('Extins', ServiceType::Extins->label());
        self::assertSame('Bucharest Today', ServiceType::BucharestToday->label());
        self::assertSame('National Economic', ServiceType::NationalEconomic->label());
        self::assertSame('National Standard', ServiceType::National24->label());
        self::assertSame('National Premium', ServiceType::NationalPremium->label());
    }
}
