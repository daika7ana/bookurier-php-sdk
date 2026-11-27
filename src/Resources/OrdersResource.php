<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\DTOs\Order\BatchResult;
use Daika7ana\Bookurier\DTOs\Order\HistoryEvent;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\DTOs\Order\OrderError;
use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\SimpleCollections\Collection;

final class OrdersResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Creates one order and returns its AWB.
     *
     * @throws ValidationException when the order is listed in the response errors
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     * @throws \InvalidArgumentException when the pickup point or district is missing
     */
    public function create(Order $order): string
    {
        $body = ResponseBody::json($this->client->postJson('add_cmds.php', [
            'data' => [$this->payload($order)],
        ]));

        $errors = $this->mapErrors($body);
        if ($errors !== []) {
            throw $this->errorException($errors[0], $body);
        }

        $map = $body->map();
        if (isset($map[$order->uniqueId])) {
            return $map[$order->uniqueId];
        }

        $data = $body->data();
        if (isset($data[0]) && is_string($data[0])) {
            return $data[0];
        }

        throw new UnexpectedStatusException(
            $body->response(),
            'Response carries no AWB for the order.',
            retryAfter: ResponseBody::retryAfter($body->response()),
        );
    }

    /**
     * Creates several orders in one call and maps each unq to its AWB.
     *
     * @param list<Order> $orders
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     * @throws \InvalidArgumentException when the list is empty, a uniqueId repeats, or a pickup point or district is missing
     */
    public function createBatch(array $orders): BatchResult
    {
        if ($orders === []) {
            throw new \InvalidArgumentException('createBatch() needs at least one order.');
        }

        $data = [];
        $seen = [];
        foreach ($orders as $order) {
            if ($order->uniqueId !== '' && isset($seen[$order->uniqueId])) {
                throw new \InvalidArgumentException('createBatch() requires unique uniqueId values: the response map pairs by unq.');
            }

            $seen[$order->uniqueId] = true;
            $data[] = $this->payload($order);
        }

        $body = ResponseBody::json($this->client->postJson('add_cmds.php', ['data' => $data]));
        $map = $body->map();

        $references = [];
        foreach ($orders as $order) {
            if (isset($map[$order->uniqueId])) {
                $references[] = new OrderReference($order->uniqueId, $map[$order->uniqueId]);
            }
        }

        return new BatchResult(new Collection($references), new Collection($this->mapErrors($body)));
    }

    /**
     * Every recorded event for an AWB, chronological; the last one is current.
     *
     * @return Collection<int, HistoryEvent>
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function history(string $awb): Collection
    {
        $key = $this->client->getConfig()->trackingKey ?? ClientConfig::DEFAULT_TRACKING_KEY;

        $body = ResponseBody::json($this->client->get('awb_history.php', ['key' => $key, 'awb' => $awb]));

        $events = [];
        foreach ($body->data() as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $events[] = HistoryEvent::fromArray($row);
            }
        }

        return new Collection($events);
    }

    /**
     * @return array<string, string|int|float>
     */
    private function payload(Order $order): array
    {
        $pickupPoint = $this->client->getConfig()->requirePickupPoint($order->pickupPoint);

        if (trim((string) $order->district) === '') {
            throw new \InvalidArgumentException(
                'Bookurier requires a district (sector or county): set Order::$district.',
            );
        }

        return ['pickup_point' => $pickupPoint] + $order->toArray();
    }

    /**
     * @return list<OrderError>
     */
    private function mapErrors(ResponseBody $body): array
    {
        $errors = [];
        foreach ($body->errors() as $error) {
            $errors[] = OrderError::fromArray($error);
        }

        return $errors;
    }

    private function errorException(OrderError $error, ResponseBody $body): ValidationException
    {
        $message = $error->message;
        if ($error->suggestion !== null) {
            $message = trim($message . ' ' . $error->suggestion);
        }

        return new ValidationException(
            message: $message === '' ? 'Bookurier rejected the order.' : $message,
            response: $body->response(),
            retryAfter: ResponseBody::retryAfter($body->response()),
        );
    }
}
