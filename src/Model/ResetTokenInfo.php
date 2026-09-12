<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** The outcome of pre-checking a password-reset token without consuming it. */
final readonly class ResetTokenInfo
{
    use DecodesData;

    public function __construct(
        public bool $valid,
        public string $email,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            valid: self::bool($d, 'valid'),
            email: self::str($d, 'email'),
        );
    }
}
