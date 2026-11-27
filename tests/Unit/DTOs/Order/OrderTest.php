<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Order;

use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testToArrayMapsEveryFieldToTheWireName(): void
    {
        $order = new Order(
            uniqueId: 'CMD-10001',
            recipientName: 'Ion Popescu',
            phone: '0744555555',
            city: 'Deva',
            street: 'Unirii',
            service: ServiceType::National24,
            weight: 1.5,
            packages: 1,
            pickupPoint: '5',
            country: 'Romania',
            zip: '330020',
            district: 'Hunedoara',
            email: 'ion@exemplu.ro',
            number: '1',
            block: 'A',
            entrance: '1',
            floor: '2',
            apartment: '3',
            interphone: '4',
            cashOnDelivery: 200.65,
            insuredValue: 0.0,
            returnDocument: true,
            weekend: true,
            unpack: true,
            exchangePackage: true,
            confirmation: true,
            notes: 'De sunat inainte',
            ref1: 'ref-1',
            ref2: 'ref-2',
        );

        // pickup_point belongs to OrdersResource, not the order payload.
        self::assertSame([
            'unq' => 'CMD-10001',
            'recv' => 'Ion Popescu',
            'phone' => '0744555555',
            'email' => 'ion@exemplu.ro',
            'country' => 'Romania',
            'district' => 'Hunedoara',
            'city' => 'Deva',
            'zip' => '330020',
            'street' => 'Unirii',
            'no' => '1',
            'bl' => 'A',
            'ent' => '1',
            'floor' => '2',
            'apt' => '3',
            'interphone' => '4',
            'service' => 9,
            'packs' => 1,
            'weight' => 1.5,
            'rbs_val' => 200.65,
            'insurance_val' => 0.0,
            'ret_doc' => 1,
            'weekend' => 1,
            'unpack' => 1,
            'exchange_pack' => 1,
            'confirmation' => 1,
            'notes' => 'De sunat inainte',
            'ref1' => 'ref-1',
            'ref2' => 'ref-2',
        ], $order->toArray());
    }

    public function testToArrayOmitsNullOptionals(): void
    {
        $order = new Order(
            uniqueId: 'CMD-10001',
            recipientName: 'Ion Popescu',
            phone: '0744555555',
            city: 'Deva',
            street: 'Unirii',
            service: ServiceType::National24,
            weight: 1.5,
            district: 'Hunedoara',
        );

        self::assertSame([
            'unq' => 'CMD-10001',
            'recv' => 'Ion Popescu',
            'phone' => '0744555555',
            'country' => 'Romania',
            'district' => 'Hunedoara',
            'city' => 'Deva',
            'street' => 'Unirii',
            'service' => 9,
            'packs' => 1,
            'weight' => 1.5,
            'ret_doc' => 0,
            'weekend' => 0,
            'unpack' => 0,
            'exchange_pack' => 0,
            'confirmation' => 0,
        ], $order->toArray());
    }

    public function testToArrayKeepsZeroValues(): void
    {
        $order = new Order(
            uniqueId: 'CMD-10001',
            recipientName: 'Ion Popescu',
            phone: '0744555555',
            city: 'Deva',
            street: 'Unirii',
            service: ServiceType::National24,
            weight: 1.5,
            district: 'Hunedoara',
            cashOnDelivery: 0.0,
            insuredValue: 0.0,
        );

        $array = $order->toArray();

        self::assertSame(0.0, $array['rbs_val']);
        self::assertSame(0.0, $array['insurance_val']);
    }
}
