<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when the response body does not match what the endpoint documents,
 * or when the request is answered with HTTP 429 or any other non-2xx status.
 */
class UnexpectedStatusException extends BookurierException
{
    public function __construct(
        public readonly ?ResponseInterface $response = null,
        string $message = '',
        int $code = 0,
        public readonly ?int $retryAfter = null,
        public readonly ?BookurierError $error = null,
    ) {
        if ($message === '') {
            $message = $response === null
                ? 'Unexpected HTTP status'
                : "Unexpected HTTP status {$response->getStatusCode()}";
        }

        parent::__construct($message, $response?->getStatusCode() ?? $code);
    }
}
