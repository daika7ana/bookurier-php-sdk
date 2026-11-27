<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\DTOs\Order;

use Daika7ana\SimpleCollections\Collection;

/**
 * Outcome of add_cmds.php with several orders: successes and per-order failures.
 */
final readonly class BatchResult
{
    /**
     * @param Collection<int, OrderReference> $references
     * @param Collection<int, OrderError> $errors
     */
    public function __construct(
        private Collection $references,
        private Collection $errors,
    ) {}

    /**
     * @return Collection<int, OrderReference>
     */
    public function references(): Collection
    {
        return $this->references;
    }

    /**
     * @return Collection<int, OrderError>
     */
    public function errors(): Collection
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors->count() > 0;
    }
}
