<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

final readonly class HistoryEntry
{
    /**
     * @param string|null $date Raw `Y-m-d H:i:s` timestamp as Bookurier sends it, with no timezone.
     */
    public function __construct(
        public string $uniqueId,
        public string $awb,
        public int $statusId,
        public string $statusDescription,
        public ?string $date = null,
        public ?string $observations = null,
    ) {}

    /**
     * @param array<string, string> $data
     */
    public static function fromArray(array $data): self
    {
        $date = $data['date'] ?? '';
        $observations = $data['obs'] ?? '';

        return new self(
            uniqueId: $data['unq'] ?? '',
            awb: $data['awb'] ?? '',
            statusId: (int) ($data['status_id'] ?? 0),
            statusDescription: $data['status_desc'] ?? '',
            date: $date === '' ? null : $date,
            observations: $observations === '' ? null : $observations,
        );
    }
}
