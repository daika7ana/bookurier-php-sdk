<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class PickupResourceTest extends TestCase
{
    public function testRequestReturnsThePickupDocumentWithoutAMessage(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->pickup()->request();

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://www.bookurier.ro/colete/serv/request_delivery.php', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $form);
        self::assertArrayNotHasKey('msg', $form);

        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertSame('text/html; charset=UTF-8', $document->mimeType);
        self::assertFalse($document->isPdf());
    }

    public function testMinusOneBecomesAnApiException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('-1'));

        $this->expectException(ApiException::class);

        $client->pickup()->request();
    }
}
