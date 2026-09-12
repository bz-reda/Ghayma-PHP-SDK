<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** Metadata for a single object, from a HEAD. */
final readonly class ObjectMetadata
{
    use DecodesData;

    /** @param array<string, string> $metadata user metadata; empty when none */
    public function __construct(
        public string $key,
        public int $size,
        public ?string $contentType,
        public ?string $etag,
        public DateTimeImmutable $lastModified,
        public ?string $cacheControl,
        public ?string $contentEncoding,
        public ?string $contentDisposition,
        public array $metadata,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        $meta = self::map($d, 'metadata');
        $strings = [];
        foreach ($meta as $name => $value) {
            if (is_string($value)) {
                $strings[$name] = $value;
            }
        }

        return new self(
            key: self::str($d, 'key'),
            size: self::int($d, 'size'),
            contentType: self::nstr($d, 'content_type'),
            etag: self::nstr($d, 'etag'),
            lastModified: self::date($d, 'last_modified'),
            cacheControl: self::nstr($d, 'cache_control'),
            contentEncoding: self::nstr($d, 'content_encoding'),
            contentDisposition: self::nstr($d, 'content_disposition'),
            metadata: $strings,
        );
    }
}
