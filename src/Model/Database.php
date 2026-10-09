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
        /** `provisioning`, `running`, `stopped`, `error` or `resizing` (while the disk moves to a new size). */
        public string $status,
        public string $host,
        public int $port,
        public ?string $dbName,
        /** @deprecated Not sent since October 2026: the database's own login is never handed out, so it is null. */
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
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        /** `cache` (evicts least-recently-used keys) or `store` (never evicts); a Valkey only. */
        public ?string $valkeyMode = null,
        /** Why the database is in error; null when empty. */
        public ?string $statusMessage = null,
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
            createdAt: self::date($d, 'created_at'),
            updatedAt: self::date($d, 'updated_at'),
            valkeyMode: self::nstr($d, 'valkey_mode'),
            statusMessage: self::nstr($d, 'status_message'),
        );
    }
}
