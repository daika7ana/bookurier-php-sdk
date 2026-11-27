<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Support;

use Daika7ana\Bookurier\Http\HttpClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements HttpClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public ?float $lastTimeout = null;

    /**
     * @var callable(RequestInterface): ResponseInterface
     */
    private $handler;

    /**
     * @param (callable(RequestInterface): ResponseInterface)|null $handler
     */
    public function __construct(?callable $handler = null)
    {
        $this->handler = $handler ?? static fn(): ResponseInterface => new Response(200, [], '');
    }

    public function sendRequest(RequestInterface $request, ?float $timeout = null): ResponseInterface
    {
        $this->lastRequest = $request;
        $this->lastTimeout = $timeout;

        return ($this->handler)($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function lastJson(): array
    {
        if ($this->lastRequest === null) {
            return [];
        }

        $decoded = json_decode((string) $this->lastRequest->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    public function lastForm(): array
    {
        if ($this->lastRequest === null) {
            return [];
        }

        parse_str((string) $this->lastRequest->getBody(), $form);

        /** @var array<string, string> $form */
        return $form;
    }
}
