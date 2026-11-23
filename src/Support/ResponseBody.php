<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

final class ResponseBody
{
    private function __construct() {}

    /**
     * @throws AuthenticationException `-1`, or HTTP 401 without an in-band signal
     * @throws ValidationException `-2`, `-3`, `-5`, or HTTP 400/422 without one
     * @throws UnexpectedStatusException any other negative code or non-2xx status, or an empty body
     */
    public static function text(ResponseInterface $response): string
    {
        $body = self::body($response);
        self::rejectByStatus($response);

        if ($body === '') {
            throw new UnexpectedStatusException(
                $response,
                'Bookurier returned an empty response body.',
                retryAfter: self::retryAfter($response),
            );
        }

        return $body;
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    public static function xml(ResponseInterface $response): XmlResponse
    {
        $body = self::body($response);

        if ($body === '') {
            self::rejectByStatus($response);

            throw new UnexpectedStatusException(
                $response,
                'Bookurier returned an empty response body.',
                retryAfter: self::retryAfter($response),
            );
        }

        try {
            $xml = XmlResponse::from($body, $response);
        } catch (UnexpectedStatusException $e) {
            // A non-2xx status outranks an unparseable body, but not an in-band <err>.
            self::rejectByStatus($response);

            throw $e;
        }

        $error = $xml->error();

        if ($error !== null) {
            self::throwInBandError(BookurierError::fromArray(['message' => $error]), $response, validationByDefault: true);
        }

        self::rejectByStatus($response);

        return $xml;
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    public static function success(ResponseInterface $response): void
    {
        $body = self::text($response);

        if ($body !== '1') {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Expected "1" for success. Got: "%s".', substr($body, 0, 200)),
                retryAfter: self::retryAfter($response),
            );
        }
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    public static function document(ResponseInterface $response, ?string $expected = null): Document
    {
        $contents = (string) $response->getBody();

        self::rejectNegativeCode(trim($contents), $response);
        self::rejectByStatus($response);

        if ($contents === '') {
            throw new UnexpectedStatusException(
                $response,
                'Bookurier returned an empty document.',
                retryAfter: self::retryAfter($response),
            );
        }

        if (self::isChallenge($contents)) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Bookurier returned an HTML challenge page instead of the API response; wait a few minutes and retry. Got: "%s".', substr($contents, 0, 200)),
                retryAfter: self::retryAfter($response),
            );
        }

        if ($expected === 'pdf' && ! str_starts_with(ltrim($contents), '%PDF')) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Expected a PDF document. Got: "%s".', substr($contents, 0, 200)),
                retryAfter: self::retryAfter($response),
            );
        }

        $mimeType = $response->getHeaderLine('Content-Type');

        return new Document($contents, $mimeType === '' ? 'application/octet-stream' : $mimeType);
    }

    /**
     * Trims the body and lets an in-band negative code win over the status.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    private static function body(ResponseInterface $response): string
    {
        $body = trim((string) $response->getBody());

        self::rejectNegativeCode($body, $response);

        return $body;
    }

    /**
     * Standard HTTP mapping, used only when the body carries no in-band signal.
     *
     * @throws AuthenticationException on 401
     * @throws ValidationException on 400 or 422
     * @throws UnexpectedStatusException on any other non-2xx status
     */
    private static function rejectByStatus(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return;
        }

        $retryAfter = self::retryAfter($response);
        $message = sprintf('Bookurier returned HTTP %d.', $status);

        if ($status === 401) {
            throw new AuthenticationException($message, response: $response, retryAfter: $retryAfter);
        }

        if ($status === 400 || $status === 422) {
            throw new ValidationException([], $message, response: $response, retryAfter: $retryAfter);
        }

        throw new UnexpectedStatusException($response, $message, retryAfter: $retryAfter);
    }

    public static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
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
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    private static function rejectNegativeCode(string $body, ResponseInterface $response): void
    {
        if (preg_match('/^-\d+$/', $body) !== 1) {
            return;
        }

        $code = (int) $body;
        $message = match ($code) {
            -1 => 'Bookurier returned -1: the request was rejected (bad credentials or malformed msg).',
            -2 => 'Bookurier returned -2: the record was not found, or the request data is invalid.',
            -3 => 'Bookurier returned -3: the request was incomplete (a label request without its msg envelope).',
            -5 => 'Bookurier returned -5: the service rejected the order (observed when <district> is missing or empty).',
            default => 'Bookurier returned ' . $code . ': the request was rejected.',
        };

        self::throwInBandError(BookurierError::fromArray(['code' => $code, 'message' => $message]), $response);
    }

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    private static function throwInBandError(BookurierError $error, ResponseInterface $response, bool $validationByDefault = false): never
    {
        $retryAfter = self::retryAfter($response);

        if ($error->isAuthenticationRelated()) {
            throw new AuthenticationException(
                $error->message,
                response: $response,
                retryAfter: $retryAfter,
                error: $error,
            );
        }

        if ($validationByDefault || $error->isValidationRelated()) {
            throw new ValidationException(
                errors: [],
                message: $error->message,
                response: $response,
                retryAfter: $retryAfter,
                error: $error,
            );
        }

        throw new UnexpectedStatusException($response, $error->message, retryAfter: $retryAfter, error: $error);
    }
}
