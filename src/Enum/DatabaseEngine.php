<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Enum;

/** A managed database engine. Valkey speaks the Redis protocol. */
enum DatabaseEngine: string
{
    case Postgres = 'postgres';
    case Mongodb = 'mongodb';
    case Valkey = 'valkey';
    /** @deprecated Redis is not offered and the contract no longer lists it; use Valkey. */
    case Redis = 'redis';
}
