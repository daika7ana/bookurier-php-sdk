<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use Throwable;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when Bookurier rejects the request data (JSON envelope status "error"),
 * or the request is answered with HTTP 400 / 422.
 */
class ValidationException extends BookurierException
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(
        public readonly array $errors = [],
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?ResponseInterface $response = null,
        public readonly ?int $retryAfter = null,
        public readonly ?BookurierError $error = null,
    ) {
        parent::__construct($message ?: 'Validation failed', $response?->getStatusCode() ?? $code, $previous);
    }
}
