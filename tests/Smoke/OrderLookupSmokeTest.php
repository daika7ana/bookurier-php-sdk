<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Support\Document;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live read-only checks against an AWB that already exists on the account.
 * Set BOOKURIER_TEST_AWB; the tests skip when it is missing.
 */
#[Group('smoke')]
final class OrderLookupSmokeTest extends SmokeTestCase
{
    public function testHistoryByAwb(): void
    {
        $awb = $this->existingAwb();

        $history = $this->client()->orders()->history($awb);

        $this->assertGreaterThan(0, $history->count());
        foreach ($history as $event) {
            $this->assertNotSame('', $event->statusName);
        }
    }

    public function testLabelHtmlByAwb(): void
    {
        $document = $this->client()->labels()->html($this->existingAwb());

        $this->assertInstanceOf(Document::class, $document);
        $this->assertNotSame('', trim($document->contents));
    }

    public function testLabelPdfByAwb(): void
    {
        $this->assertTrue($this->client()->labels()->pdf($this->existingAwb())->isPdf());
    }
}
