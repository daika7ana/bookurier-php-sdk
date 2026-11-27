<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Enums;

enum Status: int
{
    case NotPicked = -1;
    case NewParcel = 0;
    case NewOrder = 1;
    case Received = 2;
    case Assigned = 3;
    case Completed = 4;
    case CashOnDeliveryHandover = 5;
    case ReturnedToWarehouse = 6;
    case ReturnedToSender = 7;
    case Delayed = 8;
    case ReturnToSenderConfirmed = 9;
    case RefundPaid = 10;
    case Redirected = 11;
    case PickedUp = 12;
    case OutForDelivery = 14;
    case RedirectedByPartner = 15;
    case Collected = 16;
    case Compensation = 17;
    case Delivered = 18;
    case InTransit = 20;
    case ReturnWarehouse = 22;
    case DeliveryLine = 30;
    case DeliveryFailed = 33;
    case ReturnLine = 70;
    case ReturnFailed = 77;
    case ReturnCompleted = 99;

    public function isFinal(): bool
    {
        return in_array($this, [
            self::NotPicked,
            self::Completed,
            self::ReturnToSenderConfirmed,
            self::RefundPaid,
            self::Delivered,
            self::ReturnCompleted,
        ], true);
    }
}
