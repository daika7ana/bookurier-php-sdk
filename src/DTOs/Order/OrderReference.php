<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

final readonly class OrderReference
{
    public function __construct(
        public string $uniqueId,
        public string $awb,
    ) {}
}
