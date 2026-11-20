<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs;

use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\Enums\ServiceType;
use DOMDocument;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testToXmlEmitsEveryFieldInVendorOrder(): void
    {
        $xml = $this->order()->toXml();

        self::assertSame(
            [
                'client', 'unq', 'recv', 'phone', 'email', 'country', 'city', 'zip',
                'district', 'street', 'no', 'bl', 'ent', 'floor', 'apt', 'interphone',
                'service', 'packs', 'exchange_pack', 'weight', 'rbs_val', 'confirmation',
                'ret_doc', 'ret_doc_desc', 'insurance_val', 'notes',
            ],
            $this->tags($xml),
        );
    }

    public function testToXmlMapsPropertiesToVendorTags(): void
    {
        $values = $this->values($this->order()->toXml());

        self::assertSame('9999', $values['client']);
        self::assertSame('UNQ-1', $values['unq']);
        self::assertSame('Ion Popescu', $values['recv']);
        self::assertSame('0712345678', $values['phone']);
        self::assertSame('ion@example.com', $values['email']);
        self::assertSame('Romania', $values['country']);
        self::assertSame('Bucharest', $values['city']);
        self::assertSame('010101', $values['zip']);
        self::assertSame('Sector 1', $values['district']);
        self::assertSame('Piața Unirii', $values['street']);
        self::assertSame('10', $values['no']);
        self::assertSame('A', $values['bl']);
        self::assertSame('1', $values['ent']);
        self::assertSame('3', $values['floor']);
        self::assertSame('12', $values['apt']);
        self::assertSame('42', $values['interphone']);
        self::assertSame('2', $values['service']);
        self::assertSame('2', $values['packs']);
        self::assertSame('1', $values['exchange_pack']);
        self::assertSame('2.5', $values['weight']);
        self::assertSame('100.5', $values['rbs_val']);
        self::assertSame('1', $values['confirmation']);
        self::assertSame('1', $values['ret_doc']);
        self::assertSame('Retur', $values['ret_doc_desc']);
        self::assertSame('250', $values['insurance_val']);
        self::assertSame('Sună înainte', $values['notes']);
    }

    public function testNullOptionalsAreOmitted(): void
    {
        $xml = $this->minimalOrder()->toXml(1234);

        self::assertSame(
            ['client', 'unq', 'recv', 'phone', 'country', 'city', 'street', 'no', 'service', 'packs', 'exchange_pack', 'weight', 'confirmation', 'ret_doc'],
            $this->tags($xml),
        );
    }

    public function testBooleansSerializeAsOneAndZero(): void
    {
        $values = $this->values($this->minimalOrder()->toXml());

        self::assertSame('0', $values['exchange_pack']);
        self::assertSame('0', $values['confirmation']);
        self::assertSame('0', $values['ret_doc']);
    }

    public function testTextIsEscapedAndStillParsesBackToTheOriginal(): void
    {
        $notes = 'Sector 1 & "Piața" <test> \'quote\'';
        $xml = (new Order(
            uniqueId: 'UNQ&<>"1',
            recipientName: 'Popescu & Fiii <SRL>',
            phone: '0712345678',
            city: 'București',
            street: 'Calea Ștefan cel Mare',
            number: '10',
            service: ServiceType::Bucharest24h,
            weight: 1.0,
            notes: $notes,
        ))->toXml();

        self::assertStringContainsString('&amp;', $xml);
        self::assertStringContainsString('&lt;test&gt;', $xml);
        self::assertStringContainsString('&quot;', $xml);

        $values = $this->values($xml);
        self::assertSame('UNQ&<>"1', $values['unq']);
        self::assertSame('Popescu & Fiii <SRL>', $values['recv']);
        self::assertSame('București', $values['city']);
        self::assertSame('Calea Ștefan cel Mare', $values['street']);
        self::assertSame($notes, $values['notes']);
    }

    public function testExplicitClientCodeWinsOverTheDefault(): void
    {
        self::assertSame('9999', $this->values($this->order()->toXml(1234))['client']);
    }

    public function testDefaultClientCodeIsUsedWhenTheOrderHasNone(): void
    {
        self::assertSame('1234', $this->values($this->minimalOrder()->toXml(1234))['client']);
    }

    public function testClientElementIsAbsentWithoutAnyClientCode(): void
    {
        self::assertArrayNotHasKey('client', $this->values($this->minimalOrder()->toXml()));
    }

    public function testOutputIsWellFormedXml(): void
    {
        $document = new DOMDocument();

        self::assertTrue($document->loadXML($this->order()->toXml(), LIBXML_NONET));
        self::assertSame('cmd', $document->documentElement?->nodeName);
    }

    private function order(): Order
    {
        return new Order(
            uniqueId: 'UNQ-1',
            recipientName: 'Ion Popescu',
            phone: '0712345678',
            city: 'Bucharest',
            street: 'Piața Unirii',
            number: '10',
            service: ServiceType::BucharestExpress,
            weight: 2.5,
            packages: 2,
            country: 'Romania',
            zip: '010101',
            district: 'Sector 1',
            email: 'ion@example.com',
            block: 'A',
            entrance: '1',
            floor: '3',
            apartment: '12',
            interphone: '42',
            exchangePackage: true,
            cashOnDelivery: 100.5,
            confirmation: true,
            returnDocument: true,
            returnDocumentDescription: 'Retur',
            insuredValue: 250.0,
            notes: 'Sună înainte',
            clientCode: 9999,
        );
    }

    private function minimalOrder(): Order
    {
        return new Order(
            uniqueId: 'UNQ-2',
            recipientName: 'Ion Popescu',
            phone: '0712345678',
            city: 'Bucharest',
            street: 'Piața Unirii',
            number: '10',
            service: ServiceType::Bucharest24h,
            weight: 1.0,
        );
    }

    /**
     * @return list<string>
     */
    private function tags(string $xml): array
    {
        $tags = [];

        foreach ($this->load($xml)->documentElement?->childNodes ?? [] as $child) {
            if ($child instanceof \DOMElement) {
                $tags[] = $child->nodeName;
            }
        }

        return $tags;
    }

    /**
     * @return array<string, string>
     */
    private function values(string $xml): array
    {
        $values = [];

        foreach ($this->load($xml)->documentElement?->childNodes ?? [] as $child) {
            if ($child instanceof \DOMElement) {
                $values[$child->nodeName] = $child->textContent;
            }
        }

        return $values;
    }

    private function load(string $xml): DOMDocument
    {
        $document = new DOMDocument();

        self::assertTrue($document->loadXML($xml, LIBXML_NONET));

        return $document;
    }
}
