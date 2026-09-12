<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** A live, single-use password-reset link for an end user, and its expiry. */
final readonly class ResetLink
{
    use DecodesData;

    public function __construct(
        public string $link,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            link: self::str($d, 'link'),
            expiresAt: self::date($d, 'expires_at'),
        );
    }
}
