<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Config;

use Daika7ana\Bookurier\Config\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $environment = [];

    private const VARIABLES = [
        'BOOKURIER_USERNAME',
        'BOOKURIER_PASSWORD',
        'BOOKURIER_BASE_URL',
        'BOOKURIER_TIMEOUT',
        'BOOKURIER_PICKUP_POINT',
        'BOOKURIER_TRACKING_KEY',
    ];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $name) {
            $value = $this->environment[$name];
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testDefaults(): void
    {
        $config = new ClientConfig();

        self::assertSame('', $config->userId);
        self::assertSame('', $config->password);
        self::assertSame('https://portal.bookurier.ro/api', $config->baseUrl);
        self::assertSame(ClientConfig::BASE_URL, $config->baseUrl);
        self::assertNull($config->timeout);
        self::assertNull($config->pickupPoint);
        self::assertNull($config->trackingKey);
    }

    public function testFromEnvironmentReadsEveryVariable(): void
    {
        putenv('BOOKURIER_USERNAME=env-user');
        putenv('BOOKURIER_PASSWORD=env-pass');
        putenv('BOOKURIER_BASE_URL=https://example.test/api');
        putenv('BOOKURIER_TIMEOUT=12.5');
        putenv('BOOKURIER_PICKUP_POINT=5');
        putenv('BOOKURIER_TRACKING_KEY=track-key');

        $config = ClientConfig::fromEnvironment();

        self::assertSame('env-user', $config->userId);
        self::assertSame('env-pass', $config->password);
        self::assertSame('https://example.test/api', $config->baseUrl);
        self::assertSame(12.5, $config->timeout);
        self::assertSame('5', $config->pickupPoint);
        self::assertSame('track-key', $config->trackingKey);
    }

    public function testFromEnvironmentFallsBackToTheDefaultTrackingKey(): void
    {
        $config = ClientConfig::fromEnvironment();

        self::assertSame('7BzvpY3PuANhhMajURYWn', $config->trackingKey);
        self::assertSame(ClientConfig::DEFAULT_TRACKING_KEY, $config->trackingKey);
        self::assertNull($config->pickupPoint);
    }

    public function testFromEnvironmentRejectsANonNumericTimeout(): void
    {
        putenv('BOOKURIER_TIMEOUT=soon');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BOOKURIER_TIMEOUT must be numeric.');

        ClientConfig::fromEnvironment();
    }

    public function testFromEnvironmentTreatsEmptyValuesAsUnset(): void
    {
        putenv('BOOKURIER_TIMEOUT=');
        putenv('BOOKURIER_PICKUP_POINT=');
        putenv('BOOKURIER_TRACKING_KEY=');

        $config = ClientConfig::fromEnvironment();

        self::assertNull($config->timeout);
        self::assertNull($config->pickupPoint);
        self::assertSame(ClientConfig::DEFAULT_TRACKING_KEY, $config->trackingKey);
    }

    public function testWithCredentialsReturnsANewInstance(): void
    {
        $config = new ClientConfig(baseUrl: 'https://example.test', timeout: 5.0, pickupPoint: '5', trackingKey: 'key');
        $updated = $config->withCredentials('user', 'pass');

        self::assertNotSame($config, $updated);
        self::assertSame('user', $updated->userId);
        self::assertSame('pass', $updated->password);
        self::assertSame('https://example.test', $updated->baseUrl);
        self::assertSame(5.0, $updated->timeout);
        self::assertSame('5', $updated->pickupPoint);
        self::assertSame('key', $updated->trackingKey);
        self::assertSame('', $config->userId);
    }

    public function testWithTimeoutSetsAndClears(): void
    {
        $config = new ClientConfig(userId: 'user', password: 'pass', pickupPoint: '5');

        $timed = $config->withTimeout(3.0);
        self::assertSame(3.0, $timed->timeout);
        self::assertSame('user', $timed->userId);
        self::assertSame('5', $timed->pickupPoint);

        self::assertNull($timed->withTimeout(null)->timeout);
    }

    public function testWithPickupPointAndTrackingKeyKeepTheOtherFields(): void
    {
        $config = new ClientConfig(userId: 'user', password: 'pass', baseUrl: 'https://example.test', timeout: 5.0);

        $updated = $config->withPickupPoint('5')->withTrackingKey('key');

        self::assertSame('5', $updated->pickupPoint);
        self::assertSame('key', $updated->trackingKey);
        self::assertSame('user', $updated->userId);
        self::assertSame('pass', $updated->password);
        self::assertSame('https://example.test', $updated->baseUrl);
        self::assertSame(5.0, $updated->timeout);
        self::assertNull($config->pickupPoint);
        self::assertNull($config->trackingKey);
        self::assertNull($updated->withPickupPoint(null)->pickupPoint);
        self::assertNull($updated->withTrackingKey(null)->trackingKey);
    }

    public function testRequirePickupPointPrefersTheExplicitValue(): void
    {
        $config = new ClientConfig(pickupPoint: '5');

        self::assertSame('7', $config->requirePickupPoint('7'));
    }

    public function testRequirePickupPointFallsBackToTheConfigWhenTheExplicitValueIsBlank(): void
    {
        $config = new ClientConfig(pickupPoint: '5');

        self::assertSame('5', $config->requirePickupPoint());
        self::assertSame('5', $config->requirePickupPoint(''));
        self::assertSame('5', $config->requirePickupPoint('  '));
    }

    public function testRequirePickupPointThrowsWhenNeitherIsSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bookurier requires a pickup point: pass one, set Order::$pickupPoint, or set ClientConfig::pickupPoint (BOOKURIER_PICKUP_POINT).');

        (new ClientConfig())->requirePickupPoint(' ');
    }
}
