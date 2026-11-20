<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

use Daika7ana\Bookurier\Exceptions\ApiException;
use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use Psr\Http\Message\ResponseInterface;

final class ResponseBody
{
    private function __construct() {}

    /**
     * @throws ApiException when Bookurier answers a negative error code
     * @throws UnexpectedResponseException when the body is empty
     */
    public static function text(ResponseInterface $response): string
    {
        $body = trim((string) $response->getBody());

        self::rejectNegativeCode($body);

        if ($body === '') {
            throw new UnexpectedResponseException('Bookurier returned an empty response body.');
        }

        return $body;
    }

    /**
     * @throws ApiException
     * @throws UnexpectedResponseException
     */
    public static function xml(ResponseInterface $response): XmlResponse
    {
        $xml = XmlResponse::from(self::text($response));
        $error = $xml->error();

        if ($error !== null) {
            throw new ApiException($error);
        }

        return $xml;
    }

    /**
     * @throws ApiException
     * @throws UnexpectedResponseException
     */
    public static function success(ResponseInterface $response): void
    {
        $body = self::text($response);

        if ($body !== '1') {
            throw UnexpectedResponseException::withBody('Expected "1" for success.', $body);
        }
    }

    /**
     * @throws ApiException
     * @throws UnexpectedResponseException
     */
    public static function document(ResponseInterface $response, ?string $expected = null): Document
    {
        $contents = (string) $response->getBody();

        self::rejectNegativeCode(trim($contents));

        if ($contents === '') {
            throw new UnexpectedResponseException('Bookurier returned an empty document.');
        }

        if (self::isChallenge($contents)) {
            throw UnexpectedResponseException::withBody(
                'Bookurier returned an HTML challenge page instead of the API response; wait a few minutes and retry.',
                $contents,
            );
        }

        if ($expected === 'pdf' && ! str_starts_with(ltrim($contents), '%PDF')) {
            throw UnexpectedResponseException::withBody('Expected a PDF document.', $contents);
        }

        $mimeType = $response->getHeaderLine('Content-Type');

        return new Document($contents, $mimeType === '' ? 'application/octet-stream' : $mimeType);
    }

    private static function isChallenge(string $contents): bool
    {
        foreach (['One moment, please', 'Just a moment', 'being verified', 'cf-chl', 'challenge-platform'] as $marker) {
            if (stripos($contents, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bookurier reports errors as a bare negative integer in the body.
     *
     * @throws ApiException
     */
    private static function rejectNegativeCode(string $body): void
    {
        if (preg_match('/^-\d+$/', $body, $matches) !== 1) {
            return;
        }

        $code = $matches[0];

        throw new ApiException(match ($code) {
            '-1' => 'Bookurier returned -1: the request was rejected (bad credentials or malformed msg).',
            '-2' => 'Bookurier returned -2: the record was not found, or the request data is invalid.',
            '-3' => 'Bookurier returned -3: the request was incomplete (a label request without its msg envelope).',
            '-5' => 'Bookurier returned -5: the service rejected the order (observed when <district> is missing or empty).',
            default => 'Bookurier returned ' . $code . ': the request was rejected.',
        });
    }
}
