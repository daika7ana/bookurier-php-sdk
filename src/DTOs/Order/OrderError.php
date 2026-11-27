<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

/**
 * One failed order reported in add_cmds.php's errors list.
 */
final readonly class OrderError
{
    public function __construct(
        public string $order,
        public string $message,
        public ?string $suggestion = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $suggestion = $data['suggestion'] ?? null;

        return new self(
            order: (string) ($data['order'] ?? ''),
            message: (string) ($data['message'] ?? ''),
            suggestion: $suggestion === null || $suggestion === '' ? null : (string) $suggestion,
        );
    }
}
