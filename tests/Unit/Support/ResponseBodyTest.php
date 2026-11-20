<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Support;

use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\Bookurier\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseBodyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function negativeCodes(): iterable
    {
        yield '-1' => ['-1'];
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

            self::fail('Expected ApiException.');
        } catch (ApiException $e) {
            self::assertStringContainsString($code, $e->getMessage());
        }
    }

    public function testTextRejectsEmptyBody(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        ResponseBody::text(Fixture::response('   '));
    }

    public function testXmlRejectsMinusOne(): void
    {
        $this->expectException(ApiException::class);

        ResponseBody::xml(Fixture::response('-1'));
    }

    public function testXmlThrowsTheInBandErrorMessage(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('AWB inexistent');

        ResponseBody::xml(Fixture::error('AWB inexistent'));
    }

    public function testXmlAcceptsEmptyErrorElement(): void
    {
        $xml = ResponseBody::xml(Fixture::xml('<msg><cmd><awb>1</awb></cmd><err></err></msg>'));

        self::assertNull($xml->error());
        self::assertSame(['awb' => '1'], $xml->cmd());
    }

    public function testXmlRejectsNonXmlBody(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        ResponseBody::xml(Fixture::response('plain text, not XML'));
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

            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $e) {
            self::assertStringContainsString('Expected "1"', $e->getMessage());
            self::assertStringContainsString('Got: "0".', $e->getMessage());
        }
    }

    public function testSuccessPropagatesApiError(): void
    {
        $this->expectException(ApiException::class);

        ResponseBody::success(Fixture::response('-1'));
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

            self::fail('Expected ApiException.');
        } catch (ApiException $e) {
            self::assertStringContainsString($code, $e->getMessage());
        }
    }

    public function testDocumentRejectsEmptyBody(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        ResponseBody::document(Fixture::response(''));
    }

    public function testDocumentRejectsChallengePage(): void
    {
        try {
            ResponseBody::document(Fixture::challenge());

            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $e) {
            self::assertStringContainsString('HTML challenge page', $e->getMessage());
        }
    }

    public function testDocumentRejectsChallengePageEvenForPdf(): void
    {
        $this->expectException(UnexpectedResponseException::class);
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

            self::fail('Expected UnexpectedResponseException.');
        } catch (UnexpectedResponseException $e) {
            self::assertStringContainsString('Expected a PDF document.', $e->getMessage());
        }
    }

    public function testDocumentRejectsWhitespaceWrappedNegativeCode(): void
    {
        $this->expectException(ApiException::class);

        ResponseBody::document(Fixture::whitespaceNegative());
    }

    public function testTextExplainsTheMinusThreeCode(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Bookurier returned -3: the request was incomplete (a label request without its msg envelope).');

        ResponseBody::text(Fixture::response('-3'));
    }
}
