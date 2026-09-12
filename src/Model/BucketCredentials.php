<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** S3 credentials for a bucket — feed straight into any S3 client. */
final readonly class BucketCredentials
{
    use DecodesData;

    public function __construct(
        public string $accessKey,
        public string $secretKey,
        public string $bucket,
        public string $endpoint,
        public string $region,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            accessKey: self::str($d, 'access_key'),
            secretKey: self::str($d, 'secret_key'),
            bucket: self::str($d, 'bucket'),
            endpoint: self::str($d, 'endpoint'),
            region: self::str($d, 'region'),
        );
    }
}
