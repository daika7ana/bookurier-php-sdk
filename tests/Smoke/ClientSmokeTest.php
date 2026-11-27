<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\SimpleCollections\Collection;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live write checks. There is no delete endpoint: an unscanned AWB is simply
 * not billed, so the test creates, reads, and leaves the AWB in place.
 */
#[Group('smoke')]
final class ClientSmokeTest extends SmokeTestCase
{
    public function testCreateHistoryAndLabels(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueId = 'smoke-' . bin2hex(random_bytes(4));

        $awb = $client->orders()->create($this->order($uniqueId));
        $this->assertMatchesRegularExpression('/^[A-Za-z]{0,4}\d{3,}$/', $awb);
        $this->pause();

        // Live quirk: a just-created AWB has no tracking events until the parcel is scanned.
        $history = $client->orders()->history($awb);
        $this->assertInstanceOf(Collection::class, $history);
        if ($history->count() > 0) {
            $this->assertNotSame('', $history->all()[$history->count() - 1]->statusName);
        }
        $this->pause();

        $this->assertTrue($client->labels()->pdf($awb)->isPdf());
        $this->pause();

        $this->assertNotSame('', trim($client->labels()->html($awb)->contents));
    }

    public function testCreateBatchMapsReferences(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueIds = [
            'smoke-' . bin2hex(random_bytes(4)),
            'smoke-' . bin2hex(random_bytes(4)),
        ];

        $result = $client->orders()->createBatch([
            $this->order($uniqueIds[0]),
            $this->order($uniqueIds[1]),
        ]);

        $references = $result->references()->all();
        $this->assertCount(2, $references);
        foreach ($references as $index => $reference) {
            $this->assertInstanceOf(OrderReference::class, $reference);
            $this->assertSame($uniqueIds[$index], $reference->uniqueId);
            $this->assertMatchesRegularExpression('/^[A-Za-z]{0,4}\d{3,}$/', $reference->awb);
        }

        $this->pause();

        $history = $client->orders()->history($references[0]->awb);
        $this->assertInstanceOf(Collection::class, $history);
    }
}
