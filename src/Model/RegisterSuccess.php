<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Registration issued a session immediately (the app does not require email verification). */
final readonly class RegisterSuccess implements RegisterResult
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
