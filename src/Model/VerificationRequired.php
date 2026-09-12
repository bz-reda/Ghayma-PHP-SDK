<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Registration created the account but withheld tokens until the email is verified. */
final readonly class VerificationRequired implements RegisterResult
{
    use DecodesData;

    public function __construct(
        public string $message,
        public User $user,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            message: self::str($d, 'message'),
            user: User::fromArray(self::map($d, 'user')),
        );
    }
}
