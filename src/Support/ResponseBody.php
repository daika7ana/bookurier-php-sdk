<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

use Daika7ana\Bookurier\DTOs\Common\Errors\BookurierError;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Classifies Bookurier's JSON envelopes and raw documents.
 *
 * Status:error envelopes map "Invalid user"/"Login fail" to AuthenticationException
 * and everything else to ValidationException; a non-JSON body where JSON is
 * expected (or a challenge page) raises UnexpectedStatusException.
 */
final class ResponseBody
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        private ResponseInterface $response,
        private array $payload,
    ) {}

    /**
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    public static function json(ResponseInterface $response): self
    {
        $contents = trim((string) $response->getBody());

        self::rejectByStatus($response);

        if ($contents === '') {
            throw new UnexpectedStatusException(
                $response,
                'Bookurier returned an empty response body.',
                retryAfter: self::retryAfter($response),
            );
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Expected a JSON response. Got: "%s".', substr($contents, 0, 200)),
                retryAfter: self::retryAfter($response),
            );
        }

        /** @var array<string, mixed> $decoded */
        if (($decoded['status'] ?? null) === 'error') {
            self::throwError($decoded, $response);
        }

        return new self($response, $decoded);
    }

    /**
     * Returns a raw PDF/HTML body, still classifying a JSON error envelope.
     *
     * @throws AuthenticationException
     * @throws ValidationException
     * @throws UnexpectedStatusException
     */
    public static function document(ResponseInterface $response, ?string $expected = null): Document
    {
        $contents = (string) $response->getBody();
        $trimmed = trim($contents);

        self::rejectByStatus($response);

        if ($trimmed === '') {
            throw new UnexpectedStatusException(
                $response,
                'Bookurier returned an empty document.',
                retryAfter: self::retryAfter($response),
            );
        }

        if (str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                if (($decoded['status'] ?? null) === 'error') {
                    self::throwError($decoded, $response);
                }

                throw new UnexpectedStatusException(
                    $response,
                    'Expected a document, got a JSON response.',
                    retryAfter: self::retryAfter($response),
                );
            }
        }

        if (self::isChallenge($contents)) {
            throw new UnexpectedStatusException(
                $response,
                sprintf('Bookurier returned an HTML challenge page instead of a document; wait a few minutes and retry. Got: "%s".', substr($contents, 0, 200)),
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

    public function response(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return list<mixed>
     */
    public function data(): array
    {
        $data = $this->payload['data'] ?? [];

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return array<string, string>
     */
    public function map(): array
    {
        $map = $this->payload['map'] ?? [];

        if (! is_array($map)) {
            return [];
        }

        $result = [];
        foreach ($map as $key => $value) {
            if (is_string($value)) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function errors(): array
    {
        $errors = $this->payload['errors'] ?? [];

        if (! is_array($errors)) {
            return [];
        }

        $result = [];
        foreach ($errors as $error) {
            if (is_array($error)) {
                /** @var array<string, mixed> $error */
                $result[] = $error;
            }
        }

        return $result;
    }

    public function message(): string
    {
        $message = $this->payload['message'] ?? '';

        return is_string($message) ? $message : '';
    }

    public static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * @throws AuthenticationException on 401
     * @throws ValidationException on 400 or 422
     * @throws UnexpectedStatusException on any other non-2xx status, 429 included
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
            throw new ValidationException(message: $message, response: $response, retryAfter: $retryAfter);
        }

        throw new UnexpectedStatusException($response, $message, retryAfter: $retryAfter);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws AuthenticationException
     * @throws ValidationException
     */
    private static function throwError(array $payload, ResponseInterface $response): never
    {
        $message = trim((string) ($payload['message'] ?? ''));
        $message = $message === '' ? 'Bookurier returned an error.' : $message;
        $error = BookurierError::fromArray(['message' => $message]);
        $retryAfter = self::retryAfter($response);
        $lower = strtolower($message);

        if (str_contains($lower, 'invalid user') || str_contains($lower, 'login fail')) {
            throw new AuthenticationException(
                $message,
                response: $response,
                retryAfter: $retryAfter,
                error: $error,
            );
        }

        throw new ValidationException(
            message: $message,
            response: $response,
            retryAfter: $retryAfter,
            error: $error,
        );
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
}
