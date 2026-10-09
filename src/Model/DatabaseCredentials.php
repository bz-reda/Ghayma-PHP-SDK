<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use Ghayma\Sdk\Enum\DatabaseEngine;

/**
 * Connection details for a managed database: the site's own connection
 * credential, the login its runtime env carries in `DATABASE_URL` / `MONGODB_URI`.
 */
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
        /** The connection's level: `read-only` or `connect`. */
        public ?string $level = null,
        /** Always `connection`: the site's own credential, never the database's own login. */
        public ?string $credential = null,
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
            level: self::nstr($d, 'level'),
            credential: self::nstr($d, 'credential'),
        );
    }
}
