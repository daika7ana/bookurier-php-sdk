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

    public static function awb(): ResponseInterface
    {
        return self::response('BOK3925457');
    }

    public static function success(): ResponseInterface
    {
        return self::response('1');
    }

    public static function error(string $message = 'Invalid credentials'): ResponseInterface
    {
        $escaped = htmlspecialchars($message, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return self::response('<msg><cmd></cmd><err>' . $escaped . '</err></msg>', 'text/xml');
    }

    public static function xml(string $body): ResponseInterface
    {
        return self::response($body, 'text/xml');
    }

    public static function html(): ResponseInterface
    {
        return self::response(self::HTML_BODY, 'text/html; charset=UTF-8');
    }

    public static function pdf(): ResponseInterface
    {
        return self::response(self::PDF_BODY, 'application/pdf');
    }

    public static function status(): ResponseInterface
    {
        return self::xml(
            '<msg><cmd><unq>A4343244</unq><awb>000112234242</awb>'
            . '<status_id>5</status_id><status_desc>Livrata</status_desc><obs></obs>'
            . '</cmd><err></err></msg>',
        );
    }

    public static function history(): ResponseInterface
    {
        return self::xml(
            '<msg>'
            . '<cmd><unq>A4343244</unq><awb>000112234242</awb><status_id>1</status_id>'
            . '<status_desc>Preluata</status_desc><date>2020-02-18 07:46:00</date><obs>Prima scanare</obs></cmd>'
            . '<cmd><unq>A4343244</unq><awb>000112234242</awb><status_id>5</status_id>'
            . '<status_desc>Livrata</status_desc><date>2020-02-19 12:30:00</date><obs></obs></cmd>'
            . '<err></err></msg>',
        );
    }

    public static function batch(): ResponseInterface
    {
        return self::xml('<msg><cmds>BOK0001,BOK0002</cmds><err></err></msg>');
    }

    public static function batchError(): ResponseInterface
    {
        return self::xml('<msg><err>Batch rejected</err></msg>');
    }

    public static function batchWithError(): ResponseInterface
    {
        return self::xml('<msg><cmds>BOK0001,BOK0002</cmds><err>Partial failure</err></msg>');
    }

    public static function batchWithoutCmds(): ResponseInterface
    {
        return self::xml('<msg><err></err></msg>');
    }

    public static function challenge(): ResponseInterface
    {
        return self::response(
            '<!DOCTYPE html><html><head><title>One moment, please...</title></head>'
            . '<body>Your request is being verified by challenge-platform</body></html>',
            'text/html; charset=UTF-8',
        );
    }

    public static function whitespaceNegative(): ResponseInterface
    {
        return self::response("  -1\n");
    }
}
