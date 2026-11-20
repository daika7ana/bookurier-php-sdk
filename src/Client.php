<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier;

use Daika7ana\Bookurier\Config\ClientConfig;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Http\GuzzleHttpClient;
use Daika7ana\Bookurier\Http\HttpClientInterface;
use Daika7ana\Bookurier\Http\Psr18HttpClientAdapter;
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
 * PSR-18 request builder for the Bookurier courier services API.
 *
 * Every endpoint is a form POST with `userid`, `pwd` and an optional `msg` XML payload.
 */
class Client
{
    private HttpClientInterface $httpClient;

    private ClientConfig $config;

    private ?OrdersResource $ordersResource = null;

    private ?LabelsResource $labelsResource = null;

    private ?ReportsResource $reportsResource = null;

    private ?PickupResource $pickupResource = null;

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

    public function createRequest(string $method, string $path): RequestInterface
    {
        $uri = rtrim($this->config->baseUrl, '/') . '/' . ltrim($path, '/');

        return $this->requestFactory->createRequest($method, $uri);
    }

    /**
     * Posts a form-encoded request, always carrying the credentials.
     *
     * @param array<string, string> $form Extra form fields, usually `msg`.
     *
     * @throws AuthenticationException
     * @throws TransportException
     * @throws \InvalidArgumentException when a per-call timeout cannot be applied to the transport
     */
    public function post(string $endpoint, array $form = [], ?float $timeout = null): ResponseInterface
    {
        if ($this->config->userId === '' || $this->config->password === '') {
            throw new AuthenticationException(
                'Bookurier credentials are missing. Pass them to ClientConfig or set BOOKURIER_USERNAME and BOOKURIER_PASSWORD.',
            );
        }

        $fields = ['userid' => $this->config->userId, 'pwd' => $this->config->password] + $form;

        $request = $this->createRequest('POST', $endpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($fields, '', '&', PHP_QUERY_RFC1738)));

        return $this->send($request, $timeout);
    }

    /**
     * @throws TransportException
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
}
