<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** A storage bucket in the key's project. */
final readonly class Bucket
{
    use DecodesData;

    /** @param list<string> $allowedOrigins */
    public function __construct(
        public string $id,
        public string $userId,
        public ?string $projectId,
        public ?string $teamId,
        public string $name,
        public string $garageBucket,
        public int $storageUsedBytes,
        public int $storageLimitBytes,
        public bool $isPublic,
        public bool $externalAccess,
        public string $status,
        public array $allowedOrigins,
        public ?DateTimeImmutable $projectLinkedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: self::str($d, 'id'),
            userId: self::str($d, 'user_id'),
            projectId: self::nstr($d, 'project_id'),
            teamId: self::nstr($d, 'team_id'),
            name: self::str($d, 'name'),
            garageBucket: self::str($d, 'garage_bucket'),
            storageUsedBytes: self::int($d, 'storage_used_bytes'),
            storageLimitBytes: self::int($d, 'storage_limit_bytes'),
            isPublic: self::bool($d, 'is_public'),
            externalAccess: self::bool($d, 'external_access'),
            status: self::str($d, 'status'),
            allowedOrigins: self::strList($d, 'allowed_origins'),
            projectLinkedAt: self::ndate($d, 'project_linked_at'),
            createdAt: self::date($d, 'created_at'),
            updatedAt: self::date($d, 'updated_at'),
        );
    }
}
