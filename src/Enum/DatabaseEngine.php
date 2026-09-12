<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Enum;

/** A managed database engine. `redis` is legacy: no new redis databases are provisioned. */
enum DatabaseEngine: string
{
    case Postgres = 'postgres';
    case Mongodb = 'mongodb';
    case Redis = 'redis';
}
