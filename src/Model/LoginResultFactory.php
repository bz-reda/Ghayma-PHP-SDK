<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Discriminates a /login response into the right {@see LoginResult} variant by its keys. */
final class LoginResultFactory
{
    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): LoginResult
    {
        if (array_key_exists('two_fa_required', $d)) {
            return TwoFaRequired::fromArray($d);
        }
        if (array_key_exists('two_fa_enrollment_required', $d)) {
            return TwoFaEnrollmentRequired::fromArray($d);
        }

        return LoginSuccess::fromArray($d);
    }
}
