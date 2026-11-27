<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

/**
 * A partner-served city from get_partner_cities.php.
 */
final readonly class PartnerCity
{
    public function __construct(
        public string $city,
        public string $district,
        public string $zip,
        public string $zone,
        public string $sort,
        public string $driver,
        public int $kg,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            city: (string) ($data['city'] ?? ''),
            district: (string) ($data['district'] ?? ''),
            zip: (string) ($data['zip'] ?? ''),
            zone: (string) ($data['zone'] ?? ''),
            sort: (string) ($data['sort'] ?? ''),
            driver: (string) ($data['driver'] ?? ''),
            kg: (int) ($data['kg'] ?? 0),
        );
    }
}
