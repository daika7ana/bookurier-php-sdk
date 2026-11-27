<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\HistoryEvent;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\Bookurier\Enums\ServiceType;
use Daika7ana\Bookurier\Enums\Status;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Tests\Support\FakeHttpClient;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class OrdersResourceTest extends TestCase
{
    public function testCreatePostsTheOrderAndReturnsTheMappedAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::addSuccess(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $awb = $client->orders()->create($this->order());

        self::assertSame('BOK6000123456', $awb);
        $this->assertJsonPost($httpClient, 'add_cmds.php');

        $body = $httpClient->lastJson();
        self::assertSame('test-user', $body['user']);
        self::assertSame('test-password', $body['pwd']);
        self::assertSame([[
            'pickup_point' => '5',
            'unq' => 'CMD-10001',
            'recv' => 'Ion Popescu',
            'phone' => '0744555555',
            'country' => 'Romania',
            'district' => 'Hunedoara',
            'city' => 'Deva',
            'zip' => '330020',
            'street' => 'Unirii',
            'no' => '1',
            'service' => 9,
            'packs' => 1,
            'weight' => 1.5,
            'ret_doc' => 0,
            'weekend' => 0,
            'unpack' => 0,
            'exchange_pack' => 0,
            'confirmation' => 0,
        ]], $body['data']);
    }

    public function testCreatePrefersTheOrderPickupPointOverTheConfig(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::addSuccess(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $client->orders()->create($this->order(pickupPoint: '7'));

        self::assertSame('7', $httpClient->lastJson()['data'][0]['pickup_point']);
    }

    public function testCreateFallsBackToTheFirstAwbWhenTheMapIsMissing(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::addSuccessWithoutMap(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        self::assertSame('BOK6000123456', $client->orders()->create($this->order()));
    }

    public function testCreateThrowsThePerOrderError(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::orderError('Judet invalid', 'Verificati judetul.'),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        try {
            $client->orders()->create($this->order());

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('Judet invalid Verificati judetul.', $e->getMessage());
        }
    }

    public function testCreateRequiresAPickupPoint(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::addSuccess());

        try {
            $client->orders()->create($this->order());

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('BOOKURIER_PICKUP_POINT', $e->getMessage());
        }

        self::assertNull($httpClient->lastRequest);
    }

    public function testCreateRequiresADistrict(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::addSuccess(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bookurier requires a district (sector or county): set Order::$district.');

        $client->orders()->create($this->order(district: ''));
    }

    public function testCreateBatchPostsAllOrdersAndMapsTheReferences(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::batchSuccess(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $result = $client->orders()->createBatch([
            $this->order(),
            $this->order(uniqueId: 'CMD-10002'),
        ]);

        $this->assertJsonPost($httpClient, 'add_cmds.php');
        self::assertCount(2, $httpClient->lastJson()['data']);
        self::assertFalse($result->hasErrors());
        self::assertCount(2, $result->references());

        $references = $result->references()->all();
        self::assertInstanceOf(OrderReference::class, $references[0]);
        self::assertSame('CMD-10001', $references[0]->uniqueId);
        self::assertSame('BOK6000123456', $references[0]->awb);
        self::assertSame('CMD-10002', $references[1]->uniqueId);
        self::assertSame('BOK6000123457', $references[1]->awb);
    }

    public function testCreateBatchSurfacesPartialSuccess(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::batchPartial(),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $result = $client->orders()->createBatch([
            $this->order(),
            $this->order(uniqueId: 'CMD-10002'),
        ]);

        self::assertTrue($result->hasErrors());
        self::assertCount(1, $result->references());
        self::assertSame('CMD-10001', $result->references()->all()[0]->uniqueId);

        $errors = $result->errors()->all();
        self::assertCount(1, $errors);
        self::assertSame('CMD-10002', $errors[0]->order);
        self::assertSame('Judet invalid', $errors[0]->message);
        self::assertSame('Verificati judetul.', $errors[0]->suggestion);
    }

    public function testCreateBatchRejectsDuplicateUniqueIds(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchSuccess());

        $this->expectException(\InvalidArgumentException::class);

        try {
            $client->orders()->createBatch([$this->order(), $this->order()]);
        } finally {
            self::assertNull($httpClient->lastRequest);
        }
    }

    public function testCreateBatchRejectsAnEmptyList(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchSuccess());

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->createBatch([]);
    }

    public function testCreateBatchRequiresAPickupPoint(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::batchSuccess());

        $this->expectException(\InvalidArgumentException::class);

        $client->orders()->createBatch([$this->order()]);
    }

    public function testHistoryGetsTheTrackingKeyAndTheAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::history());

        $history = $client->orders()->history('BOK6000123456');

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/awb_history.php?key=7BzvpY3PuANhhMajURYWn&awb=BOK6000123456', (string) $request->getUri());

        self::assertCount(2, $history);
        $events = $history->all();
        self::assertInstanceOf(HistoryEvent::class, $events[0]);
        self::assertSame('14.07.2023', $events[0]->date);
        self::assertSame('2', $events[0]->statusId);
        self::assertSame('Receptionata', $events[0]->statusName);
        self::assertSame(Status::Received, $events[0]->status());

        self::assertSame('15.07.2023', $events[1]->date);
        self::assertSame(Status::Delivered, $events[1]->status());
        self::assertTrue($events[1]->status()->isFinal());
    }

    public function testHistoryUsesTheConfiguredTrackingKey(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::history(),
            new ClientConfig(userId: 'test-user', password: 'test-password', trackingKey: 'custom-key'),
        );

        $client->orders()->history('BOK6000123456');

        self::assertNotNull($httpClient->lastRequest);
        self::assertStringContainsString('key=custom-key', (string) $httpClient->lastRequest->getUri());
    }

    public function testHistoryKeepsPartnerTextWithoutAStatusId(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::historyWithPartnerText());

        $event = $client->orders()->history('BOK6000123456')->all()[0];

        self::assertNull($event->statusId);
        self::assertNull($event->status());
        self::assertSame('In curs de livrare la curier partener', $event->statusName);
    }

    public function testHistoryClassifiesAnUnknownAwb(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::error('AWB inexistent'));

        $this->expectException(ValidationException::class);

        $client->orders()->history('BOK0000000000');
    }

    public function testInvalidUserBecomesAnAuthenticationException(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::error('Invalid user'),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $this->expectException(AuthenticationException::class);

        $client->orders()->create($this->order());
    }

    public function testAnEmptyResponseBecomesAnUnexpectedStatusException(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'),
        );

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->create($this->order());
    }

    public function testAResponseWithoutAnAwbBecomesAnUnexpectedStatusException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::json([
            'status' => 'success',
            'data' => [],
            'map' => [],
            'errors' => [],
            'message' => 'ok',
        ]), new ClientConfig(userId: 'test-user', password: 'test-password', pickupPoint: '5'));

        $this->expectException(UnexpectedStatusException::class);

        $client->orders()->create($this->order());
    }

    private function order(
        string $uniqueId = 'CMD-10001',
        ?string $pickupPoint = null,
        string $district = 'Hunedoara',
    ): Order {
        return new Order(
            uniqueId: $uniqueId,
            recipientName: 'Ion Popescu',
            phone: '0744555555',
            city: 'Deva',
            street: 'Unirii',
            service: ServiceType::National24,
            weight: 1.5,
            pickupPoint: $pickupPoint,
            zip: '330020',
            district: $district,
            number: '1',
        );
    }

    private function assertJsonPost(FakeHttpClient $httpClient, string $path): void
    {
        $request = $httpClient->lastRequest;

        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/' . $path, (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }
}
