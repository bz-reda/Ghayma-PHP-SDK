<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** One page of objects in a bucket, plus the folders (common prefixes) at this level. */
final readonly class ObjectList
{
    use DecodesData;

    /**
     * @param list<StorageObject> $objects
     * @param list<string>        $folders
     */
    public function __construct(
        public array $objects,
        public array $folders,
        public string $continuationToken,
        public bool $isTruncated,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            objects: self::objectList($d, 'objects', StorageObject::fromArray(...)),
            folders: self::strList($d, 'folders'),
            continuationToken: self::str($d, 'continuation_token'),
            isTruncated: self::bool($d, 'is_truncated'),
        );
    }
}
