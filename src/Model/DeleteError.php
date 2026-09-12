<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** One key that could not be deleted, with the reason. */
final readonly class DeleteError
{
    use DecodesData;

    public function __construct(
        public string $key,
        public string $error,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            key: self::str($d, 'key'),
            error: self::str($d, 'error'),
        );
    }
}
