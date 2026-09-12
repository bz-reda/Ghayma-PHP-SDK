<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use Ghayma\Sdk\Enum\DatabaseEngine;

/** Connection details for a managed database, read from its Kubernetes secret. */
final readonly class DatabaseCredentials
{
    use DecodesData;

    public function __construct(
        public DatabaseEngine $type,
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $database,
        public string $internalUrl,
        public bool $externalAccess,
        public ?string $externalHost,
        public ?int $externalPort,
        public ?string $externalUrl,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            type: DatabaseEngine::from(self::str($d, 'type')),
            host: self::str($d, 'host'),
            port: self::int($d, 'port'),
            username: self::str($d, 'username'),
            password: self::str($d, 'password'),
            database: self::str($d, 'database'),
            internalUrl: self::str($d, 'internal_url'),
            externalAccess: self::bool($d, 'external_access'),
            externalHost: self::nstr($d, 'external_host'),
            externalPort: self::nint($d, 'external_port'),
            externalUrl: self::nstr($d, 'external_url'),
        );
    }
}
