<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Support;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class TestClientFactory
{
    /**
     * @param callable(RequestInterface): ResponseInterface $handler
     * @return array{Client, FakeHttpClient}
     */
    public static function create(callable $handler, ?ClientConfig $config = null): array
    {
        $httpClient = new FakeHttpClient($handler);
        $psr17Factory = new HttpFactory();

        $client = new Client(
            $httpClient,
            $psr17Factory,
            $psr17Factory,
            $config ?? new ClientConfig(userId: 'test-user', password: 'test-password'),
        );

        return [$client, $httpClient];
    }
}
