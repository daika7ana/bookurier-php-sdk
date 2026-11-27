<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\DTOs\Order;

use Daika7ana\Bookurier\DTOs\Order\OrderError;
use PHPUnit\Framework\TestCase;

final class OrderErrorTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $error = OrderError::fromArray([
            'order' => 'CMD-10002',
            'message' => 'Judet invalid',
            'suggestion' => 'Verificati judetul.',
        ]);

        self::assertSame('CMD-10002', $error->order);
        self::assertSame('Judet invalid', $error->message);
        self::assertSame('Verificati judetul.', $error->suggestion);
    }

    public function testMissingFieldsFallBackToDefaults(): void
    {
        $error = OrderError::fromArray(['order' => 'CMD-10002']);

        self::assertSame('', $error->message);
        self::assertNull($error->suggestion);
    }
}
