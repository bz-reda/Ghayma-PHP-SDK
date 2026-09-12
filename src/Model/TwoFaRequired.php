<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** The password verified but the user holds a second factor; finish at verify2fa(). */
final readonly class TwoFaRequired implements LoginResult
{
    use DecodesData;

    /** @param list<string> $methods */
    public function __construct(
        public string $challengeToken,
        public array $methods,
        public string $phoneHint,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            challengeToken: self::str($d, 'challenge_token'),
            methods: self::strList($d, 'methods'),
            phoneHint: self::str($d, 'phone_hint'),
        );
    }
}
