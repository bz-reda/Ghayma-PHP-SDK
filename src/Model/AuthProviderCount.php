<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** A per-provider user count in an auth app's stats. */
final readonly class AuthProviderCount
{
    use DecodesData;

    public function __construct(
        public string $provider,
        public int $count,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            provider: self::str($d, 'provider'),
            count: self::int($d, 'count'),
        );
    }
}
