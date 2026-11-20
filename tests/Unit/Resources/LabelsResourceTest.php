<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use Daika7ana\Bookurier\Tests\Support\FakeHttpClient;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class LabelsResourceTest extends TestCase
{
    public function testHtmlByAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->labels()->html('000112870071');

        $this->assertRequest($httpClient, 'get_AWB.php', '<msg><cmd><awb>000112870071</awb></cmd></msg>');
        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertSame('text/html; charset=UTF-8', $document->mimeType);
        self::assertFalse($document->isPdf());
    }

    public function testPdfByAwb(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::pdf());

        $document = $client->labels()->pdf('000112870071');

        $this->assertRequest($httpClient, 'get_AWB_pdf.php', '<msg><cmd><awb>000112870071</awb></cmd></msg>');
        self::assertSame(Fixture::PDF_BODY, $document->contents);
        self::assertSame('application/pdf', $document->mimeType);
        self::assertTrue($document->isPdf());
    }

    public function testPdfRejectsANonPdfBody(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $this->expectException(UnexpectedResponseException::class);

        $client->labels()->pdf('000112870071');
    }

    public function testPdfByUniqueIdRejectsANonPdfBody(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $this->expectException(UnexpectedResponseException::class);

        $client->labels()->pdfByUniqueId('A4343244');
    }

    public function testHtmlByUniqueId(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->labels()->htmlByUniqueId('A4343244');

        $this->assertRequest($httpClient, 'get_AWB_unq.php', '<msg><cmd><unq>A4343244</unq></cmd></msg>');
        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertFalse($document->isPdf());
    }

    public function testPdfByUniqueId(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::pdf());

        $document = $client->labels()->pdfByUniqueId('A4343244');

        $this->assertRequest($httpClient, 'get_AWB_unq_pdf.php', '<msg><cmd><unq>A4343244</unq></cmd></msg>');
        self::assertSame(Fixture::PDF_BODY, $document->contents);
        self::assertSame('application/pdf', $document->mimeType);
        self::assertTrue($document->isPdf());
    }

    public function testDailySendsNoMessage(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->labels()->daily();

        $this->assertRequest($httpClient, 'get_AWB_list.php');
        self::assertArrayNotHasKey('msg', $this->form($httpClient));
        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertFalse($document->isPdf());
    }

    public function testMinusOneBecomesAnApiException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('-1'));

        $this->expectException(ApiException::class);

        $client->labels()->html('000112870071');
    }

    private function assertRequest(FakeHttpClient $httpClient, string $path, ?string $message = null): void
    {
        $request = $httpClient->lastRequest;

        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://www.bookurier.ro/colete/serv/' . $path, (string) $request->getUri());
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        if ($message !== null) {
            self::assertSame($message, $this->form($httpClient)['msg']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function form(FakeHttpClient $httpClient): array
    {
        self::assertNotNull($httpClient->lastRequest);

        parse_str((string) $httpClient->lastRequest->getBody(), $form);

        /** @var array<string, string> $form */
        return $form;
    }
}
