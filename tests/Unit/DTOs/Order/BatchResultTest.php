<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Order;

use Daika7ana\Bookurier\DTOs\Order\BatchResult;
use Daika7ana\Bookurier\DTOs\Order\OrderError;
use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\SimpleCollections\Collection;
use PHPUnit\Framework\TestCase;

final class BatchResultTest extends TestCase
{
    public function testExposesReferencesAndErrors(): void
    {
        $reference = new OrderReference('CMD-10001', 'BOK6000123456');
        $error = new OrderError('CMD-10002', 'Judet invalid');

        $result = new BatchResult(
            new Collection([$reference]),
            new Collection([$error]),
        );

        self::assertCount(1, $result->references());
        self::assertSame($reference, $result->references()->all()[0]);
        self::assertCount(1, $result->errors());
        self::assertSame($error, $result->errors()->all()[0]);
        self::assertTrue($result->hasErrors());
    }

    public function testHasErrorsIsFalseWithCleanErrors(): void
    {
        $result = new BatchResult(new Collection([new OrderReference('CMD-10001', 'BOK6000123456')]), new Collection([]));

        self::assertFalse($result->hasErrors());
    }
}
