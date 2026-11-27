<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Config;

final readonly class ClientConfig
{
    public const BASE_URL = 'https://portal.bookurier.ro/api';

    public const DEFAULT_TRACKING_KEY = '7BzvpY3PuANhhMajURYWn';

    /**
     * @param float|null $timeout Per-request timeout in seconds. Null lets the bundled transport apply its
     *                            30 s request / 10 s connect defaults.
     * @param string|null $pickupPoint Default pickup point number, used when an order has none.
     * @param string|null $trackingKey Shared awb_history key; falls back to DEFAULT_TRACKING_KEY.
     */
    public function __construct(
        public string $userId = '',
        public string $password = '',
        public string $baseUrl = self::BASE_URL,
        public ?float $timeout = null,
        public ?string $pickupPoint = null,
        public ?string $trackingKey = null,
    ) {}

    public static function fromEnvironment(): self
    {
        $timeout = getenv('BOOKURIER_TIMEOUT');

        if ($timeout !== false && $timeout !== '' && ! is_numeric($timeout)) {
            throw new \InvalidArgumentException('BOOKURIER_TIMEOUT must be numeric.');
        }

        return new self(
            userId: (string) getenv('BOOKURIER_USERNAME'),
            password: (string) getenv('BOOKURIER_PASSWORD'),
            baseUrl: getenv('BOOKURIER_BASE_URL') ?: self::BASE_URL,
            timeout: $timeout === false || $timeout === '' ? null : (float) $timeout,
            pickupPoint: self::optional('BOOKURIER_PICKUP_POINT'),
            trackingKey: self::optional('BOOKURIER_TRACKING_KEY') ?? self::DEFAULT_TRACKING_KEY,
        );
    }

    public function withCredentials(string $userId, string $password): self
    {
        return new self($userId, $password, $this->baseUrl, $this->timeout, $this->pickupPoint, $this->trackingKey);
    }

    public function withTimeout(?float $timeout): self
    {
        return new self($this->userId, $this->password, $this->baseUrl, $timeout, $this->pickupPoint, $this->trackingKey);
    }

    public function withPickupPoint(?string $pickupPoint): self
    {
        return new self($this->userId, $this->password, $this->baseUrl, $this->timeout, $pickupPoint, $this->trackingKey);
    }

    public function withTrackingKey(?string $trackingKey): self
    {
        return new self($this->userId, $this->password, $this->baseUrl, $this->timeout, $this->pickupPoint, $trackingKey);
    }

    /**
     * The explicit pickup point when it is non-blank, else the configured default.
     *
     * @throws \InvalidArgumentException when neither is set
     */
    public function requirePickupPoint(?string $explicit = null): string
    {
        if ($explicit !== null && trim($explicit) !== '') {
            return $explicit;
        }

        if ($this->pickupPoint !== null && trim($this->pickupPoint) !== '') {
            return $this->pickupPoint;
        }

        throw new \InvalidArgumentException(
            'Bookurier requires a pickup point: pass one, set Order::$pickupPoint, or set ClientConfig::pickupPoint (BOOKURIER_PICKUP_POINT).',
        );
    }

    private static function optional(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
