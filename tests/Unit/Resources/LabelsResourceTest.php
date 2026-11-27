<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Resources;

use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use Daika7ana\Bookurier\Tests\Support\TestClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class LabelsResourceTest extends TestCase
{
    public function testPdfPostsOneAwbAsJsonAndReturnsThePdf(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::pdf());

        $document = $client->labels()->pdf('BOK6000123456');

        $request = $httpClient->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://portal.bookurier.ro/api/print_awbs.php', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'user' => 'test-user',
            'pwd' => 'test-password',
            'data' => ['BOK6000123456'],
            'format' => 'pdf',
            'page' => 0,
        ], $httpClient->lastJson());

        self::assertTrue($document->isPdf());
    }

    public function testHtmlPostsTheListModeAndPage(): void
    {
        [$client, $httpClient] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::html());

        $document = $client->labels()->html(['BOK6000123456', 'BOK6000123457'], 's', 2);

        self::assertSame([
            'user' => 'test-user',
            'pwd' => 'test-password',
            'data' => ['BOK6000123456', 'BOK6000123457'],
            'format' => 'html',
            'mode' => 's',
            'page' => 2,
        ], $httpClient->lastJson());

        self::assertSame(Fixture::HTML_BODY, $document->contents);
    }

    public function testAJsonErrorEnvelopeBecomesAValidationException(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::error('Invalid request'));

        $this->expectException(ValidationException::class);

        $client->labels()->pdf('BOK6000123456');
    }

    public function testRateLimitBecomesAnUnexpectedStatusExceptionWithRetryAfter(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::rateLimited(30));

        try {
            $client->labels()->pdf('BOK6000123456');

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testHtmlRejectsANonHtmlNonJsonBody(): void
    {
        [$client] = TestClientFactory::create(static fn(): ResponseInterface => Fixture::response('', 'text/html'));

        $this->expectException(UnexpectedStatusException::class);

        $client->labels()->html('BOK6000123456');
    }
}
