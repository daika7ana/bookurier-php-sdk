<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use Daika7ana\Bookurier\Support\Document;
use Daika7ana\Bookurier\Support\ResponseBody;

final class ReportsResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * The daily order list (bordero), as HTML.
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedResponseException
     */
    public function dailyBordero(): Document
    {
        return ResponseBody::document($this->client->post('get_cmd_list.php'));
    }
}
