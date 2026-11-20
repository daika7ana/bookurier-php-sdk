<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * Thrown when the HTTP transport fails; the original exception is the `previous` one.
 */
class TransportException extends BookurierException implements ClientExceptionInterface {}
