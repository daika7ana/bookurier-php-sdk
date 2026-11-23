<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\Support\Document;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live read-only checks for the account-wide daily documents.
 */
#[Group('smoke')]
final class ReportsSmokeTest extends SmokeTestCase
{
    public function testDailyBorderoReturnsDocument(): void
    {
        $document = $this->client()->reports()->dailyBordero();

        $this->assertInstanceOf(Document::class, $document);
        $this->assertStringNotContainsString('One moment', $document->contents);
    }

    public function testDailyLabelsReturnDocument(): void
    {
        $document = $this->client()->labels()->daily();

        $this->assertInstanceOf(Document::class, $document);
        $this->assertStringNotContainsString('One moment', $document->contents);
    }
}
