<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Http;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Http\Psr18HttpClientAdapter;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Psr18HttpClientAdapterTest extends TestCase
{
    public function testDelegatesTheSameRequestWithoutTimeout(): void
    {
        $response = new Response(200, [], 'body');
        $client = new class ($response) implements ClientInterface {
            public int $calls = 0;

            public ?RequestInterface $request = null;

            public function __construct(private ResponseInterface $response) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->calls++;
                $this->request = $request;

                return $this->response;
            }
        };
        $request = new Request('POST', 'https://example.test/add_cmd.php', ['Content-Type' => 'text/plain'], 'payload');

        $result = (new Psr18HttpClientAdapter($client))->sendRequest($request);

        self::assertSame(1, $client->calls);
        self::assertSame($request, $client->request);
        self::assertSame('payload', (string) $client->request->getBody());
        self::assertSame($response, $result);
    }

    public function testRejectsANonNullTimeoutWithoutCallingTheClient(): void
    {
        $client = new class implements ClientInterface {
            public int $calls = 0;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->calls++;

                return new Response();
            }
        };

        try {
            (new Psr18HttpClientAdapter($client))->sendRequest(new Request('POST', 'https://example.test'), 2.0);

            self::fail('Expected InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('A foreign PSR-18 client cannot honour the SDK per-call timeout; configure the timeout on that client.', $e->getMessage());
        }

        self::assertSame(0, $client->calls);
    }

    public function testWrapsClientExceptionsAsTransportExceptions(): void
    {
        $exception = new ConnectException('Connection refused', new Request('POST', 'https://example.test'));
        $client = new class ($exception) implements ClientInterface {
            public function __construct(private ConnectException $exception) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        try {
            (new Psr18HttpClientAdapter($client))->sendRequest(new Request('POST', 'https://example.test'));

            self::fail('Expected TransportException.');
        } catch (TransportException $e) {
            self::assertSame('Connection refused', $e->getMessage());
            self::assertSame($exception, $e->getPrevious());
        }
    }

    public function testClientCreateAcceptsABarePsr18Client(): void
    {
        $client = new class implements ClientInterface {
            public ?RequestInterface $request = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], '000112870071');
            }
        };

        $bookurier = Client::create(
            $client,
            config: new ClientConfig(userId: 'user', password: 'pass'),
        );

        $response = $bookurier->post('add_cmd.php', ['msg' => '<msg></msg>']);

        self::assertSame('000112870071', (string) $response->getBody());
        self::assertNotNull($client->request);
        self::assertSame('https://www.bookurier.ro/colete/serv/add_cmd.php', (string) $client->request->getUri());
    }
}
