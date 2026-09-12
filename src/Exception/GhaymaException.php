<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

use RuntimeException;
use Throwable;

/**
 * Base class for every error the SDK raises.
 *
 * Carries the HTTP `status`, the machine-readable `errorCode` from the
 * contract's error vocabulary, and `retryAfter` seconds on a 429. Catch this
 * to handle any failure, or one of the subclasses to branch on a specific one.
 */
class GhaymaException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly string $errorCode = self::DEFAULT_CODE,
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    private const DEFAULT_CODE = 'request_failed';

    /**
     * Map a non-2xx response to the right subclass: keyed first by the body's
     * machine-readable `code` (present on newer endpoints), then by HTTP status.
     *
     * @param array<string, mixed> $body
     */
    public static function fromResponse(int $status, array $body, ?int $retryAfter = null): self
    {
        $message = self::stringField($body, 'error')
            ?? self::stringField($body, 'message')
            ?? 'Request failed with status ' . $status;
        $code = self::stringField($body, 'code');

        $byCode = match ($code) {
            'invalid_grant' => InvalidGrantException::class,
            'invalid_token' => InvalidTokenException::class,
            'rate_limited' => RateLimitedException::class,
            default => null,
        };

        $byStatus = match ($status) {
            401 => UnauthorizedException::class,
            403 => ForbiddenException::class,
            404 => NotFoundException::class,
            429 => RateLimitedException::class,
            default => null,
        };

        /** @var class-string<self> $class */
        $class = $byCode ?? $byStatus ?? self::class;

        return new $class($message, $status, $code ?? self::DEFAULT_CODE, $retryAfter);
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function stringField(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
