<?php

declare(strict_types=1);

namespace Ghayma\Sdk;

use Ghayma\Sdk\Admin\AuthAppsClient;
use Ghayma\Sdk\Admin\DatabasesClient;
use Ghayma\Sdk\Admin\StorageClient;
use Ghayma\Sdk\Http\ClientResolver;
use Ghayma\Sdk\Http\Transport;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The Ghayma admin client, authenticated with a project API key (`gsk_…`).
 *
 * Server-side only. Exposes three sub-clients over the runtime contract:
 * {@see $auth} (an auth app's end users), {@see $storage} (bucket objects) and
 * {@see $databases} (managed databases). The HTTP client and PSR-17 factories
 * are discovered via php-http/discovery unless supplied.
 */
final class Ghayma
{
    /** Administer an auth app's end users. */
    public readonly AuthAppsClient $auth;

    /** Read and write bucket objects, mint presigned URLs. */
    public readonly StorageClient $storage;

    /** Read managed databases, their credentials and metrics. */
    public readonly DatabasesClient $databases;

    public function __construct(
        string $projectKey,
        string $baseUrl = 'https://api.ghayma.tech',
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $rf = null,
        ?StreamFactoryInterface $sf = null,
    ) {
        $transport = new Transport(
            rtrim($baseUrl, '/'),
            ClientResolver::client($http),
            ClientResolver::requestFactory($rf),
            ClientResolver::streamFactory($sf),
            ['Authorization' => 'Bearer ' . $projectKey],
        );

        $this->auth = new AuthAppsClient($transport);
        $this->storage = new StorageClient($transport);
        $this->databases = new DatabasesClient($transport);
    }
}
