<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\DTOs\Order\HistoryEntry;
use Daika7ana\Bookurier\DTOs\Order\Order;
use Daika7ana\Bookurier\DTOs\Order\OrderReference;
use Daika7ana\Bookurier\DTOs\Order\OrderStatus;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\Bookurier\Support\Xml;
use Daika7ana\Bookurier\Support\XmlResponse;
use Daika7ana\SimpleCollections\Collection;

final class OrdersResource
{
    // Three live shapes, chosen server-side: `BOK` + 7 digits, 12 bare digits on
    // partner routes, or `CKC` + 6 digits. Kept lenient on purpose.
    private const AWB_PATTERN = '/^[A-Za-z]{0,4}\d{3,}$/';

    public function __construct(
        private Client $client,
    ) {}

    /**
     * Creates one order and returns the generated AWB.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function create(Order $order): string
    {
        $this->assertOrderRequirements($order);

        $response = $this->client->post('add_cmd.php', ['msg' => Xml::message($order->toXml($this->client->getConfig()->clientId))]);
        $awb = ResponseBody::text($response);

        if (preg_match(self::AWB_PATTERN, $awb) !== 1) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Expected an AWB number. Got: "%s".', substr($awb, 0, 200)),
                retryAfter: ResponseBody::retryAfter($response),
            );
        }

        return $awb;
    }

    /**
     * Creates several orders in one call (`add_cmds.php`).
     *
     * @param list<Order> $orders
     * @return Collection<int, OrderReference>
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function createBatch(array $orders): Collection
    {
        if ($orders === []) {
            throw new \InvalidArgumentException('createBatch() needs at least one order.');
        }

        $uniqueIds = [];
        foreach ($orders as $order) {
            $this->assertOrderRequirements($order);

            if ($order->uniqueId !== '' && isset($uniqueIds[$order->uniqueId])) {
                throw new \InvalidArgumentException('createBatch() requires unique uniqueId values: AWB pairing is positional.');
            }

            $uniqueIds[$order->uniqueId] = true;
        }

        $clientCode = $this->client->getConfig()->clientId;
        $commands = '';
        foreach ($orders as $order) {
            $commands .= $order->toXml($clientCode);
        }

        $response = $this->client->post('add_cmds.php', ['msg' => Xml::message('<cmds>' . $commands . '</cmds>')]);
        $xml = ResponseBody::xml($response);

        $cmds = $xml->text('cmds');
        if ($cmds === null) {
            throw new UnexpectedStatusException(
                $response,
                'Response XML has no <cmds> element.',
                retryAfter: ResponseBody::retryAfter($response),
            );
        }

        // Live API answers <msg><cmds>BOK1,BOK2</cmds></msg>: a comma list, not <cmd> elements.
        $awbs = array_values(array_filter(
            array_map(trim(...), explode(',', $cmds)),
            static fn(string $awb): bool => $awb !== '',
        ));

        if (count($awbs) !== count($orders)) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Expected %d AWBs, got %d.', count($orders), count($awbs)),
                retryAfter: ResponseBody::retryAfter($response),
            );
        }

        $references = [];
        foreach ($orders as $index => $order) {
            if (preg_match(self::AWB_PATTERN, $awbs[$index]) !== 1) {
                throw new UnexpectedStatusException(
                    $response,
                    'Expected an AWB number.',
                    retryAfter: ResponseBody::retryAfter($response),
                );
            }

            $references[] = new OrderReference($order->uniqueId, $awbs[$index]);
        }

        return new Collection($references);
    }

    private function assertOrderRequirements(Order $order): void
    {
        if (($order->clientCode ?? $this->client->getConfig()->clientId) === null) {
            throw new \InvalidArgumentException(
                'Bookurier requires a client code: set Order::$clientCode or ClientConfig::clientId (BOOKURIER_CLIENT_ID).',
            );
        }

        if (trim((string) $order->district) === '') {
            throw new \InvalidArgumentException(
                'Bookurier requires a district (sector or county): set Order::$district.',
            );
        }
    }

    /**
     * Deletes an order by AWB.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function delete(string $awb): void
    {
        ResponseBody::success(
            $this->client->post('del_cmd.php', ['msg' => Xml::message(Xml::cmd(['awb' => $awb]))]),
        );
    }

    /**
     * Deletes an order by the caller's own identifier.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function deleteByUniqueId(string $uniqueId): void
    {
        ResponseBody::success(
            $this->client->post('del_cmd_unq.php', ['msg' => Xml::message(Xml::cmd(['unq' => $uniqueId]))]),
        );
    }

    /**
     * Current status of an order, looked up by AWB.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function status(string $awb): OrderStatus
    {
        $xml = ResponseBody::xml(
            $this->client->post('get_stat.php', ['msg' => Xml::message(Xml::cmd(['awb' => $awb]))]),
        );

        return OrderStatus::fromArray($xml->cmd());
    }

    /**
     * Current status of an order, looked up by the caller's own identifier.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function statusByUniqueId(string $uniqueId): OrderStatus
    {
        $xml = ResponseBody::xml(
            $this->client->post('get_stat_unq.php', ['msg' => Xml::message(Xml::cmd(['unq' => $uniqueId]))]),
        );

        return OrderStatus::fromArray($xml->cmd());
    }

    /**
     * Every recorded status change for an order, looked up by AWB.
     *
     * The live `get_history.php` answers `-2` even for a valid AWB; prefer
     * {@see historyByUniqueId()}.
     *
     * @return Collection<int, HistoryEntry>
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function history(string $awb): Collection
    {
        return $this->mapHistory(
            ResponseBody::xml(
                $this->client->post('get_history.php', ['msg' => Xml::message(Xml::cmd(['awb' => $awb]))]),
            ),
        );
    }

    /**
     * Every recorded status change for an order, looked up by the caller's own identifier.
     *
     * @return Collection<int, HistoryEntry>
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function historyByUniqueId(string $uniqueId): Collection
    {
        return $this->mapHistory(
            ResponseBody::xml(
                $this->client->post('get_history_unq.php', ['msg' => Xml::message(Xml::cmd(['unq' => $uniqueId]))]),
            ),
        );
    }

    /**
     * @return Collection<int, HistoryEntry>
     */
    private function mapHistory(XmlResponse $xml): Collection
    {
        $entries = [];
        foreach ($xml->commands() as $command) {
            $entries[] = HistoryEntry::fromArray($command);
        }

        return new Collection($entries);
    }
}
