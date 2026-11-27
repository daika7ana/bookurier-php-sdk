<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Http\GuzzleHttpClient;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ClientTest extends TestCase
{
    public function testCreateWiresGuzzleAndThePsr17Factory(): void
    {
        $client = Client::create(config: new ClientConfig(userId: 'user', password: 'pass'));

        $httpClient = new \ReflectionProperty(Client::class, 'httpClient');
        $requestFactory = new \ReflectionProperty(Client::class, 'requestFactory');
        $streamFactory = new \ReflectionProperty(Client::class, 'streamFactory');

        self::assertInstanceOf(GuzzleHttpClient::class, $httpClient->getValue($client));
        self::assertInstanceOf(HttpFactory::class, $requestFactory->getValue($client));
        self::assertInstanceOf(HttpFactory::class, $streamFactory->getValue($client));
    }

    public function testForeignKeyPsr18ClientWithTimeoutIsRejected(): void
    {
        $foreign = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
        $factory = new HttpFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ClientConfig::timeout cannot be applied to a foreign PSR-18 client; configure the timeout on that client or leave it null.');

        new Client($foreign, $factory, $factory, new ClientConfig(userId: 'user', password: 'pass', timeout: 5.0));
    }

    public function testMissingCredentialsAreRejectedBeforeSendingJson(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(),
        );

        try {
            $client->postJson('add_cmds.php', ['data' => []]);

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertStringContainsString('credentials are missing', $e->getMessage());
        }

        self::assertNull($httpClient->lastRequest);
    }

    public function testMissingCredentialsAreRejectedBeforeSendingForm(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(),
        );

        try {
            $client->postForm('print_borderou.php', ['pickup' => '5', 'filter_date' => '2026-11-26']);

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertStringContainsString('credentials are missing', $e->getMessage());
        }

        self::assertNull($httpClient->lastRequest);
    }

    public function testGetWorksWithoutAccountCredentials(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::history(),
            config: new ClientConfig(),
        );

        $client->get('awb_history.php', ['key' => 'track-key', 'awb' => 'BOK6000123456']);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('', $request->getBody()->getContents());
    }

    public function testPostJsonSendsCredentialsAndPayloadAsJson(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::addSuccess());

        $client->postJson('add_cmds.php', ['data' => [['unq' => 'CMD-10001']]]);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/add_cmds.php', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'user' => 'test-user',
            'pwd' => 'test-password',
            'data' => [['unq' => 'CMD-10001']],
        ], $httpClient->lastJson());
    }

    public function testPostJsonRejectsInvalidUtf8Payloads(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response(''));

        $this->expectException(\JsonException::class);

        try {
            $client->postJson('add_cmds.php', ['data' => [['unq' => "\xB1"]]]);
        } finally {
            self::assertNull($httpClient->lastRequest);
        }
    }

    public function testPostFormSendsThePortalFieldNames(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::pickup());

        $client->postForm('request_pickup.php', ['pickup' => '5']);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame(['user' => 'test-user', 'pwd' => 'test-password', 'pickup' => '5'], $httpClient->lastForm());
    }

    public function testGetSendsTheQueryString(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::history());

        $client->get('awb_history.php', ['key' => 'track-key', 'awb' => 'BOK6000123456']);

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/awb_history.php?key=track-key&awb=BOK6000123456', (string) $request->getUri());
    }

    public function testConfigTimeoutReachesTheTransport(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(userId: 'user', password: 'pass', timeout: 7.5),
        );

        $client->postJson('add_cmds.php', ['data' => []]);

        self::assertSame(7.5, $httpClient->lastTimeout);
    }

    public function testWithTimeoutDerivesAClientSharingTransportAndCredentials(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(userId: 'user', password: 'pass', timeout: 10.0),
        );

        $derived = $client->withTimeout(30.0);

        self::assertSame(30.0, $derived->getConfig()->timeout);
        self::assertSame(10.0, $client->getConfig()->timeout);
        self::assertSame('user', $derived->getConfig()->userId);

        $derived->postForm('print_borderou.php');

        self::assertSame(30.0, $httpClient->lastTimeout);
        self::assertSame('user', $httpClient->lastForm()['user']);
        self::assertSame('pass', $httpClient->lastForm()['pwd']);
    }

    public function testPerCallTimeoutOverridesTheConfigTimeout(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(userId: 'user', password: 'pass', timeout: 7.5),
        );

        $client->postJson('add_cmds.php', [], 2.0);

        self::assertSame(2.0, $httpClient->lastTimeout);
    }

    public function testCustomBaseUrlIsHonoured(): void
    {
        [$client, $httpClient] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(userId: 'user', password: 'pass', baseUrl: 'https://example.test/api/'),
        );

        $client->get('awb_history.php');

        self::assertNotNull($httpClient->lastRequest);
        self::assertSame('https://example.test/api/awb_history.php', (string) $httpClient->lastRequest->getUri());
    }

    public function testSetCredentialsReplacesTheConfig(): void
    {
        [$client] = TestClientFactory::create(
            static fn(): ResponseInterface => Fixture::response(''),
            config: new ClientConfig(),
        );

        $result = $client->setCredentials('new-user', 'new-pass');

        self::assertSame($client, $result);
        self::assertSame('new-user', $client->getConfig()->userId);
        self::assertSame('new-pass', $client->getConfig()->password);
    }

    public function testResourcesAreSharedInstances(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response(''));

        self::assertSame($client->orders(), $client->orders());
        self::assertSame($client->labels(), $client->labels());
        self::assertSame($client->reports(), $client->reports());
        self::assertSame($client->pickup(), $client->pickup());
        self::assertSame($client->cities(), $client->cities());
    }

    public function testSendDoesNotInjectCredentialsIntoACallerRequest(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response(''));

        $request = $client->createRequest('GET', 'awb_history.php');

        $client->send($request);

        self::assertNotNull($httpClient->lastRequest);
        self::assertSame('', (string) $httpClient->lastRequest->getUri()->getQuery());
    }

    public function testForeignClientExceptionIsWrappedAsTransportException(): void
    {
        $previous = new class ('boom') extends \RuntimeException implements ClientExceptionInterface {};
        $foreign = new class ($previous) implements ClientInterface {
            public function __construct(private \RuntimeException $previous) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->previous;
            }
        };
        $factory = new HttpFactory();

        $client = new Client($foreign, $factory, $factory, new ClientConfig(userId: 'user', password: 'pass'));

        try {
            $client->get('awb_history.php');

            self::fail('Expected TransportException.');
        } catch (TransportException $e) {
            self::assertSame($previous, $e->getPrevious());
            self::assertSame('boom', $e->getMessage());
        }
    }

    public function testForeignClientWithEnvironmentTimeoutIsRejected(): void
    {
        putenv('BOOKURIER_TIMEOUT=5');

        try {
            $foreign = new class implements ClientInterface {
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    return new Response();
                }
            };
            $factory = new HttpFactory();

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('ClientConfig::timeout cannot be applied to a foreign PSR-18 client');

            new Client($foreign, $factory, $factory);
        } finally {
            putenv('BOOKURIER_TIMEOUT');
        }
    }

    public function testWithTimeoutRejectsForeignPsr18ClientTransport(): void
    {
        $foreign = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
        $factory = new HttpFactory();

        $client = new Client($foreign, $factory, $factory, new ClientConfig(userId: 'user', password: 'pass'));

        $client->withTimeout(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ClientConfig::timeout cannot be applied to a foreign PSR-18 client; configure the timeout on that client or leave it null.');

        $client->withTimeout(30.0);
    }
}
