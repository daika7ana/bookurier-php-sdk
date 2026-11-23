<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\Document;
use Daika7ana\Bookurier\Support\ResponseBody;

final class PickupResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Asks Bookurier for a courier pickup and returns the pickup bordero as HTML.
     *
     * @experimental Dispatches a physical courier, has never been exercised
     * against the live service, returns HTML and may still change shape in a
     * minor release; verify the returned bordero before retrying.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function request(): Document
    {
        return ResponseBody::document($this->client->post('request_delivery.php'));
    }
}
