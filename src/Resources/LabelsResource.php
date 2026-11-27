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

final class LabelsResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * AWB labels as PDF, for one AWB or a list.
     *
     * @param string|list<string> $awbs
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function pdf(string|array $awbs, ?string $mode = null, int $page = 0): Document
    {
        return ResponseBody::document(
            $this->client->postJson('print_awbs.php', $this->payload($awbs, 'pdf', $mode, $page)),
            'pdf',
        );
    }

    /**
     * AWB labels as HTML, for one AWB or a list.
     *
     * @param string|list<string> $awbs
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function html(string|array $awbs, ?string $mode = null, int $page = 0): Document
    {
        return ResponseBody::document(
            $this->client->postJson('print_awbs.php', $this->payload($awbs, 'html', $mode, $page)),
        );
    }

    /**
     * @param string|list<string> $awbs
     * @return array<string, mixed>
     */
    private function payload(string|array $awbs, string $format, ?string $mode, int $page): array
    {
        $payload = [
            'data' => is_array($awbs) ? array_values($awbs) : [$awbs],
            'format' => $format,
        ];

        if ($mode !== null) {
            $payload['mode'] = $mode;
        }

        $payload['page'] = $page;

        return $payload;
    }
}
