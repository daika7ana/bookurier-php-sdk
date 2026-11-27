<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Enums;

use Daika7ana\Bookurier\Enums\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    /**
     * @return iterable<string, array{int, Status}>
     */
    public static function statuses(): iterable
    {
        yield 'not picked' => [-1, Status::NotPicked];
        yield 'new parcel' => [0, Status::NewParcel];
        yield 'new order' => [1, Status::NewOrder];
        yield 'received' => [2, Status::Received];
        yield 'assigned' => [3, Status::Assigned];
        yield 'completed' => [4, Status::Completed];
        yield 'cod handover' => [5, Status::CashOnDeliveryHandover];
        yield 'returned to warehouse' => [6, Status::ReturnedToWarehouse];
        yield 'returned to sender' => [7, Status::ReturnedToSender];
        yield 'delayed' => [8, Status::Delayed];
        yield 'return confirmed' => [9, Status::ReturnToSenderConfirmed];
        yield 'refund paid' => [10, Status::RefundPaid];
        yield 'redirected' => [11, Status::Redirected];
        yield 'picked up' => [12, Status::PickedUp];
        yield 'out for delivery' => [14, Status::OutForDelivery];
        yield 'redirected by partner' => [15, Status::RedirectedByPartner];
        yield 'collected' => [16, Status::Collected];
        yield 'compensation' => [17, Status::Compensation];
        yield 'delivered' => [18, Status::Delivered];
        yield 'in transit' => [20, Status::InTransit];
        yield 'return warehouse' => [22, Status::ReturnWarehouse];
        yield 'delivery line' => [30, Status::DeliveryLine];
        yield 'delivery failed' => [33, Status::DeliveryFailed];
        yield 'return line' => [70, Status::ReturnLine];
        yield 'return failed' => [77, Status::ReturnFailed];
        yield 'return completed' => [99, Status::ReturnCompleted];
    }

    #[DataProvider('statuses')]
    public function testEveryVendorIdMapsToItsCase(int $id, Status $expected): void
    {
        self::assertSame($expected, Status::from($id));
    }

    /**
     * @return iterable<string, array{Status, bool}>
     */
    public static function finalFlags(): iterable
    {
        yield 'not picked' => [Status::NotPicked, true];
        yield 'completed' => [Status::Completed, true];
        yield 'return confirmed' => [Status::ReturnToSenderConfirmed, true];
        yield 'refund paid' => [Status::RefundPaid, true];
        yield 'delivered' => [Status::Delivered, true];
        yield 'return completed' => [Status::ReturnCompleted, true];
        yield 'received' => [Status::Received, false];
        yield 'in transit' => [Status::InTransit, false];
        yield 'returned to sender' => [Status::ReturnedToSender, false];
        yield 'delivery failed' => [Status::DeliveryFailed, false];
    }

    #[DataProvider('finalFlags')]
    public function testFinalFlags(Status $status, bool $expected): void
    {
        self::assertSame($expected, $status->isFinal());
    }
}
