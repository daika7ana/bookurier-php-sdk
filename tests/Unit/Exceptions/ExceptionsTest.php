<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Tests\Unit\Exceptions;

use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\BookurierException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Exception;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class ExceptionsTest extends TestCase
{
    public function testEverySdkExceptionExtendsTheBaseException(): void
    {
        self::assertInstanceOf(Exception::class, new BookurierException());
        self::assertInstanceOf(BookurierException::class, new AuthenticationException());
        self::assertInstanceOf(BookurierException::class, new TransportException());
        self::assertInstanceOf(BookurierException::class, new UnexpectedStatusException());
        self::assertInstanceOf(BookurierException::class, new ValidationException([]));
    }

    public function testTransportExceptionIsAPsr18ClientException(): void
    {
        self::assertInstanceOf(ClientExceptionInterface::class, new TransportException());
    }

    public function testHttpLevelExceptionsAcceptANullResponse(): void
    {
        $authentication = new AuthenticationException();
        $validation = new ValidationException([]);
        $unexpected = new UnexpectedStatusException();

        self::assertNull($authentication->response);
        self::assertNull($authentication->retryAfter);
        self::assertNull($authentication->error);
        self::assertNull($validation->response);
        self::assertNull($validation->retryAfter);
        self::assertNull($validation->error);
        self::assertNull($unexpected->response);
        self::assertNull($unexpected->retryAfter);
        self::assertNull($unexpected->error);
    }

    public function testHttpLevelExceptionsDefaultTheirMessages(): void
    {
        self::assertSame('Validation failed', (new ValidationException([]))->getMessage());
        self::assertSame('Unexpected HTTP status', (new UnexpectedStatusException())->getMessage());
    }

    public function testHttpLevelExceptionsDeriveTheirCodeFromTheResponse(): void
    {
        $response = new Response(422);

        self::assertSame(401, (new AuthenticationException('', 0, response: new Response(401)))->getCode());
        self::assertSame(422, (new ValidationException([], '', 0, response: $response))->getCode());
        self::assertSame(422, (new UnexpectedStatusException($response, 'shape', 123))->getCode());
    }

    public function testHttpLevelExceptionsKeepTheExplicitCodeWithoutAResponse(): void
    {
        self::assertSame(7, (new AuthenticationException('', 7))->getCode());
        self::assertSame(7, (new ValidationException([], '', 7))->getCode());
        self::assertSame(7, (new UnexpectedStatusException(null, 'shape', 7))->getCode());
    }
}
