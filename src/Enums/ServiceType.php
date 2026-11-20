<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Enums;

enum ServiceType: int
{
    case Bucharest24h = 1;

    /**
     * Listed in the original documentation; not offered by the plugin's service picker.
     */
    case BucharestExpress = 2;

    case Metropolitan = 3;
    case Extins = 5;
    case BucharestToday = 7;
    case NationalEconomic = 8;

    /**
     * The plugin labels this "National Standard"; earlier documentation called it "National 24".
     */
    case National24 = 9;

    case NationalPremium = 11;

    public function label(): string
    {
        return match ($this) {
            self::Bucharest24h => 'Bucharest 24h',
            self::BucharestExpress => 'Bucharest express',
            self::Metropolitan => 'Metropolitan',
            self::Extins => 'Extins',
            self::BucharestToday => 'Bucharest Today',
            self::NationalEconomic => 'National Economic',
            self::National24 => 'National Standard',
            self::NationalPremium => 'National Premium',
        };
    }
}
