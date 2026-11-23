<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Common\Errors;

/**
 * A Bookurier error reported in-band: a negative body code or a non-empty `<err>`.
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
        $message = $data['message'] ?? $data['error'] ?? 'Bookurier API error.';

        return new self(
            code: (int) ($data['code'] ?? $fallbackCode),
            message: is_string($message) ? $message : 'Bookurier API error.',
            id: (string) ($data['id'] ?? $data['error_id'] ?? ''),
            raw: $data,
            context: isset($data['context']) ? (string) $data['context'] : null,
            component: isset($data['component']) ? (string) $data['component'] : null,
        );
    }

    public function isValidationRelated(): bool
    {
        if (in_array($this->code, [-2, -3, -5], true)) {
            return true;
        }

        $message = strtolower($this->message);

        return str_contains($message, 'invalid')
            || str_contains($message, 'inexistent')
            || str_contains($message, 'not found')
            || str_contains($message, 'missing')
            || str_contains($message, 'incomplete');
    }

    /**
     * `-1` is the code the service answers for bad credentials (and a malformed `msg`).
     */
    public function isAuthenticationRelated(): bool
    {
        if ($this->code === -1) {
            return true;
        }

        $message = strtolower($this->message);

        return str_contains($message, 'auth')
            || str_contains($message, 'password')
            || str_contains($message, 'credential')
            || str_contains($message, 'userid')
            || str_contains($message, 'logged user');
    }
}
