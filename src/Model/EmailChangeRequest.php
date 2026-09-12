<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** Acknowledgement that an email-change confirmation link was sent, and when it expires. */
final readonly class EmailChangeRequest
{
    use DecodesData;

    public function __construct(
        public string $message,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            message: self::str($d, 'message'),
            expiresAt: self::date($d, 'expires_at'),
        );
    }
}
