<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;
use Ghayma\Sdk\Enum\DatabaseEngine;

/** A managed database. Resource fields are resolved from the row's tier at render time. */
final readonly class Database
{
    use DecodesData;

    public function __construct(
        public string $id,
        public string $userId,
        public ?string $projectId,
        public ?string $teamId,
        public string $name,
        public DatabaseEngine $type,
        public string $version,
        public string $status,
        public string $host,
        public int $port,
        public ?string $dbName,
        public ?string $username,
        public string $tierSlug,
        public string $cpuRequest,
        public string $cpuLimit,
        public string $memoryRequest,
        public string $memoryLimit,
        public int $cpuMilli,
        public int $memoryMb,
        public int $storageMb,
        public int $storageUsedBytes,
        public int $diskGb,
        public string $backupTierSlug,
        public ?int $maxConnections,
        public bool $replicaSet,
        public bool $externalAccess,
        public ?string $externalHost,
        public ?int $externalPort,
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
            type: DatabaseEngine::from(self::str($d, 'type')),
            version: self::str($d, 'version'),
            status: self::str($d, 'status'),
            host: self::str($d, 'host'),
            port: self::int($d, 'port'),
            dbName: self::nstr($d, 'db_name'),
            username: self::nstr($d, 'username'),
            tierSlug: self::str($d, 'tier_slug'),
            cpuRequest: self::str($d, 'cpu_request'),
            cpuLimit: self::str($d, 'cpu_limit'),
            memoryRequest: self::str($d, 'memory_request'),
            memoryLimit: self::str($d, 'memory_limit'),
            cpuMilli: self::int($d, 'cpu_milli'),
            memoryMb: self::int($d, 'memory_mb'),
            storageMb: self::int($d, 'storage_mb'),
            storageUsedBytes: self::int($d, 'storage_used_bytes'),
            diskGb: self::int($d, 'disk_gb'),
            backupTierSlug: self::str($d, 'backup_tier_slug'),
            maxConnections: self::nint($d, 'max_connections'),
            replicaSet: self::bool($d, 'replica_set'),
            externalAccess: self::bool($d, 'external_access'),
            externalHost: self::nstr($d, 'external_host'),
            externalPort: self::nint($d, 'external_port'),
            createdAt: self::date($d, 'created_at'),
            updatedAt: self::date($d, 'updated_at'),
        );
    }
}
