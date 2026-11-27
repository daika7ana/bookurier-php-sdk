<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Common\Errors;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use PHPUnit\Framework\TestCase;

final class BookurierErrorTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $raw = [
            'code' => 7,
            'message' => 'Judet invalid',
            'id' => 'err-1',
            'context' => 'orders',
            'component' => 'add_cmds',
        ];

        $error = BookurierError::fromArray($raw);

        self::assertSame(7, $error->code);
        self::assertSame('Judet invalid', $error->message);
        self::assertSame('err-1', $error->id);
        self::assertSame($raw, $error->raw);
        self::assertSame('orders', $error->context);
        self::assertSame('add_cmds', $error->component);
    }

    public function testFieldsWithoutASourceKeepTheirDefaults(): void
    {
        $error = BookurierError::fromArray(['message' => 'Judet invalid']);

        self::assertSame(0, $error->code);
        self::assertSame('Judet invalid', $error->message);
        self::assertSame('', $error->id);
        self::assertSame(['message' => 'Judet invalid'], $error->raw);
        self::assertNull($error->context);
        self::assertNull($error->component);
    }

    public function testFallbackCodeIsUsedAndRawIsPreserved(): void
    {
        $error = BookurierError::fromArray(['message' => 'Rejected'], 5);

        self::assertSame(5, $error->code);
        self::assertSame(['message' => 'Rejected'], $error->raw);
    }
}
