<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use Daika7ana\Bookurier\Support\XmlResponse;
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
        $this->expectException(UnexpectedResponseException::class);

        XmlResponse::from('  ');
    }

    public function testFromRejectsInvalidXml(): void
    {
        try {
            XmlResponse::from('<msg><cmd>');

            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $e) {
            self::assertStringContainsString('Expected an XML response.', $e->getMessage());
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
        $this->expectException(UnexpectedResponseException::class);

        XmlResponse::from('<msg><err></err></msg>')->cmd();
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
        $this->expectException(UnexpectedResponseException::class);

        XmlResponse::from('<msg><err></err></msg>')->commands();
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
