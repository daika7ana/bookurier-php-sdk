<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Order;

use Daika7ana\Bookurier\DTOs\Order\HistoryEvent;
use Daika7ana\Bookurier\Enums\Status;
use PHPUnit\Framework\TestCase;

final class HistoryEventTest extends TestCase
{
    public function testFromArrayMapsTheVendorFields(): void
    {
        $event = HistoryEvent::fromArray([
            'data' => '14.07.2023',
            'status_id' => '2',
            'status_name' => 'Receptionata',
            'obs' => '0',
            'sort_date' => '2023-07-14 11:44:12',
        ]);

        self::assertSame('14.07.2023', $event->date);
        self::assertSame('2', $event->statusId);
        self::assertSame('Receptionata', $event->statusName);
        self::assertSame('0', $event->obs);
        self::assertSame('2023-07-14 11:44:12', $event->sortDate);
        self::assertSame(Status::Received, $event->status());
    }

    public function testAnEmptyStatusIdBecomesNull(): void
    {
        $event = HistoryEvent::fromArray([
            'data' => '16.07.2023',
            'status_id' => '',
            'status_name' => 'In curs de livrare la curier partener',
            'obs' => '',
            'sort_date' => '2023-07-16 08:00:00',
        ]);

        self::assertNull($event->statusId);
        self::assertNull($event->status());
        self::assertSame('In curs de livrare la curier partener', $event->statusName);
    }

    public function testUnknownStatusIdsDoNotResolve(): void
    {
        $event = HistoryEvent::fromArray(['status_id' => '999']);

        self::assertSame('999', $event->statusId);
        self::assertNull($event->status());

        self::assertNull(HistoryEvent::fromArray(['status_id' => 'SIGURAT 12'])->status());
    }
}
