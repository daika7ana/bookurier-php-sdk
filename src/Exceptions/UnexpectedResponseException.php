<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

/**
 * Thrown when the response is neither the documented success value nor a parseable XML document.
 */
final class UnexpectedResponseException extends BookurierException
{
    public static function withBody(string $message, string $body): self
    {
        return new self(sprintf('%s Got: "%s".', $message, substr($body, 0, 200)));
    }
}
