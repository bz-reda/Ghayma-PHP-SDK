<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** An object or folder listed in a bucket. */
final readonly class StorageObject
{
    use DecodesData;

    public function __construct(
        public string $key,
        public int $size,
        public DateTimeImmutable $lastModified,
        public ?string $etag,
        public bool $isFolder,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            key: self::str($d, 'key'),
            size: self::int($d, 'size'),
            lastModified: self::date($d, 'last_modified'),
            etag: self::nstr($d, 'etag'),
            isFolder: self::bool($d, 'is_folder'),
        );
    }
}
