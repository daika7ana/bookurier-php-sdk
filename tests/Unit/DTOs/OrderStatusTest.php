<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs;

use Daika7ana\Bookurier\DTOs\Order\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $status = OrderStatus::fromArray([
            'unq' => 'A4343244',
            'awb' => '000112234242',
            'status_id' => '5',
            'status_desc' => 'Livrata',
            'obs' => 'Livrat la destinatar',
        ]);

        self::assertSame('A4343244', $status->uniqueId);
        self::assertSame('000112234242', $status->awb);
        self::assertSame(5, $status->statusId);
        self::assertSame('Livrata', $status->statusDescription);
        self::assertSame('Livrat la destinatar', $status->observations);
    }

    public function testMissingKeysFallBackToTheDocumentedDefaults(): void
    {
        $status = OrderStatus::fromArray([]);

        self::assertSame('', $status->uniqueId);
        self::assertSame('', $status->awb);
        self::assertSame(0, $status->statusId);
        self::assertSame('', $status->statusDescription);
        self::assertNull($status->observations);
    }

    public function testBlankObservationsBecomeNull(): void
    {
        self::assertNull(OrderStatus::fromArray(['obs' => ''])->observations);
    }
}
