<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\Document;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseBodyTest extends TestCase
{
    public function testJsonExposesTheEnvelope(): void
    {
        $body = ResponseBody::json(Fixture::batchSuccess());

        self::assertSame(['BOK6000123456', 'BOK6000123457'], $body->data());
        self::assertSame([
            'CMD-10001' => 'BOK6000123456',
            'CMD-10002' => 'BOK6000123457',
        ], $body->map());
        self::assertSame([], $body->errors());
        self::assertSame('ok', $body->message());
        self::assertSame(200, $body->response()->getStatusCode());
    }

    public function testJsonExposesPerOrderErrors(): void
    {
        $errors = ResponseBody::json(Fixture::batchPartial())->errors();

        self::assertCount(1, $errors);
        self::assertSame('CMD-10002', $errors[0]['order']);
        self::assertSame('Judet invalid', $errors[0]['message']);
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function errorMessages(): iterable
    {
        yield 'invalid user' => ['Invalid user', AuthenticationException::class];
        yield 'login fail' => ['Login fail', AuthenticationException::class];
        yield 'login fail lowercase' => ['LOGIN FAIL', AuthenticationException::class];
        yield 'invalid request' => ['Invalid request', ValidationException::class];
        yield 'judet invalid' => ['Judet invalid: Prahova', ValidationException::class];
        yield 'server error' => ['Server error', ValidationException::class];
    }

    /**
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('errorMessages')]
    public function testStatusErrorClassifiesByMessage(string $message, string $exception): void
    {
        try {
            ResponseBody::json(Fixture::error($message));

            self::fail('Expected ' . $exception);
        } catch (\Throwable $e) {
            self::assertInstanceOf($exception, $e);
            self::assertSame($message, $e->getMessage());
            self::assertSame(200, $e->getCode());
        }
    }

    public function testRateLimitBecomesAnUnexpectedStatusExceptionWithRetryAfter(): void
    {
        try {
            ResponseBody::json(Fixture::rateLimited(42));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertSame(429, $e->getCode());
            self::assertSame(42, $e->retryAfter);
        }
    }

    public function testANonJsonBodyBecomesAnUnexpectedStatusException(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::json(Fixture::response('not json'));
    }

    public function testAnEmptyBodyBecomesAnUnexpectedStatusException(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::json(Fixture::response(''));
    }

    public function testDocumentReturnsRawHtml(): void
    {
        $document = ResponseBody::document(Fixture::html());

        self::assertInstanceOf(Document::class, $document);
        self::assertSame(Fixture::HTML_BODY, $document->contents);
        self::assertSame('text/html; charset=UTF-8', $document->mimeType);
    }

    public function testDocumentReturnsRawPdf(): void
    {
        $document = ResponseBody::document(Fixture::pdf(), 'pdf');

        self::assertTrue($document->isPdf());
    }

    public function testDocumentRejectsANonPdfBodyWhenPdfIsExpected(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::document(Fixture::html(), 'pdf');
    }

    public function testDocumentClassifiesAJsonErrorEnvelope(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No data');

        ResponseBody::document(Fixture::error('No data'));
    }

    public function testDocumentRejectsAJsonSuccessBody(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::document(Fixture::pickup());
    }

    public function testDocumentRejectsAChallengePage(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::document(Fixture::challenge());
    }

    public function testRetryAfterIsNullWithoutTheHeader(): void
    {
        self::assertNull(ResponseBody::retryAfter(Fixture::response('ok')));
    }
}
