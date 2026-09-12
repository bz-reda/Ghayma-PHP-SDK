<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** A freshly minted TOTP secret, returned exactly once, to confirm next. */
final readonly class TotpEnrollment
{
    use DecodesData;

    public function __construct(
        public string $secret,
        public string $otpauthUri,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            secret: self::str($d, 'secret'),
            otpauthUri: self::str($d, 'otpauth_uri'),
        );
    }
}
