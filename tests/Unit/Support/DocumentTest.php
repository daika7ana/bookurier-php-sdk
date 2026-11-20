<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Support\Document;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    public function testIsPdfToleratesLeadingWhitespace(): void
    {
        foreach (["%PDF-1.4", "\n%PDF-1.4", "  \n\t%PDF-1.7"] as $contents) {
            self::assertTrue((new Document($contents, 'application/pdf'))->isPdf());
        }
    }

    public function testIsPdfIsFalseForHtml(): void
    {
        self::assertFalse((new Document('<!DOCTYPE html><html></html>', 'text/html'))->isPdf());
    }
}
