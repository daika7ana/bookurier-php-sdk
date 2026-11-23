<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ReportsResourceTest extends TestCase
{
    public function testDailyBorderoReturnsHtmlWithoutAMessage(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->reports()->dailyBordero();

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://www.bookurier.ro/colete/serv/get_cmd_list.php', (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $form);
        self::assertArrayNotHasKey('msg', $form);

        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertSame('text/html; charset=UTF-8', $document->mimeType);
        self::assertFalse($document->isPdf());
    }

    public function testEmptyBodyBecomesAnUnexpectedStatusException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response(''));

        $this->expectException(UnexpectedStatusException::class);

        $client->reports()->dailyBordero();
    }
}
