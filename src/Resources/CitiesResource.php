<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\DTOs\Order\PartnerCity;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\SimpleCollections\Collection;

final class CitiesResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * Partner-served cities, optionally filtered by district (diacritics-insensitive).
     *
     * @return Collection<int, PartnerCity>
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function partnerCities(?string $district = null): Collection
    {
        $body = ResponseBody::json($this->client->postJson('get_partner_cities.php', [
            'data' => $district === null ? new \stdClass() : ['district' => $district],
        ]));

        $cities = [];
        foreach ($body->data() as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $cities[] = PartnerCity::fromArray($row);
            }
        }

        return new Collection($cities);
    }
}
