<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Common\Errors;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookurierErrorTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $raw = [
            'code' => -2,
            'message' => 'AWB inexistent',
            'id' => 'err-1',
            'context' => 'orders',
            'component' => 'get_stat',
        ];

        $error = BookurierError::fromArray($raw);

        self::assertSame(-2, $error->code);
        self::assertSame('AWB inexistent', $error->message);
        self::assertSame('err-1', $error->id);
        self::assertSame($raw, $error->raw);
        self::assertSame('orders', $error->context);
        self::assertSame('get_stat', $error->component);
    }

    public function testFieldsWithoutASourceKeepTheirDefaults(): void
    {
        $error = BookurierError::fromArray(['error' => 'AWB inexistent']);

        self::assertSame(0, $error->code);
        self::assertSame('AWB inexistent', $error->message);
        self::assertSame('', $error->id);
        self::assertSame(['error' => 'AWB inexistent'], $error->raw);
        self::assertNull($error->context);
        self::assertNull($error->component);
    }

    public function testFallbackCodeIsUsedAndRawIsPreserved(): void
    {
        $error = BookurierError::fromArray(['message' => 'Rejected'], -5);

        self::assertSame(-5, $error->code);
        self::assertSame(['message' => 'Rejected'], $error->raw);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validationCodes(): iterable
    {
        yield '-2' => [-2];
        yield '-3' => [-3];
        yield '-5' => [-5];
    }

    #[DataProvider('validationCodes')]
    public function testKnownCodesAreValidationRelated(int $code): void
    {
        self::assertTrue(BookurierError::fromArray(['code' => $code, 'message' => 'Rejected'])->isValidationRelated());
    }

    public function testMinusOneIsAuthenticationRelated(): void
    {
        $error = BookurierError::fromArray(['code' => -1, 'message' => 'Rejected']);

        self::assertTrue($error->isAuthenticationRelated());
        self::assertFalse($error->isValidationRelated());
    }

    public function testErrorMessageClassification(): void
    {
        self::assertTrue(BookurierError::fromArray(['message' => 'AWB inexistent'])->isValidationRelated());
        self::assertTrue(BookurierError::fromArray(['message' => 'Invalid credentials'])->isAuthenticationRelated());
        self::assertFalse(BookurierError::fromArray(['message' => 'Unexpected'])->isValidationRelated());
        self::assertFalse(BookurierError::fromArray(['message' => 'Unexpected'])->isAuthenticationRelated());
    }
}
