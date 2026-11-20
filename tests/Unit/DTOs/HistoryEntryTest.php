<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs;

use Daika7ana\Bookurier\DTOs\Order\HistoryEntry;
use PHPUnit\Framework\TestCase;

final class HistoryEntryTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $entry = HistoryEntry::fromArray([
            'unq' => 'A4343244',
            'awb' => '000112234242',
            'status_id' => '1',
            'status_desc' => 'Preluata',
            'date' => '2020-02-18 07:46:00',
            'obs' => 'Prima scanare',
        ]);

        self::assertSame('A4343244', $entry->uniqueId);
        self::assertSame('000112234242', $entry->awb);
        self::assertSame(1, $entry->statusId);
        self::assertSame('Preluata', $entry->statusDescription);
        self::assertSame('2020-02-18 07:46:00', $entry->date);
        self::assertSame('Prima scanare', $entry->observations);
    }

    public function testMissingKeysFallBackToTheDocumentedDefaults(): void
    {
        $entry = HistoryEntry::fromArray([]);

        self::assertSame('', $entry->uniqueId);
        self::assertSame('', $entry->awb);
        self::assertSame(0, $entry->statusId);
        self::assertSame('', $entry->statusDescription);
        self::assertNull($entry->date);
        self::assertNull($entry->observations);
    }

    public function testBlankDateAndObservationsBecomeNull(): void
    {
        $entry = HistoryEntry::fromArray(['date' => '', 'obs' => '']);

        self::assertNull($entry->date);
        self::assertNull($entry->observations);
    }
}
