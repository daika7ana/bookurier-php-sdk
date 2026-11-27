<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Enums;

enum ServiceType: int
{
    case Bucharest24h = 1;
    case Metropolitan = 3;
    case IlfovExtins = 5;
    case NationalEconomic = 8;
    case National24 = 9;
    case NationalPremium = 11;

    public function label(): string
    {
        return match ($this) {
            self::Bucharest24h => 'Bucharest 24h',
            self::Metropolitan => 'Metropolitan',
            self::IlfovExtins => 'Ilfov Extins',
            self::NationalEconomic => 'National Economic',
            self::National24 => 'National 24',
            self::NationalPremium => 'National Premium',
        };
    }
}
