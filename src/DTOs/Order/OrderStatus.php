<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

final readonly class OrderStatus
{
    public function __construct(
        public string $uniqueId,
        public string $awb,
        public int $statusId,
        public string $statusDescription,
        public ?string $observations = null,
    ) {}

    /**
     * @param array<string, string> $data
     */
    public static function fromArray(array $data): self
    {
        $observations = $data['obs'] ?? '';

        return new self(
            uniqueId: $data['unq'] ?? '',
            awb: $data['awb'] ?? '',
            statusId: (int) ($data['status_id'] ?? 0),
            statusDescription: $data['status_desc'] ?? '',
            observations: $observations === '' ? null : $observations,
        );
    }
}
