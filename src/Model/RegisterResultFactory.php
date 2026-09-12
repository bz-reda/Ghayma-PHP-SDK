<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Discriminates a /register response into the right {@see RegisterResult} variant by its keys. */
final class RegisterResultFactory
{
    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): RegisterResult
    {
        if (array_key_exists('access_token', $d)) {
            return RegisterSuccess::fromArray($d);
        }

        return VerificationRequired::fromArray($d);
    }
}
