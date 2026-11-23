<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\HistoryEntry;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\Bookurier\DTOs\Order\OrderStatus;
use Daika7ana\Bookurier\Enums\ServiceType;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Tests\Support\FakeHttpClient;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class OrdersResourceTest extends TestCase
{
    public function testCreatePostsTheOrderAndReturnsTheAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::awb());
        $order = $this->order(clientCode: 1234);

        $awb = $client->orders()->create($order);

        self::assertSame('BOK3925457', $awb);
        $this->assertRequest($httpClient, 'add_cmd.php');
        self::assertSame(
            '<msg><cmd><client>1234</client><unq>UNQ-1</unq><recv>Ion Popescu</recv><phone>0712345678</phone>'
            . '<country>Romania</country><city>Bucharest</city><district>Sect 1</district><street>Piața Unirii</street>'
            . '<no>10</no><service>2</service><packs>2</packs><exchange_pack>0</exchange_pack>'
            . '<weight>2.5</weight><confirmation>0</confirmation><ret_doc>0</ret_doc></cmd></msg>',
            $this->form($httpClient)['msg'],
        );
    }

    public function testCreateSendsTheConfiguredClientCode(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::awb(),
            new ClientConfig(userId: 'test-user', password: 'test-password', clientId: 1234),
        );

        $client->orders()->create($this->order());

        self::assertStringContainsString('<client>1234</client>', $this->form($httpClient)['msg']);
    }

    public function testCreatePrefersTheOrderClientCodeOverTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::awb(),
            new ClientConfig(userId: 'test-user', password: 'test-password', clientId: 1234),
        );

        $client->orders()->create($this->order(clientCode: 9999));

        $msg = $this->form($httpClient)['msg'];
        self::assertStringContainsString('<client>9999</client>', $msg);
        self::assertStringNotContainsString('<client>1234</client>', $msg);
    }

    public function testCreateBatchSendsTheConfiguredClientCode(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::batch(),
            new ClientConfig(userId: 'test-user', password: 'test-password', clientId: 1234),
        );

        $client->orders()->createBatch([$this->order(), $this->order(uniqueId: 'UNQ-2')]);

        self::assertSame(2, substr_count($this->form($httpClient)['msg'], '<client>1234</client>'));
    }

    public function testCreateRejectsANonNumericAwb(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('not-an-awb'));

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->create($this->order(clientCode: 1234));
    }

    public function testCreateBatchPostsAllOrdersAndMapsTheReferences(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batch());
        $first = $this->order(clientCode: 1234);
        $second = $this->order(uniqueId: 'UNQ-2', clientCode: 1234);

        $references = $client->orders()->createBatch([$first, $second]);

        $this->assertRequest($httpClient, 'add_cmds.php');
        self::assertSame(
            '<msg><cmds>' . $first->toXml() . $second->toXml() . '</cmds></msg>',
            $this->form($httpClient)['msg'],
        );

        self::assertCount(2, $references);
        $items = $references->all();
        self::assertInstanceOf(OrderReference::class, $items[0]);
        self::assertSame($first->uniqueId, $items[0]->uniqueId);
        self::assertSame('BOK0001', $items[0]->awb);
        self::assertSame($second->uniqueId, $items[1]->uniqueId);
        self::assertSame('BOK0002', $items[1]->awb);
    }

    public function testCreateBatchMapsMixedAwbShapesPositionally(): void
    {
        // Live batch of a Bucharest order (own network) and a Cluj order (partner route).
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::xml('<msg><cmds>BOK3925647,062568457893</cmds></msg>'),
        );

        $references = $client->orders()->createBatch([
            $this->order(clientCode: 1234),
            $this->order(uniqueId: 'UNQ-2', clientCode: 1234),
        ])->all();

        self::assertSame('BOK3925647', $references[0]->awb);
        self::assertSame('062568457893', $references[1]->awb);
    }

    public function testCreateBatchRejectsAnAwbCountMismatch(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batch());

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->createBatch([
            $this->order(clientCode: 1234),
            $this->order(uniqueId: 'UNQ-2', clientCode: 1234),
            $this->order(uniqueId: 'UNQ-3', clientCode: 1234),
        ]);
    }

    public function testCreateBatchRejectsAnEmptyList(): void
    {
        [$client] = TestClientFactory::create();

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->createBatch([]);
    }

    public function testDeletePostsTheAwbAndReturnsVoid(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::success());

        $client->orders()->delete('000112870071');

        $this->assertRequest($httpClient, 'del_cmd.php');
        self::assertSame('<msg><cmd><awb>000112870071</awb></cmd></msg>', $this->form($httpClient)['msg']);
    }

    public function testDeleteRejectsAnUnexpectedSuccessValue(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('0'));

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->delete('000112870071');
    }

    public function testDeleteByUniqueIdPostsTheUniqueId(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::success());

        $client->orders()->deleteByUniqueId('A4343244');

        $this->assertRequest($httpClient, 'del_cmd_unq.php');
        self::assertSame('<msg><cmd><unq>A4343244</unq></cmd></msg>', $this->form($httpClient)['msg']);
    }

    public function testStatusPostsTheAwbAndMapsTheStatus(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::status());

        $status = $client->orders()->status('000112234242');

        $this->assertRequest($httpClient, 'get_stat.php');
        self::assertSame('<msg><cmd><awb>000112234242</awb></cmd></msg>', $this->form($httpClient)['msg']);

        self::assertInstanceOf(OrderStatus::class, $status);
        self::assertSame('A4343244', $status->uniqueId);
        self::assertSame('000112234242', $status->awb);
        self::assertSame(5, $status->statusId);
        self::assertSame('Livrata', $status->statusDescription);
        self::assertNull($status->observations);
    }

    public function testStatusByUniqueIdPostsTheUniqueId(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::status());

        $status = $client->orders()->statusByUniqueId('A4343244');

        $this->assertRequest($httpClient, 'get_stat_unq.php');
        self::assertSame('<msg><cmd><unq>A4343244</unq></cmd></msg>', $this->form($httpClient)['msg']);
        self::assertSame('A4343244', $status->uniqueId);
    }

    public function testHistoryPostsTheAwbAndMapsEveryEntry(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::history());

        $history = $client->orders()->history('000112234242');

        $this->assertRequest($httpClient, 'get_history.php');
        self::assertSame('<msg><cmd><awb>000112234242</awb></cmd></msg>', $this->form($httpClient)['msg']);

        self::assertCount(2, $history);
        $entries = $history->all();
        self::assertInstanceOf(HistoryEntry::class, $entries[0]);
        self::assertSame('A4343244', $entries[0]->uniqueId);
        self::assertSame('000112234242', $entries[0]->awb);
        self::assertSame(1, $entries[0]->statusId);
        self::assertSame('Preluata', $entries[0]->statusDescription);
        self::assertSame('2020-02-18 07:46:00', $entries[0]->date);
        self::assertSame('Prima scanare', $entries[0]->observations);
        self::assertSame(5, $entries[1]->statusId);
        self::assertSame('Livrata', $entries[1]->statusDescription);
        self::assertNull($entries[1]->observations);
    }

    public function testHistoryByUniqueIdPostsTheUniqueId(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::history());

        $history = $client->orders()->historyByUniqueId('A4343244');

        $this->assertRequest($httpClient, 'get_history_unq.php');
        self::assertSame('<msg><cmd><unq>A4343244</unq></cmd></msg>', $this->form($httpClient)['msg']);
        self::assertCount(2, $history);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function negativeCodes(): iterable
    {
        yield '-2' => ['-2'];
        yield '-3' => ['-3'];
    }

    #[DataProvider('negativeCodes')]
    public function testNegativeCodesBecomeValidationExceptions(string $code): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response($code));

        try {
            $client->orders()->status('000112234242');

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertStringContainsString($code, $e->getMessage());
            self::assertSame((int) $code, $e->error?->code);
            self::assertNotNull($e->response);
        }
    }

    public function testMinusOneBecomesAnAuthenticationException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('-1'));

        $this->expectException(AuthenticationException::class);

        $client->orders()->status('000112234242');
    }

    public function testAnInBandErrorBecomesAValidationException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::error('AWB inexistent'));

        try {
            $client->orders()->history('000112234242');

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('AWB inexistent', $e->getMessage());
        }
    }

    public function testEmptyBodyBecomesAnUnexpectedStatusException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response(''));

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->create($this->order(clientCode: 1234));
    }

    public function testCreateBatchRejectsAnInBandError(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchError());

        try {
            $client->orders()->createBatch([$this->order(clientCode: 1234), $this->order(uniqueId: 'UNQ-2', clientCode: 1234)]);

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('Batch rejected', $e->getMessage());
        }
    }

    public function testCreateBatchRejectsAnErrorEvenWhenCmdsArePresent(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchWithError());

        $this->expectException(ValidationException::class);

        $client->orders()->createBatch([$this->order(clientCode: 1234), $this->order(uniqueId: 'UNQ-2', clientCode: 1234)]);
    }

    public function testCreateBatchRejectsAResponseWithoutCmds(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchWithoutCmds());

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->createBatch([$this->order(clientCode: 1234)]);
    }

    public function testCreateBatchRejectsAMalformedAwbInTheList(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::xml('<msg><cmds>BOK0001,not-an-awb</cmds><err></err></msg>'),
        );

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->createBatch([$this->order(clientCode: 1234), $this->order(uniqueId: 'UNQ-2', clientCode: 1234)]);
    }

    public function testCreateBatchRejectsDuplicateUniqueIds(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batch());

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->createBatch([$this->order(clientCode: 1234), $this->order(clientCode: 1234)]);
    }

    public function testCreateRequiresAClientCode(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::awb());

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->create($this->order());
    }

    public function testCreateBatchRequiresAClientCode(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batch());

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->createBatch([$this->order()]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAwbs(): iterable
    {
        yield 'BOK prefix' => ['BOK3925457'];
        yield 'partner 12 digits' => ['062568455929'];
        yield 'CKC prefix' => ['CKC122336'];
        yield 'plain digits' => ['3925457'];
    }

    #[DataProvider('validAwbs')]
    public function testCreateAcceptsAwbFormats(string $awb): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response($awb));

        self::assertSame($awb, $client->orders()->create($this->order(clientCode: 1234)));
    }

    public function testCreateRequiresADistrict(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::awb());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bookurier requires a district (sector or county): set Order::$district.');

        $client->orders()->create($this->order(clientCode: 1234, district: ''));
    }

    public function testCreateBatchRequiresADistrict(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batch());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bookurier requires a district (sector or county): set Order::$district.');

        $client->orders()->createBatch([$this->order(clientCode: 1234, district: '  ')]);
    }

    public function testMinusFiveBecomesAValidationException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('-5'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bookurier returned -5: the service rejected the order (observed when <district> is missing or empty).');

        $client->orders()->create($this->order(clientCode: 1234));
    }

    private function order(string $uniqueId = 'UNQ-1', ?int $clientCode = null, string $district = 'Sect 1'): Order
    {
        return new Order(
            uniqueId: $uniqueId,
            recipientName: 'Ion Popescu',
            phone: '0712345678',
            city: 'Bucharest',
            district: $district,
            street: 'Piața Unirii',
            number: '10',
            service: ServiceType::BucharestExpress,
            weight: 2.5,
            packages: 2,
            clientCode: $clientCode,
        );
    }

    /**
     * @return array<string, string>
     */
    private function form(FakeHttpClient $httpClient): array
    {
        self::assertNotNull($httpClient->lastRequest);

        parse_str((string) $httpClient->lastRequest->getBody(), $form);

        /** @var array<string, string> $form */
        return $form;
    }

    private function assertRequest(FakeHttpClient $httpClient, string $path): void
    {
        $request = $httpClient->lastRequest;

        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://www.bookurier.ro/colete/serv/' . $path, (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }
}
