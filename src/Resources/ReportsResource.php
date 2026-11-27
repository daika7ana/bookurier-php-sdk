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

final class ReportsResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * The printable bordero for one pickup point and date.
     *
     * @param string $date yyyy-mm-dd or dd-mm-yyyy, as the vendor accepts both.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     * @throws \InvalidArgumentException when no pickup point is given or configured
     */
    public function borderou(string $date, ?string $pickupPoint = null, ?float $timeout = null): Document
    {
        $pickup = $this->client->getConfig()->requirePickupPoint($pickupPoint);

        return ResponseBody::document($this->client->postForm('print_borderou.php', [
            'pickup' => $pickup,
            'filter_date' => $date,
        ], $timeout));
    }
}
