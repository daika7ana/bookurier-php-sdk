<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Common\Errors;

/**
 * A Bookurier error reported as {"status":"error","message":"..."}.
 */
final readonly class BookurierError
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public int $code,
        public string $message,
        public string $id,
        public array $raw,
        public ?string $context = null,
        public ?string $component = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, int $fallbackCode = 0): self
    {
        $message = $data['message'] ?? 'Bookurier API error.';

        return new self(
            code: (int) ($data['code'] ?? $fallbackCode),
            message: is_string($message) ? $message : 'Bookurier API error.',
            id: (string) ($data['id'] ?? ''),
            raw: $data,
            context: isset($data['context']) ? (string) $data['context'] : null,
            component: isset($data['component']) ? (string) $data['component'] : null,
        );
    }
}
