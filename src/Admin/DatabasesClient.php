<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Admin;

use Ghayma\Sdk\Http\Transport;
use Ghayma\Sdk\Model\Database;
use Ghayma\Sdk\Model\DatabaseCredentials;
use Ghayma\Sdk\Model\DatabaseMetrics;
use Ghayma\Sdk\Model\DecodesData;

/**
 * Read a project's managed databases, their credentials and live metrics
 * (the `databases` capability). Reachable through {@see \Ghayma\Sdk\Ghayma::$databases}.
 */
final class DatabasesClient
{
    use DecodesData;

    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    /**
     * List the managed databases in the key's project.
     *
     * @return list<Database>
     */
    public function list(): array
    {
        $res = $this->transport->request('GET', '/api/v1/databases');

        return self::objectList($res, 'databases', Database::fromArray(...));
    }

    /** Get one managed database. */
    public function get(string $id): Database
    {
        $res = $this->transport->request('GET', '/api/v1/databases/' . $id);

        return Database::fromArray(self::map($res, 'database'));
    }

    /** Get a managed database's connection credentials, including the live password. */
    public function credentials(string $id): DatabaseCredentials
    {
        $res = $this->transport->request('GET', '/api/v1/databases/' . $id . '/credentials');

        // The contract returns the credentials object directly; tolerate a wrapper too.
        $data = array_key_exists('credentials', $res) ? self::map($res, 'credentials') : $res;

        return DatabaseCredentials::fromArray($data);
    }

    /** Get a managed database's live metrics (only `status` is set when it is not running). */
    public function metrics(string $id): DatabaseMetrics
    {
        $res = $this->transport->request('GET', '/api/v1/databases/' . $id . '/metrics');

        return DatabaseMetrics::fromArray(self::map($res, 'metrics'));
    }
}
