<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

/**
 * Thrown when the HTTP transport fails; the original exception is the `previous` one.
 */
class TransportException extends BookurierException implements ClientExceptionInterface
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message ?: 'Transport error', $code, $previous);
    }
}
