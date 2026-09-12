<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Login succeeded with no second factor: a full session was issued. */
final readonly class LoginSuccess implements LoginResult
{
    public function __construct(
        public Session $session,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(Session::fromArray($d));
    }
}
