<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseBodyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function negativeCodes(): iterable
    {
        yield '-2' => ['-2'];
        yield '-3' => ['-3'];
    }

    public function testTextReturnsTrimmedBody(): void
    {
        self::assertSame('000112870071', ResponseBody::text(Fixture::response("  000112870071\n")));
    }

    #[DataProvider('negativeCodes')]
    public function testTextRejectsNegativeCodes(string $code): void
    {
        try {
            ResponseBody::text(Fixture::response($code));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertStringContainsString($code, $e->getMessage());
            self::assertSame(200, $e->getCode());
            self::assertNotNull($e->response);
            self::assertSame((int) $code, $e->error?->code);
        }
    }

    public function testTextRejectsMinusOneAsAuthenticationFailure(): void
    {
        try {
            ResponseBody::text(Fixture::response('-1'));

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertStringContainsString('-1', $e->getMessage());
            self::assertSame(200, $e->getCode());
            self::assertNotNull($e->response);
            self::assertNotNull($e->error);
            self::assertTrue($e->error->isAuthenticationRelated());
        }
    }

    public function testInBandErrorCodeIsTheHttpStatus(): void
    {
        try {
            ResponseBody::text(Fixture::response('-2', status: 502));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame(502, $e->getCode());
            self::assertSame(-2, $e->error?->code);
        }
    }

    public function testRejectedCodeCarriesRetryAfter(): void
    {
        $response = new Response(200, ['Retry-After' => '30'], '-1');

        try {
            ResponseBody::text($response);

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testTextRejectsEmptyBody(): void
    {
        try {
            ResponseBody::text(Fixture::response('   '));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertNotNull($e->response);
        }
    }

    public function testXmlRejectsMinusOne(): void
    {
        $this->expectException(AuthenticationException::class);

        ResponseBody::xml(Fixture::response('-1'));
    }

    public function testXmlThrowsTheInBandErrorMessage(): void
    {
        try {
            ResponseBody::xml(Fixture::error('AWB inexistent'));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('AWB inexistent', $e->getMessage());
            self::assertSame('AWB inexistent', $e->error?->message);
            self::assertNotNull($e->response);
        }
    }

    public function testXmlAcceptsEmptyErrorElement(): void
    {
        $xml = ResponseBody::xml(Fixture::xml('<msg><cmd><awb>1</awb></cmd><err></err></msg>'));

        self::assertNull($xml->error());
        self::assertSame(['awb' => '1'], $xml->cmd());
    }

    public function testBareUnauthorizedStatusBecomesAnAuthenticationException(): void
    {
        try {
            ResponseBody::text(Fixture::response('<html>Unauthorized</html>', status: 401));

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->getCode());
            self::assertSame(401, $e->response?->getStatusCode());
            self::assertNull($e->error);
        }
    }

    #[DataProvider('validationStatuses')]
    public function testBareValidationStatusesBecomeValidationExceptions(int $status): void
    {
        try {
            ResponseBody::text(Fixture::response('{"message":"bad request"}', status: $status));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame($status, $e->getCode());
            self::assertNull($e->error);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function validationStatuses(): iterable
    {
        yield '400' => [400];
        yield '422' => [422];
    }

    public function testOtherNonSuccessStatusBecomesAnUnexpectedStatusException(): void
    {
        try {
            ResponseBody::text(Fixture::response('gateway', status: 500));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertSame(500, $e->getCode());
        }
    }

    public function testInBandCodeBeatsTheStatus(): void
    {
        try {
            ResponseBody::text(Fixture::response('-1', status: 401));

            self::fail('Expected AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->getCode());
            self::assertSame(-1, $e->error?->code);
        }
    }

    public function testInBandXmlErrorBeatsTheStatus(): void
    {
        try {
            ResponseBody::xml(Fixture::error('AWB inexistent', status: 401));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertSame('AWB inexistent', $e->getMessage());
            self::assertSame(401, $e->getCode());
        }
    }

    public function testXmlRejectsNonXmlBody(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::xml(Fixture::response('plain text, not XML'));
    }

    public function testXmlShapeErrorCarriesTheResponse(): void
    {
        try {
            ResponseBody::xml(Fixture::response('plain text, not XML', status: 502));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertSame(502, $e->response?->getStatusCode());
            self::assertSame(502, $e->getCode());
        }
    }

    public function testSuccessAcceptsOne(): void
    {
        ResponseBody::success(Fixture::success());

        $this->addToAssertionCount(1);
    }

    public function testSuccessRejectsAnythingButOne(): void
    {
        try {
            ResponseBody::success(Fixture::response('0'));

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('Expected "1"', $e->getMessage());
            self::assertStringContainsString('Got: "0".', $e->getMessage());
        }
    }

    public function testSuccessPropagatesInBandErrors(): void
    {
        $this->expectException(ValidationException::class);

        ResponseBody::success(Fixture::response('-2'));
    }

    public function testDocumentKeepsContentsAndContentType(): void
    {
        $document = ResponseBody::document(Fixture::pdf());

        self::assertSame(Fixture::PDF_BODY, $document->contents);
        self::assertSame('application/pdf', $document->mimeType);
        self::assertTrue($document->isPdf());
    }

    public function testDocumentFallsBackToOctetStreamContentType(): void
    {
        $response = Fixture::response(Fixture::HTML_BODY);
        $response = $response->withoutHeader('Content-Type');

        $document = ResponseBody::document($response);

        self::assertSame('application/octet-stream', $document->mimeType);
        self::assertFalse($document->isPdf());
    }

    #[DataProvider('negativeCodes')]
    public function testDocumentRejectsNegativeCodes(string $code): void
    {
        try {
            ResponseBody::document(Fixture::response($code));

            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertStringContainsString($code, $e->getMessage());
        }
    }

    public function testDocumentRejectsEmptyBody(): void
    {
        $this->expectException(UnexpectedStatusException::class);

        ResponseBody::document(Fixture::response(''));
    }

    public function testDocumentRejectsChallengePage(): void
    {
        try {
            ResponseBody::document(Fixture::challenge());

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('HTML challenge page', $e->getMessage());
        }
    }

    public function testDocumentRejectsChallengePageEvenForPdf(): void
    {
        $this->expectException(UnexpectedStatusException::class);
        $this->expectExceptionMessage('HTML challenge page');

        ResponseBody::document(Fixture::challenge(), 'pdf');
    }

    public function testDocumentAcceptsPdfWhenExpected(): void
    {
        $document = ResponseBody::document(Fixture::pdf(), 'pdf');

        self::assertSame(Fixture::PDF_BODY, $document->contents);
        self::assertTrue($document->isPdf());
    }

    public function testDocumentRejectsNonPdfWhenExpected(): void
    {
        try {
            ResponseBody::document(Fixture::html(), 'pdf');

            self::fail('Expected UnexpectedStatusException.');
        } catch (UnexpectedStatusException $e) {
            self::assertStringContainsString('Expected a PDF document.', $e->getMessage());
        }
    }

    public function testDocumentRejectsWhitespaceWrappedNegativeCode(): void
    {
        $this->expectException(AuthenticationException::class);

        ResponseBody::document(Fixture::whitespaceNegative());
    }

    public function testTextExplainsTheMinusThreeCode(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bookurier returned -3: the request was incomplete (a label request without its msg envelope).');

        ResponseBody::text(Fixture::response('-3'));
    }
}
