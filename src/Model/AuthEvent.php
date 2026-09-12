<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** One row of an auth app's event log. */
final readonly class AuthEvent
{
    use DecodesData;

    public function __construct(
        public string $id,
        public string $appId,
        public ?string $userId,
        public string $event,
        public string $ip,
        public string $userAgent,
        public bool $success,
        public ?string $details,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: self::str($d, 'id'),
            appId: self::str($d, 'app_id'),
            userId: self::nstr($d, 'user_id'),
            event: self::str($d, 'event'),
            ip: self::str($d, 'ip'),
            userAgent: self::str($d, 'user_agent'),
            success: self::bool($d, 'success'),
            details: self::nstr($d, 'details'),
            createdAt: self::date($d, 'created_at'),
        );
    }
}
