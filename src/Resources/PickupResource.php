<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\ResponseBody;

final class PickupResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Asks Bookurier for a courier pickup and returns the confirmation message.
     *
     * @experimental Dispatches a physical courier, has never been exercised
     * against the live service and may still change shape in a minor release;
     * verify the confirmation before retrying.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     * @throws \InvalidArgumentException when no pickup point is given or configured
     */
    public function request(?string $pickupPoint = null): string
    {
        $pickup = $this->client->getConfig()->requirePickupPoint($pickupPoint);

        $body = ResponseBody::json($this->client->postForm('request_pickup.php', ['pickup' => $pickup]));

        return $body->message();
    }
}
