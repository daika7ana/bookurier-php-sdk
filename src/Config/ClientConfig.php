<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Config;

final readonly class ClientConfig
{
    public const BASE_URL = 'https://www.bookurier.ro/colete/serv';

    /**
     * @param float|null $timeout Per-request timeout in seconds. Null lets the bundled transport apply its
     *                            30 s request / 10 s connect defaults.
     */
    public function __construct(
        public string $userId = '',
        public string $password = '',
        public string $baseUrl = self::BASE_URL,
        public ?float $timeout = null,
        public ?int $clientId = null,
    ) {}

    public static function fromEnvironment(): self
    {
        $timeout = getenv('BOOKURIER_TIMEOUT');
        $clientId = getenv('BOOKURIER_CLIENT_ID');

        if ($timeout !== false && $timeout !== '' && ! is_numeric($timeout)) {
            throw new \InvalidArgumentException('BOOKURIER_TIMEOUT must be numeric.');
        }

        if ($clientId !== false && $clientId !== '' && ! ctype_digit($clientId)) {
            throw new \InvalidArgumentException('BOOKURIER_CLIENT_ID must be an integer.');
        }

        return new self(
            userId: (string) (getenv('BOOKURIER_USERNAME') ?: getenv('BOOKURIER_USERID')),
            password: (string) getenv('BOOKURIER_PASSWORD'),
            baseUrl: getenv('BOOKURIER_BASE_URL') ?: self::BASE_URL,
            timeout: $timeout === false || $timeout === '' ? null : (float) $timeout,
            clientId: $clientId === false || $clientId === '' ? null : (int) $clientId,
        );
    }

    public function withCredentials(string $userId, string $password): self
    {
        return new self($userId, $password, $this->baseUrl, $this->timeout, $this->clientId);
    }

    public function withTimeout(?float $timeout): self
    {
        return new self($this->userId, $this->password, $this->baseUrl, $timeout, $this->clientId);
    }

    public function withClientId(?int $clientId = null): self
    {
        return new self($this->userId, $this->password, $this->baseUrl, $this->timeout, $clientId);
    }
}
