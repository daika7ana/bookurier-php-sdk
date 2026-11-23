<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Support\Document;
use Daika7ana\SimpleCollections\Collection;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live read-only checks against an order that already exists on the account.
 * Set BOOKURIER_TEST_AWB and/or BOOKURIER_TEST_UNIQUE_ID; the matching tests
 * skip when their fixture is missing.
 */
#[Group('smoke')]
final class OrderLookupSmokeTest extends SmokeTestCase
{
    public function testStatusByAwb(): void
    {
        $awb = $this->existingAwb();

        $status = $this->client()->orders()->status($awb);

        $this->assertSame($awb, $status->awb);
        $this->assertNotSame('', $status->statusDescription);
    }

    public function testStatusByUniqueId(): void
    {
        $uniqueId = $this->existingUniqueId();

        $status = $this->client()->orders()->statusByUniqueId($uniqueId);

        $this->assertSame($uniqueId, $status->uniqueId);
        $this->assertNotSame('', $status->awb);
    }

    public function testHistoryByUniqueId(): void
    {
        $uniqueId = $this->existingUniqueId();

        $history = $this->client()->orders()->historyByUniqueId($uniqueId);

        $this->assertInstanceOf(Collection::class, $history);
        foreach ($history as $entry) {
            $this->assertSame($uniqueId, $entry->uniqueId);
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

    public function testLabelHtmlByUniqueId(): void
    {
        $document = $this->client()->labels()->htmlByUniqueId($this->existingUniqueId());

        $this->assertInstanceOf(Document::class, $document);
        $this->assertNotSame('', trim($document->contents));
    }
}
