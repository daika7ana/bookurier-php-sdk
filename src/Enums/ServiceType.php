<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Enums;

enum ServiceType: int
{
    case Bucharest24h = 1;
    case BucharestExpress = 2;
    case Metropolitan = 3;
    case National24 = 9;

    public function label(): string
    {
        return match ($this) {
            self::Bucharest24h => 'Bucharest 24h',
            self::BucharestExpress => 'Bucharest express',
            self::Metropolitan => 'Metropolitan',
            self::National24 => 'National 24h',
        };
    }
}
