<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** The outcome of deleting every object under a prefix: a count, and per-key errors. */
final readonly class PrefixDeleteResult
{
    use DecodesData;

    /** @param list<DeleteError> $errors */
    public function __construct(
        public int $deletedCount,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            deletedCount: self::int($d, 'deleted_count'),
            errors: self::objectList($d, 'errors', DeleteError::fromArray(...)),
        );
    }
}
