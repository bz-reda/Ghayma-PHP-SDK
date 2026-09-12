<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/**
 * The result of turning TOTP on. `recoveryCodes` appears only when a set was
 * minted; `session` only on the enforced-enrolment path, which also completes
 * the login in the same round-trip.
 */
final readonly class TotpConfirmation
{
    use DecodesData;

    /** @param list<string> $recoveryCodes */
    public function __construct(
        public bool $enabled,
        public array $recoveryCodes,
        public ?Session $session,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            enabled: self::bool($d, 'enabled'),
            recoveryCodes: self::strList($d, 'recovery_codes'),
            session: isset($d['access_token']) ? Session::fromArray($d) : null,
        );
    }
}
