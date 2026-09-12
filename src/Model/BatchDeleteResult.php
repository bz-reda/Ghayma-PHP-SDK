<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** The outcome of a batch object delete: the keys removed, and per-key errors. */
final readonly class BatchDeleteResult
{
    use DecodesData;

    /**
     * @param list<string>      $deleted
     * @param list<DeleteError> $errors
     */
    public function __construct(
        public array $deleted,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            deleted: self::strList($d, 'deleted'),
            errors: self::objectList($d, 'errors', DeleteError::fromArray(...)),
        );
    }
}
