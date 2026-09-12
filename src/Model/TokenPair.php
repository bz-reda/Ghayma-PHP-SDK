<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** An access/refresh token pair with the access token's lifetime. */
final readonly class TokenPair
{
    use DecodesData;

    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $tokenType,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            accessToken: self::str($d, 'access_token'),
            refreshToken: self::str($d, 'refresh_token'),
            expiresIn: self::int($d, 'expires_in'),
            tokenType: self::str($d, 'token_type'),
        );
    }
}
