<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Exceptions;

/**
 * Thrown when Bookurier answers a negative error code (`-1`, `-2`, …) or a non-empty `<err>`.
 */
final class ApiException extends BookurierException {}
