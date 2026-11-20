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
        'BOOKURIER_USERID',
        'BOOKURIER_PASSWORD',
        'BOOKURIER_BASE_URL',
        'BOOKURIER_TIMEOUT',
        'BOOKURIER_CLIENT_ID',
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
        self::assertSame(ClientConfig::BASE_URL, $config->baseUrl);
        self::assertSame('https://www.bookurier.ro/colete/serv', $config->baseUrl);
        self::assertNull($config->timeout);
        self::assertNull($config->clientId);
    }

    public function testFromEnvironmentReadsEveryVariable(): void
    {
        putenv('BOOKURIER_USERID=env-user');
        putenv('BOOKURIER_PASSWORD=env-pass');
        putenv('BOOKURIER_BASE_URL=https://example.test/api');
        putenv('BOOKURIER_TIMEOUT=12.5');

        $config = ClientConfig::fromEnvironment();

        self::assertSame('env-user', $config->userId);
        self::assertSame('env-pass', $config->password);
        self::assertSame('https://example.test/api', $config->baseUrl);
        self::assertSame(12.5, $config->timeout);
        self::assertNull($config->clientId);
    }

    public function testFromEnvironmentPrefersTheUsernameVariable(): void
    {
        putenv('BOOKURIER_USERNAME=env-user');
        putenv('BOOKURIER_USERID=legacy-user');

        self::assertSame('env-user', ClientConfig::fromEnvironment()->userId);
    }

    public function testFromEnvironmentStillReadsTheLegacyUserId(): void
    {
        putenv('BOOKURIER_USERID=legacy-user');

        self::assertSame('legacy-user', ClientConfig::fromEnvironment()->userId);
    }

    public function testFromEnvironmentReadsTheClientId(): void
    {
        putenv('BOOKURIER_CLIENT_ID=1234');

        $config = ClientConfig::fromEnvironment();

        self::assertSame(1234, $config->clientId);
    }

    public function testFromEnvironmentRejectsANonNumericTimeout(): void
    {
        putenv('BOOKURIER_TIMEOUT=soon');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BOOKURIER_TIMEOUT must be numeric.');

        ClientConfig::fromEnvironment();
    }

    public function testFromEnvironmentRejectsANonNumericClientId(): void
    {
        putenv('BOOKURIER_CLIENT_ID=abc');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BOOKURIER_CLIENT_ID must be an integer.');

        ClientConfig::fromEnvironment();
    }

    public function testFromEnvironmentTreatsEmptyValuesAsUnset(): void
    {
        putenv('BOOKURIER_TIMEOUT=');
        putenv('BOOKURIER_CLIENT_ID=');

        $config = ClientConfig::fromEnvironment();

        self::assertNull($config->timeout);
        self::assertNull($config->clientId);
    }

    public function testFromEnvironmentFallsBackToDefaults(): void
    {
        $config = ClientConfig::fromEnvironment();

        self::assertSame('', $config->userId);
        self::assertSame('', $config->password);
        self::assertSame(ClientConfig::BASE_URL, $config->baseUrl);
        self::assertNull($config->timeout);
    }

    public function testWithCredentialsReturnsANewInstance(): void
    {
        $config = new ClientConfig(baseUrl: 'https://example.test', timeout: 5.0);
        $updated = $config->withCredentials('user', 'pass');

        self::assertNotSame($config, $updated);
        self::assertSame('user', $updated->userId);
        self::assertSame('pass', $updated->password);
        self::assertSame('https://example.test', $updated->baseUrl);
        self::assertSame(5.0, $updated->timeout);
        self::assertSame('', $config->userId);
    }

    public function testWithCredentialsAndTimeoutKeepTheClientId(): void
    {
        $config = new ClientConfig(clientId: 1234);

        self::assertSame(1234, $config->withCredentials('user', 'pass')->clientId);
        self::assertSame(1234, $config->withTimeout(3.0)->clientId);
    }

    public function testWithTimeoutSetsAndClears(): void
    {
        $config = new ClientConfig(userId: 'user', password: 'pass');

        $timed = $config->withTimeout(3.0);
        self::assertSame(3.0, $timed->timeout);
        self::assertSame('user', $timed->userId);
        self::assertSame('pass', $timed->password);

        $cleared = $timed->withTimeout(null);
        self::assertNull($cleared->timeout);
        self::assertSame(3.0, $timed->timeout);
    }

    public function testWithClientIdReturnsANewInstanceAndKeepsTheOtherFields(): void
    {
        $config = new ClientConfig(userId: 'user', password: 'pass', baseUrl: 'https://example.test', timeout: 5.0);

        $updated = $config->withClientId(1234);

        self::assertNotSame($config, $updated);
        self::assertSame(1234, $updated->clientId);
        self::assertSame('user', $updated->userId);
        self::assertSame('pass', $updated->password);
        self::assertSame('https://example.test', $updated->baseUrl);
        self::assertSame(5.0, $updated->timeout);
        self::assertNull($config->clientId);
        self::assertNull($updated->withClientId()->clientId);
    }
}
