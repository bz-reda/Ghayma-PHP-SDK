<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Live metrics for a database. Only `status` is set when the database is not running. */
final readonly class DatabaseMetrics
{
    use DecodesData;

    /** @param array<string, mixed> $extra engine-specific counters */
    public function __construct(
        public string $status,
        public ?float $uptimeHours,
        public int $sizeBytes,
        public string $sizeReadable,
        public int $activeConnections,
        public ?int $maxConnections,
        public array $extra,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            status: self::str($d, 'status'),
            uptimeHours: self::nfloat($d, 'uptime_hours'),
            sizeBytes: self::int($d, 'size_bytes'),
            sizeReadable: self::str($d, 'size_readable'),
            activeConnections: self::int($d, 'active_connections'),
            maxConnections: self::nint($d, 'max_connections'),
            extra: self::map($d, 'extra'),
        );
    }
}
