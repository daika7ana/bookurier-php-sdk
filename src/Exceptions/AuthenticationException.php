<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thrown when the credentials are missing, Bookurier answers "Invalid user" / "Login fail",
 * or the request is answered with HTTP 401.
 */
class AuthenticationException extends BookurierException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?ResponseInterface $response = null,
        public readonly ?int $retryAfter = null,
        public readonly ?BookurierError $error = null,
    ) {
        parent::__construct($message, $response?->getStatusCode() ?? $code, $previous);
    }
}
