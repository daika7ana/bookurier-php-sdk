<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\Document;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live read-only checks for the daily bordero.
 */
#[Group('smoke')]
final class ReportsSmokeTest extends SmokeTestCase
{
    public function testBorderouReturnsADocument(): void
    {
        try {
            $document = $this->client()->reports()->borderou(date('Y-m-d'));
        } catch (ValidationException $exception) {
            // 'No data' is the documented outcome for a day without a dispatch.
            $this->assertSame('No data', $exception->getMessage());

            return;
        }

        $this->assertInstanceOf(Document::class, $document);
        $this->assertStringNotContainsString('One moment', $document->contents);
    }
}
