<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

/**
 * An AWB label, daily bordero or pickup bordero exactly as Bookurier returned it.
 */
final readonly class Document
{
    public function __construct(
        public string $contents,
        public string $mimeType,
    ) {}

    public function isPdf(): bool
    {
        return str_starts_with(ltrim($this->contents), '%PDF');
    }
}
