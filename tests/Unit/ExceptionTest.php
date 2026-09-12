<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Unit;

use Ghayma\Sdk\Exception\ForbiddenException;
use Ghayma\Sdk\Exception\GhaymaException;
use Ghayma\Sdk\Exception\InvalidGrantException;
use Ghayma\Sdk\Exception\InvalidTokenException;
use Ghayma\Sdk\Exception\NotFoundException;
use Ghayma\Sdk\Exception\RateLimitedException;
use Ghayma\Sdk\Exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    public function testMapsByStatus(): void
    {
        $this->assertInstanceOf(
            UnauthorizedException::class,
            GhaymaException::fromResponse(401, ['error' => 'invalid key']),
        );
        $this->assertInstanceOf(
            ForbiddenException::class,
            GhaymaException::fromResponse(403, ['error' => 'project keys cannot access this route']),
        );
        $this->assertInstanceOf(
            NotFoundException::class,
            GhaymaException::fromResponse(404, ['error' => 'not found']),
        );
        $this->assertInstanceOf(
            RateLimitedException::class,
            GhaymaException::fromResponse(429, ['error' => 'too many requests', 'code' => 'rate_limited']),
        );
    }

    public function testCodeWinsOverStatus(): void
    {
        // reset-password / oauth-exchange answer 400 but name the reason in `code`.
        $grant = GhaymaException::fromResponse(400, ['error' => 'invalid or expired reset token', 'code' => 'invalid_grant']);
        $this->assertInstanceOf(InvalidGrantException::class, $grant);
        $this->assertSame('invalid_grant', $grant->errorCode);

        $token = GhaymaException::fromResponse(401, ['error' => 'invalid id token', 'code' => 'invalid_token']);
        $this->assertInstanceOf(InvalidTokenException::class, $token);
        $this->assertSame('invalid_token', $token->errorCode);
    }

    public function testRetryAfterCarriedOn429(): void
    {
        $ex = GhaymaException::fromResponse(429, ['error' => 'slow down', 'code' => 'rate_limited'], 7);
        $this->assertInstanceOf(RateLimitedException::class, $ex);
        $this->assertSame(7, $ex->retryAfter);
        $this->assertSame(429, $ex->status);
    }

    public function testUnknownFallsBackToBaseWithDefaultCode(): void
    {
        $ex = GhaymaException::fromResponse(418, ['error' => "i'm a teapot"]);
        $this->assertSame(GhaymaException::class, $ex::class);
        $this->assertSame('request_failed', $ex->errorCode);
        $this->assertSame(418, $ex->status);
        $this->assertSame("i'm a teapot", $ex->getMessage());
        $this->assertNull($ex->retryAfter);
    }

    public function testDefaultMessageWhenNoErrorField(): void
    {
        $ex = GhaymaException::fromResponse(500, []);
        $this->assertSame('Request failed with status 500', $ex->getMessage());
    }

    public function testSubclassesAreCatchableAsBase(): void
    {
        $this->expectException(GhaymaException::class);

        throw GhaymaException::fromResponse(404, ['error' => 'not found']);
    }
}
