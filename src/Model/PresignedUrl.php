<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** A presigned S3 URL and its lifetime. */
final readonly class PresignedUrl
{
    use DecodesData;

    public function __construct(
        public string $url,
        public int $expiresIn,
    ) {
    }

    /**
     * The contract names the field `upload_url` or `download_url` per direction;
     * both are surfaced here as `url`.
     *
     * @param array<string, mixed> $d
     */
    public static function fromArray(array $d): self
    {
        $url = self::nstr($d, 'upload_url')
            ?? self::nstr($d, 'download_url')
            ?? self::str($d, 'url');

        return new self(
            url: $url,
            expiresIn: self::int($d, 'expires_in'),
        );
    }
}
