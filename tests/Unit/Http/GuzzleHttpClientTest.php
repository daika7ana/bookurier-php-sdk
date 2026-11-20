<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Http;

use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Http\GuzzleHttpClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GuzzleHttpClientTest extends TestCase
{
    public function testDefaultConstructionAppliesDocumentedTimeouts(): void
    {
        $client = new GuzzleHttpClient();

        $property = new \ReflectionProperty(GuzzleHttpClient::class, 'guzzleClient');
        $guzzle = $property->getValue($client);

        self::assertInstanceOf(GuzzleClient::class, $guzzle);
        self::assertSame(30.0, $guzzle->getConfig('timeout'));
        self::assertSame(10.0, $guzzle->getConfig('connect_timeout'));
        self::assertSame(30.0, GuzzleHttpClient::DEFAULT_TIMEOUT);
        self::assertSame(10.0, GuzzleHttpClient::DEFAULT_CONNECT_TIMEOUT);
    }

    public function testConstructorTimeoutsReachTheGuzzleClient(): void
    {
        [$guzzle, $mock] = $this->guzzleWithQueue([new Response()]);
        $client = new GuzzleHttpClient($guzzle, timeout: 12.0, connectTimeout: 4.0);

        $client->sendRequest(new Request('POST', 'https://example.test'));

        $options = $mock->getLastOptions();
        self::assertSame(12.0, $options['timeout']);
        self::assertSame(4.0, $options['connect_timeout']);
    }

    public function testPerRequestTimeoutOverridesTheConstructorValue(): void
    {
        [$guzzle, $mock] = $this->guzzleWithQueue([new Response()]);
        $client = new GuzzleHttpClient($guzzle, timeout: 12.0);

        $client->sendRequest(new Request('POST', 'https://example.test'), 1.5);

        self::assertSame(1.5, $mock->getLastOptions()['timeout']);
    }

    public function testDisablesErrorsAndRedirects(): void
    {
        [$guzzle, $mock] = $this->guzzleWithQueue([new Response(500)]);
        $client = new GuzzleHttpClient($guzzle);

        $response = $client->sendRequest(new Request('POST', 'https://example.test'));

        self::assertSame(500, $response->getStatusCode());
        $options = $mock->getLastOptions();
        self::assertFalse($options['http_errors']);
        self::assertFalse($options['allow_redirects']);
    }

    public function testWrapsGuzzleExceptions(): void
    {
        $exception = new ConnectException('Connection refused', new Request('POST', 'https://example.test'));
        [$guzzle] = $this->guzzleWithQueue([$exception]);
        $client = new GuzzleHttpClient($guzzle);

        try {
            $client->sendRequest(new Request('POST', 'https://example.test'));

            self::fail('Expected TransportException.');
        } catch (TransportException $e) {
            self::assertSame('Connection refused', $e->getMessage());
            self::assertSame($exception, $e->getPrevious());
        }
    }

    /**
     * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $queue
     * @return array{GuzzleClient, MockHandler}
     */
    private function guzzleWithQueue(array $queue): array
    {
        $mock = new MockHandler($queue);
        $guzzle = new GuzzleClient(['handler' => HandlerStack::create($mock)]);

        return [$guzzle, $mock];
    }
}
