<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Smoke;

use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\SimpleCollections\Collection;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * Live write check. Skipped unless BOOKURIER_TEST_USERNAME,
 * BOOKURIER_TEST_PASSWORD and BOOKURIER_TEST_CLIENT_ID are set; writes
 * additionally need BOOKURIER_TEST_ALLOW_WRITES=1.
 */
#[Group('smoke')]
final class ClientSmokeTest extends SmokeTestCase
{
    public function testCreateStatusPdfDelete(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueId = 'smoke-' . bin2hex(random_bytes(4));
        $awb = null;

        try {
            $awb = $client->orders()->create($this->order($uniqueId));
            $this->assertMatchesRegularExpression('/^[A-Za-z]{0,4}\d{3,}$/', $awb);
            $this->pause();

            $byAwb = $client->orders()->status($awb);
            $this->assertSame($awb, $byAwb->awb);
            $this->assertNotSame('', $byAwb->statusDescription);
            $this->pause();

            $byUniqueId = $client->orders()->statusByUniqueId($uniqueId);
            $this->assertSame($uniqueId, $byUniqueId->uniqueId);
            $this->assertSame($awb, $byUniqueId->awb);
            $this->pause();

            $history = $client->orders()->historyByUniqueId($uniqueId);
            $this->assertInstanceOf(Collection::class, $history);
            foreach ($history as $entry) {
                $this->assertSame($uniqueId, $entry->uniqueId);
                $this->assertSame($awb, $entry->awb);
            }
            $this->pause();

            $this->assertTrue($client->labels()->pdf($awb)->isPdf());
            $this->pause();

            $this->assertTrue($client->labels()->pdfByUniqueId($uniqueId)->isPdf());
        } finally {
            if ($awb !== null) {
                $this->pause();
                $client->orders()->delete($awb);
            }
        }
    }

    public function testCreateBatchAndDelete(): void
    {
        $this->requireWriteAccess();

        $client = $this->client();
        $uniqueIds = [
            'smoke-' . bin2hex(random_bytes(4)),
            'smoke-' . bin2hex(random_bytes(4)),
        ];
        $awbs = [];

        try {
            $references = $client->orders()->createBatch([
                $this->order($uniqueIds[0]),
                $this->order($uniqueIds[1]),
            ]);

            $items = $references->all();
            $awbs = array_map(
                static fn(OrderReference $reference): string => $reference->awb,
                $items,
            );
            $this->assertCount(2, $awbs);

            foreach ($awbs as $index => $awb) {
                $this->assertMatchesRegularExpression('/^[A-Za-z]{0,4}\d{3,}$/', $awb);
                $this->assertSame($uniqueIds[$index], $items[$index]->uniqueId);
            }

            $this->pause();

            $status = $client->orders()->statusByUniqueId($uniqueIds[1]);
            $this->assertSame($awbs[1], $status->awb);
        } finally {
            $this->deleteAll($awbs);
        }
    }

    /**
     * @param list<string> $awbs
     */
    private function deleteAll(array $awbs): void
    {
        $failure = null;

        foreach ($awbs as $awb) {
            $this->pause();

            try {
                $this->client()->orders()->delete($awb);
            } catch (Throwable $e) {
                $failure ??= $e;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
