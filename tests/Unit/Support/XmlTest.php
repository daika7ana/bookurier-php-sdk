<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Support\Xml;
use PHPUnit\Framework\TestCase;

final class XmlTest extends TestCase
{
    public function testEscapeHandlesSpecialCharactersAndDiacritics(): void
    {
        $xml = Xml::cmd(['notes' => 'Sector 1 & "Piața" <test> \'quote\'']);

        self::assertSame(
            '<cmd><notes>Sector 1 &amp; &quot;Piața&quot; &lt;test&gt; &apos;quote&apos;</notes></cmd>',
            $xml,
        );
    }

    public function testNullFieldsAreOmitted(): void
    {
        $xml = Xml::cmd(['awb' => '123', 'obs' => null, 'notes' => null]);

        self::assertSame('<cmd><awb>123</awb></cmd>', $xml);
    }

    public function testBooleansBecomeOneAndZero(): void
    {
        $xml = Xml::cmd(['confirmation' => true, 'ret_doc' => false]);

        self::assertSame('<cmd><confirmation>1</confirmation><ret_doc>0</ret_doc></cmd>', $xml);
    }

    public function testFieldOrderIsPreserved(): void
    {
        $xml = Xml::cmd(['b' => '2', 'a' => '1', 'c' => '3']);

        self::assertSame('<cmd><b>2</b><a>1</a><c>3</c></cmd>', $xml);
    }

    public function testNumbersAreCastToString(): void
    {
        $xml = Xml::cmd(['packs' => 3, 'weight' => 2.5, 'rbs_val' => 0]);

        self::assertSame('<cmd><packs>3</packs><weight>2.5</weight><rbs_val>0</rbs_val></cmd>', $xml);
    }

    public function testMessageWrapsInnerXml(): void
    {
        self::assertSame('<msg><cmd><awb>1</awb></cmd></msg>', Xml::message('<cmd><awb>1</awb></cmd>'));
    }
}
