<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** The app enforces 2FA and this user is not enrolled; run TOTP enrolment with the enroll token. */
final readonly class TwoFaEnrollmentRequired implements LoginResult
{
    use DecodesData;

    /** @param list<string> $methods */
    public function __construct(
        public string $enrollToken,
        public array $methods,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            enrollToken: self::str($d, 'enroll_token'),
            methods: self::strList($d, 'methods'),
        );
    }
}
