<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Http\GuzzleHttpClient;
use Daika7ana\Bookurier\Http\HttpClientInterface;
use Daika7ana\Bookurier\Http\Psr18HttpClientAdapter;
use Daika7ana\Bookurier\Resources\CitiesResource;
use Daika7ana\Bookurier\Resources\LabelsResource;
use Daika7ana\Bookurier\Resources\OrdersResource;
use Daika7ana\Bookurier\Resources\PickupResource;
use Daika7ana\Bookurier\Resources\ReportsResource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * PSR-18 request builder for Bookurier's October 2026 JSON API.
 *
 * Account endpoints carry the portal user and pwd on every request; awb_history
 * is public and needs only the shared tracking key.
 */
class Client
{
    private HttpClientInterface $httpClient;

    private ClientConfig $config;

    private ?OrdersResource $ordersResource = null;

    private ?LabelsResource $labelsResource = null;

    private ?ReportsResource $reportsResource = null;

    private ?PickupResource $pickupResource = null;

    private ?CitiesResource $citiesResource = null;

    public function __construct(
        ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        ?ClientConfig $config = null,
    ) {
        $this->config = $config ?? ClientConfig::fromEnvironment();

        if ($this->config->timeout !== null && ! $httpClient instanceof HttpClientInterface) {
            throw new \InvalidArgumentException('ClientConfig::timeout cannot be applied to a foreign PSR-18 client; configure the timeout on that client or leave it null.');
        }

        $this->httpClient = $httpClient instanceof HttpClientInterface
            ? $httpClient
            : new Psr18HttpClientAdapter($httpClient);
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    /**
     * A client sharing this client's transport, PSR-17 factories and credentials,
     * but with a different request timeout budget.
     */
    public function withTimeout(?float $timeout): self
    {
        if ($timeout !== null && $this->httpClient instanceof Psr18HttpClientAdapter) {
            throw new \InvalidArgumentException('ClientConfig::timeout cannot be applied to a foreign PSR-18 client; configure the timeout on that client or leave it null.');
        }

        return new self(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $this->config->withTimeout($timeout),
        );
    }

    public function setCredentials(string $userId, string $password): self
    {
        $this->config = $this->config->withCredentials($userId, $password);

        return $this;
    }

    public function orders(): OrdersResource
    {
        return $this->ordersResource ??= new OrdersResource($this);
    }

    public function labels(): LabelsResource
    {
        return $this->labelsResource ??= new LabelsResource($this);
    }

    public function reports(): ReportsResource
    {
        return $this->reportsResource ??= new ReportsResource($this);
    }

    public function pickup(): PickupResource
    {
        return $this->pickupResource ??= new PickupResource($this);
    }

    public function cities(): CitiesResource
    {
        return $this->citiesResource ??= new CitiesResource($this);
    }

    public function createRequest(string $method, string $path): RequestInterface
    {
        $uri = rtrim($this->config->baseUrl, '/') . '/' . ltrim($path, '/');

        return $this->requestFactory->createRequest($method, $uri);
    }

    /**
     * Posts a raw JSON body, always carrying the credentials.
     *
     * @param array<string, mixed> $payload
     *
     * @throws AuthenticationException
     * @throws TransportException
     * @throws \JsonException when the payload cannot be encoded, e.g. it holds invalid UTF-8
     * @throws \InvalidArgumentException when a per-call timeout cannot be applied to the transport
     */
    public function postJson(string $endpoint, array $payload, ?float $timeout = null): ResponseInterface
    {
        $this->assertCredentials();

        $body = json_encode(
            ['user' => $this->config->userId, 'pwd' => $this->config->password] + $payload,
            JSON_THROW_ON_ERROR,
        );

        $request = $this->createRequest('POST', $endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        return $this->send($request, $timeout);
    }

    /**
     * Posts a form-encoded request, always carrying the credentials.
     *
     * @param array<string, string> $fields Extra form fields.
     *
     * @throws AuthenticationException
     * @throws TransportException
     * @throws \InvalidArgumentException when a per-call timeout cannot be applied to the transport
     */
    public function postForm(string $endpoint, array $fields = [], ?float $timeout = null): ResponseInterface
    {
        $this->assertCredentials();

        $fields = ['user' => $this->config->userId, 'pwd' => $this->config->password] + $fields;

        $request = $this->createRequest('POST', $endpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($fields, '', '&', PHP_QUERY_RFC1738)));

        return $this->send($request, $timeout);
    }

    /**
     * GETs a public endpoint; no account credentials are attached.
     *
     * @param array<string, string> $query
     *
     * @throws TransportException
     * @throws \InvalidArgumentException when a per-call timeout cannot be applied to the transport
     */
    public function get(string $endpoint, array $query = [], ?float $timeout = null): ResponseInterface
    {
        $path = $query === []
            ? $endpoint
            : $endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->send($this->createRequest('GET', $path), $timeout);
    }

    /**
     * @throws TransportException
     * @throws \InvalidArgumentException when a per-call timeout cannot be applied to the transport
     */
    public function send(RequestInterface $request, ?float $timeout = null): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request, $timeout ?? $this->config->timeout);
        } catch (TransportException $e) {
            throw $e;
        } catch (ClientExceptionInterface $e) {
            throw new TransportException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function create(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?ClientConfig $config = null,
    ): self {
        $httpClient ??= new GuzzleHttpClient();
        $config ??= ClientConfig::fromEnvironment();

        if ($requestFactory === null || $streamFactory === null) {
            $psr17Factory = new \GuzzleHttp\Psr7\HttpFactory();
            $requestFactory ??= $psr17Factory;
            $streamFactory ??= $psr17Factory;
        }

        return new self($httpClient, $requestFactory, $streamFactory, $config);
    }

    private function assertCredentials(): void
    {
        if ($this->config->userId === '' || $this->config->password === '') {
            throw new AuthenticationException(
                'Bookurier credentials are missing. Pass them to ClientConfig or set BOOKURIER_USERNAME and BOOKURIER_PASSWORD.',
            );
        }
    }
}
