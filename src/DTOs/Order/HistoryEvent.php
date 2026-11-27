<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

use Daika7ana\Bookurier\Enums\Status;

/**
 * One awb_history.php event. The last event in the list is the current status.
 */
final readonly class HistoryEvent
{
    /**
     * @param string|null $statusId Null when the partner courier sends free text only.
     * @param string $date Vendor "data", formatted dd.mm.yyyy.
     * @param string $sortDate Vendor "sort_date", formatted Y-m-d H:i:s.
     */
    public function __construct(
        public string $date,
        public ?string $statusId,
        public string $statusName,
        public string $obs,
        public string $sortDate,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusId = $data['status_id'] ?? null;

        return new self(
            date: (string) ($data['data'] ?? ''),
            statusId: $statusId === null || $statusId === '' ? null : (string) $statusId,
            statusName: (string) ($data['status_name'] ?? ''),
            obs: (string) ($data['obs'] ?? ''),
            sortDate: (string) ($data['sort_date'] ?? ''),
        );
    }

    public function status(): ?Status
    {
        return $this->statusId === null || ! preg_match('/^-?\d+$/', $this->statusId) ? null : Status::tryFrom((int) $this->statusId);
    }
}
