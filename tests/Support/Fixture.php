<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class Fixture
{
    public const HTML_BODY = '<!DOCTYPE html><html><body><h1>Bookurier</h1></body></html>';

    public const PDF_BODY = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    public static function response(string $body, string $contentType = 'text/plain; charset=UTF-8', int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => $contentType], $body);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): ResponseInterface
    {
        return self::response(json_encode($payload, JSON_THROW_ON_ERROR), 'application/json', $status);
    }

    public static function addSuccess(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => ['BOK6000123456'],
            'map' => ['CMD-10001' => 'BOK6000123456'],
            'errors' => [],
            'message' => 'ok',
        ]);
    }

    public static function addSuccessWithoutMap(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => ['BOK6000123456'],
            'errors' => [],
            'message' => 'ok',
        ]);
    }

    public static function batchSuccess(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => ['BOK6000123456', 'BOK6000123457'],
            'map' => [
                'CMD-10001' => 'BOK6000123456',
                'CMD-10002' => 'BOK6000123457',
            ],
            'errors' => [],
            'message' => 'ok',
        ]);
    }

    public static function batchPartial(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => ['BOK6000123456'],
            'map' => ['CMD-10001' => 'BOK6000123456'],
            'errors' => [[
                'order' => 'CMD-10002',
                'message' => 'Judet invalid',
                'suggestion' => 'Verificati judetul.',
            ]],
            'message' => 'ok',
        ]);
    }

    public static function orderError(string $message = 'Judet invalid', ?string $suggestion = 'Verificati judetul.'): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => [],
            'map' => [],
            'errors' => [[
                'order' => 'CMD-10001',
                'message' => $message,
                'suggestion' => $suggestion,
            ]],
            'message' => 'ok',
        ]);
    }

    public static function history(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => [
                [
                    'data' => '14.07.2023',
                    'status_id' => '2',
                    'status_name' => 'Receptionata',
                    'obs' => '0',
                    'sort_date' => '2023-07-14 11:44:12',
                ],
                [
                    'data' => '15.07.2023',
                    'status_id' => '18',
                    'status_name' => 'Livrat',
                    'obs' => '',
                    'sort_date' => '2023-07-15 09:10:00',
                ],
            ],
            'message' => 'ok',
        ]);
    }

    public static function historyWithPartnerText(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'data' => [[
                'data' => '16.07.2023',
                'status_id' => '',
                'status_name' => 'In curs de livrare la curier partener',
                'obs' => '',
                'sort_date' => '2023-07-16 08:00:00',
            ]],
            'message' => 'ok',
        ]);
    }

    public static function partnerCities(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'count' => 2,
            'data' => [
                [
                    'city' => 'Ploiesti',
                    'district' => 'Prahova',
                    'zip' => '100087',
                    'zone' => 'MUNTENIA',
                    'sort' => 'PH',
                    'driver' => '20102',
                    'kg' => 300,
                ],
                [
                    'city' => 'Campina',
                    'district' => 'Prahova',
                    'zip' => '105600',
                    'zone' => 'MUNTENIA',
                    'sort' => 'PH',
                    'driver' => '20102',
                    'kg' => 300,
                ],
            ],
            'message' => 'ok',
        ]);
    }

    public static function pickup(): ResponseInterface
    {
        return self::json([
            'status' => 'success',
            'message' => 'Pickup requested for tomorrow.',
        ]);
    }

    public static function error(string $message = 'Invalid request'): ResponseInterface
    {
        return self::json(['status' => 'error', 'message' => $message]);
    }

    public static function rateLimited(int $retryAfter = 42): ResponseInterface
    {
        return new Response(429, [
            'Content-Type' => 'application/json',
            'Retry-After' => (string) $retryAfter,
        ], json_encode([
            'status' => 'error',
            'message' => sprintf('Too many requests, retry in %d seconds', $retryAfter),
        ], JSON_THROW_ON_ERROR));
    }

    public static function html(): ResponseInterface
    {
        return self::response(self::HTML_BODY, 'text/html; charset=UTF-8');
    }

    public static function pdf(): ResponseInterface
    {
        return self::response(self::PDF_BODY, 'application/pdf');
    }

    public static function challenge(): ResponseInterface
    {
        return self::response(
            '<!DOCTYPE html><html><head><title>One moment, please...</title></head>'
            . '<body>Your request is being verified by challenge-platform</body></html>',
            'text/html; charset=UTF-8',
        );
    }
}
