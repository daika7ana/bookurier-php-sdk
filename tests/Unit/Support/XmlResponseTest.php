<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Support\XmlResponse;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class XmlResponseTest extends TestCase
{
    public function testFromToleratesUtf8Bom(): void
    {
        $xml = XmlResponse::from("\xEF\xBB\xBF<msg><cmd><awb>42</awb></cmd><err></err></msg>");

        self::assertSame(['awb' => '42'], $xml->cmd());
    }

    public function testFromRejectsEmptyBody(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        XmlResponse::from('  ');
    }

    public function testFromRejectsInvalidXml(): void
    {
        try {
            XmlResponse::from('<msg><cmd>');

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('Expected an XML response.', $e->getMessage());
        }
    }

    public function testShapeErrorsCarryTheResponse(): void
    {
        try {
            XmlResponse::from('<msg><cmd>', new Response(502));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertSame(502, $e->response?->getStatusCode());
            self::assertSame(502, $e->getCode());
        }
    }

    public function testErrorReturnsTrimmedText(): void
    {
        $xml = XmlResponse::from('<msg><err> Eroare </err></msg>');

        self::assertSame('Eroare', $xml->error());
    }

    public function testErrorIsNullWhenMissingOrEmpty(): void
    {
        self::assertNull(XmlResponse::from('<msg><cmd/></msg>')->error());
        self::assertNull(XmlResponse::from('<msg><err> </err></msg>')->error());
    }

    public function testCmdFlattensAndTrimsTheFirstCommand(): void
    {
        $xml = XmlResponse::from('<msg><cmd><unq> A1 </unq><awb>0001</awb></cmd></msg>');

        self::assertSame(['unq' => 'A1', 'awb' => '0001'], $xml->cmd());
    }

    public function testCmdThrowsWhenNoCommandExists(): void
    {
        try {
            XmlResponse::from('<msg><err></err></msg>')->cmd();

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('Response XML has no <cmd> element.', $e->getMessage());
            self::assertStringContainsString('Got: "<msg><err></err></msg>".', $e->getMessage());
        }
    }

    public function testCommandsFlattensEveryCommand(): void
    {
        $xml = XmlResponse::from(
            '<msg><cmds><cmd><unq>A1</unq><awb>0001</awb></cmd>'
            . '<cmd><unq>A2</unq><awb>0002</awb></cmd></cmds></msg>',
        );

        self::assertSame(
            [
                ['unq' => 'A1', 'awb' => '0001'],
                ['unq' => 'A2', 'awb' => '0002'],
            ],
            $xml->commands(),
        );
    }

    public function testCommandsThrowsWhenNoCommandExists(): void
    {
        try {
            XmlResponse::from('<msg><err></err></msg>')->commands();

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('Response XML has no <cmd> elements.', $e->getMessage());
            self::assertStringContainsString('Got: "<msg><err></err></msg>".', $e->getMessage());
        }
    }

    public function testTextReturnsTheTrimmedValue(): void
    {
        self::assertSame('BOK0001,BOK0002', XmlResponse::from('<msg><cmds> BOK0001,BOK0002 </cmds></msg>')->text('cmds'));
    }

    public function testTextIsNullWhenTheElementIsMissing(): void
    {
        self::assertNull(XmlResponse::from('<msg><err></err></msg>')->text('cmds'));
    }

    public function testTextIsNullWhenTheElementIsEmpty(): void
    {
        self::assertNull(XmlResponse::from('<msg><cmds> </cmds></msg>')->text('cmds'));
    }
}
