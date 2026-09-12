<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Unit;

use Ghayma\Sdk\Ghayma;
use Ghayma\Sdk\Model\AuthApp;
use Ghayma\Sdk\Model\AuthUser;
use Ghayma\Sdk\Model\Bucket;
use Ghayma\Sdk\Model\BucketCredentials;
use Ghayma\Sdk\Model\Database;
use Ghayma\Sdk\Model\DatabaseCredentials;
use Ghayma\Sdk\Model\ObjectContent;
use Ghayma\Sdk\Model\PresignedUrl;
use Ghayma\Sdk\Tests\Support\RecordingClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class AdminTest extends TestCase
{
    private function ghayma(RecordingClient $client): Ghayma
    {
        $factory = new Psr17Factory();

        return new Ghayma('gsk_test', 'https://api.ghayma.tech', $client, $factory, $factory);
    }

    /** @return array<string, mixed> */
    private function sampleUser(): array
    {
        return [
            'id' => '5c6d7e8f-9a0b-4c1d-8e2f-3a4b5c6d7e8f',
            'app_id' => '7b2f9c14-3e5a-4d61-9f8b-2a1c4e6d8f00',
            'email' => 'user@example.com',
            'name' => 'Sample User',
            'email_verified' => true,
            'provider' => 'email',
            'disabled' => false,
            'app_metadata' => ['roles' => ['member']],
            'phone_verified' => false,
            'totp_enabled' => false,
            'whatsapp_otp_enabled' => false,
            'created_at' => '2026-09-01T10:15:00Z',
            'updated_at' => '2026-09-09T08:30:00Z',
        ];
    }

    private function assertRequest(RequestInterface $r, string $method, string $path): void
    {
        $this->assertSame($method, $r->getMethod());
        $this->assertSame('https://api.ghayma.tech' . $path, (string) $r->getUri());
        $this->assertSame('Bearer gsk_test', $r->getHeaderLine('Authorization'));
    }

    public function testListAppsGetsCollection(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['auth_apps' => [[
            'id' => '7b2f9c14-3e5a-4d61-9f8b-2a1c4e6d8f00', 'user_id' => 'u', 'project_id' => 'p',
            'name' => 'Sample App', 'app_id' => 'sample-app', 'status' => 'active',
            'allowed_origins' => [], 'email_verification_required' => true,
            'google_oauth_enabled' => false, 'google_native_client_ids' => [], 'github_oauth_enabled' => false,
            'auth_tier_slug' => '1k', 'two_fa_enabled' => false, 'sms_enabled' => false,
            'two_fa_policy' => 'optional', 'reset_url' => '', 'email_locale' => 'en',
            'jwt_expiry_seconds' => 900, 'refresh_expiry_seconds' => 604800,
            'created_at' => '2026-09-01T10:15:00Z', 'updated_at' => '2026-09-10T12:00:00Z',
        ]]]);
        $apps = $this->ghayma($client)->auth->listApps();

        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/auth-apps');
        $this->assertCount(1, $apps);
        $this->assertInstanceOf(AuthApp::class, $apps[0]);
        $this->assertSame('sample-app', $apps[0]->appId);
    }

    public function testGetAppUnwrapsEnvelope(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['auth_app' => [
            'id' => 'a', 'user_id' => 'u', 'project_id' => 'p', 'name' => 'n', 'app_id' => 'slug',
            'status' => 'active', 'allowed_origins' => [], 'email_verification_required' => false,
            'google_oauth_enabled' => false, 'google_native_client_ids' => [], 'github_oauth_enabled' => false,
            'auth_tier_slug' => '1k', 'two_fa_enabled' => false, 'sms_enabled' => false,
            'two_fa_policy' => '', 'reset_url' => '', 'email_locale' => 'en',
            'jwt_expiry_seconds' => 900, 'refresh_expiry_seconds' => 604800,
            'created_at' => '2026-09-01T10:15:00Z', 'updated_at' => '2026-09-10T12:00:00Z',
        ]]);
        $app = $this->ghayma($client)->auth->getApp('a');

        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/auth-apps/a');
        $this->assertSame('slug', $app->appId);
    }

    public function testResetLinkPostsEmail(): void
    {
        $client = (new RecordingClient())->queueJson(200, [
            'link' => 'https://sample.example.com/reset?token=x',
            'expires_at' => '2026-09-12T13:15:00Z',
        ]);
        $link = $this->ghayma($client)->auth->resetLink('a', 'user@example.com');

        $r = $client->lastRequest();
        $this->assertRequest($r, 'POST', '/api/v1/auth-apps/a/reset-link');
        $this->assertSame('{"email":"user@example.com"}', (string) $r->getBody());
        $this->assertStringContainsString('token=x', $link->link);
    }

    public function testListUsersPassesPageAndLimitAsQuery(): void
    {
        $client = (new RecordingClient())->queueJson(200, [
            'users' => [$this->sampleUser()], 'total' => 128, 'page' => 2, 'per_page' => 50,
        ]);
        $page = $this->ghayma($client)->auth->listUsers('a', 2, 50);

        $r = $client->lastRequest();
        $this->assertSame('GET', $r->getMethod());
        $this->assertSame('https://api.ghayma.tech/api/v1/auth-apps/a/users?page=2&per_page=50', (string) $r->getUri());
        $this->assertSame(50, $page->perPage);
        $this->assertCount(1, $page->users);
        $this->assertInstanceOf(AuthUser::class, $page->users[0]);
    }

    public function testListUsersOmitsQueryWhenNoPaging(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['users' => [], 'total' => 0, 'page' => 1, 'per_page' => 20]);
        $this->ghayma($client)->auth->listUsers('a');

        $this->assertSame('https://api.ghayma.tech/api/v1/auth-apps/a/users', (string) $client->lastRequest()->getUri());
    }

    public function testUserLifecycleVerbsAndPaths(): void
    {
        foreach (
            [
                ['disableUser', 'POST', '/api/v1/auth-apps/a/users/u/disable'],
                ['enableUser', 'POST', '/api/v1/auth-apps/a/users/u/enable'],
                ['reset2fa', 'POST', '/api/v1/auth-apps/a/users/u/reset-2fa'],
                ['deleteUser', 'DELETE', '/api/v1/auth-apps/a/users/u'],
            ] as [$method, $verb, $path]
        ) {
            $client = (new RecordingClient())->queueJson(200, ['message' => 'ok']);
            $this->ghayma($client)->auth->{$method}('a', 'u');
            $this->assertRequest($client->lastRequest(), $verb, $path);
        }
    }

    public function testSetAppMetadataPatchesObjectAndClearsWithEmptyObject(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['user' => $this->sampleUser()]);
        $this->ghayma($client)->auth->setAppMetadata('a', 'u', ['roles' => ['admin']]);
        $r = $client->lastRequest();
        $this->assertRequest($r, 'PATCH', '/api/v1/auth-apps/a/users/u/app-metadata');
        $this->assertSame('{"app_metadata":{"roles":["admin"]}}', (string) $r->getBody());

        $client = (new RecordingClient())->queueJson(200, ['user' => $this->sampleUser()]);
        $this->ghayma($client)->auth->setAppMetadata('a', 'u', []);
        $this->assertSame('{"app_metadata":{}}', (string) $client->lastRequest()->getBody());
    }

    public function testPresignUploadPostsKeyAndExpiry(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['upload_url' => 'https://s3/up?sig=x', 'expires_in' => 3600]);
        $result = $this->ghayma($client)->storage->presignUpload('b1', 'images/photo.jpg', 3600);

        $r = $client->lastRequest();
        $this->assertRequest($r, 'POST', '/api/v1/storage/b1/presign/upload');
        $this->assertSame('{"key":"images/photo.jpg","expiry":3600}', (string) $r->getBody());
        $this->assertInstanceOf(PresignedUrl::class, $result);
        $this->assertSame('https://s3/up?sig=x', $result->url);
    }

    public function testPresignDownloadOmitsExpiryWhenNull(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['download_url' => 'https://s3/down', 'expires_in' => 3600]);
        $this->ghayma($client)->storage->presignDownload('b1', 'images/photo.jpg');

        $this->assertSame('{"key":"images/photo.jpg"}', (string) $client->lastRequest()->getBody());
    }

    public function testListObjectsPassesPrefix(): void
    {
        $client = (new RecordingClient())->queueJson(200, [
            'objects' => [['key' => 'images/a.jpg', 'size' => 10, 'last_modified' => '2026-09-10T12:00:00Z', 'is_folder' => false]],
            'folders' => ['images/'], 'continuation_token' => '', 'is_truncated' => false,
        ]);
        $list = $this->ghayma($client)->storage->listObjects('b1', 'images/');

        $this->assertSame('https://api.ghayma.tech/api/v1/storage/b1/objects?prefix=images%2F', (string) $client->lastRequest()->getUri());
        $this->assertSame(['images/'], $list->folders);
        $this->assertCount(1, $list->objects);
    }

    public function testDeleteObjectSendsKeyBody(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['message' => 'deleted']);
        $this->ghayma($client)->storage->deleteObject('b1', 'images/photo.jpg');

        $r = $client->lastRequest();
        $this->assertRequest($r, 'DELETE', '/api/v1/storage/b1/objects');
        $this->assertSame('{"key":"images/photo.jpg"}', (string) $r->getBody());
    }

    public function testUploadObjectSendsMultipart(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['message' => 'uploaded', 'key' => 'images/photo.jpg']);
        $this->ghayma($client)->storage->uploadObject('b1', 'images/photo.jpg', 'RAWBYTES', 'image/jpeg');

        $r = $client->lastRequest();
        $this->assertSame('POST', $r->getMethod());
        $this->assertStringStartsWith('multipart/form-data; boundary=', $r->getHeaderLine('Content-Type'));
        $body = (string) $r->getBody();
        $this->assertStringContainsString('name="key"', $body);
        $this->assertStringContainsString('images/photo.jpg', $body);
        $this->assertStringContainsString('filename="photo.jpg"', $body);
        $this->assertStringContainsString('Content-Type: image/jpeg', $body);
        $this->assertStringContainsString('RAWBYTES', $body);
    }

    public function testDownloadObjectReturnsBytesTypeAndLength(): void
    {
        $client = (new RecordingClient())->queueRaw(200, 'RAWBYTES', ['Content-Type' => 'image/jpeg', 'Content-Length' => '8']);
        $content = $this->ghayma($client)->storage->downloadObject('b1', 'images/photo.jpg');

        $this->assertSame('https://api.ghayma.tech/api/v1/storage/b1/objects/download?key=images%2Fphoto.jpg', (string) $client->lastRequest()->getUri());
        $this->assertInstanceOf(ObjectContent::class, $content);
        $this->assertSame('RAWBYTES', $content->bytes);
        $this->assertSame('image/jpeg', $content->contentType);
        $this->assertSame(8, $content->contentLength);
    }

    public function testDeleteBatchAndPrefix(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['deleted' => ['a.jpg', 'b.jpg'], 'errors' => []]);
        $batch = $this->ghayma($client)->storage->deleteBatch('b1', ['a.jpg', 'b.jpg']);
        $r = $client->lastRequest();
        $this->assertRequest($r, 'POST', '/api/v1/storage/b1/objects/delete-batch');
        $this->assertSame('{"keys":["a.jpg","b.jpg"]}', (string) $r->getBody());
        $this->assertSame(['a.jpg', 'b.jpg'], $batch->deleted);

        $client = (new RecordingClient())->queueJson(200, ['deleted_count' => 12, 'errors' => []]);
        $prefix = $this->ghayma($client)->storage->deletePrefix('b1', 'images/');
        $this->assertSame('{"prefix":"images/"}', (string) $client->lastRequest()->getBody());
        $this->assertSame(12, $prefix->deletedCount);
    }

    public function testBucketAndCredentials(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['bucket' => [
            'id' => 'b1', 'user_id' => 'u', 'name' => 'assets', 'garage_bucket' => 'assets-b1',
            'storage_used_bytes' => 10, 'storage_limit_bytes' => 100, 'is_public' => false,
            'external_access' => false, 'status' => 'active', 'allowed_origins' => [],
            'created_at' => '2026-09-01T10:15:00Z', 'updated_at' => '2026-09-10T12:00:00Z',
        ]]);
        $bucket = $this->ghayma($client)->storage->getBucket('b1');
        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/storage/b1');
        $this->assertInstanceOf(Bucket::class, $bucket);

        $client = (new RecordingClient())->queueJson(200, ['credentials' => [
            'access_key' => 'GK', 'secret_key' => 's', 'bucket' => 'assets-b1', 'endpoint' => 'https://s3', 'region' => 'garage',
        ]]);
        $creds = $this->ghayma($client)->storage->credentials('b1');
        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/storage/b1/credentials');
        $this->assertInstanceOf(BucketCredentials::class, $creds);
        $this->assertSame('GK', $creds->accessKey);
    }

    public function testDatabasesListGetCredentialsMetrics(): void
    {
        $dbRow = [
            'id' => 'd1', 'user_id' => 'u', 'name' => 'primary', 'type' => 'postgres', 'version' => '16',
            'status' => 'running', 'host' => 'h', 'port' => 5432, 'tier_slug' => 'db-s',
            'cpu_request' => '100m', 'cpu_limit' => '1', 'memory_request' => '256Mi', 'memory_limit' => '1Gi',
            'cpu_milli' => 1000, 'memory_mb' => 1024, 'storage_mb' => 5120, 'storage_used_bytes' => 1,
            'disk_gb' => 5, 'backup_tier_slug' => 'weekly', 'replica_set' => false, 'external_access' => false,
            'created_at' => '2026-09-01T10:15:00Z', 'updated_at' => '2026-09-10T12:00:00Z',
        ];

        $client = (new RecordingClient())->queueJson(200, ['databases' => [$dbRow]]);
        $dbs = $this->ghayma($client)->databases->list();
        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/databases');
        $this->assertCount(1, $dbs);
        $this->assertInstanceOf(Database::class, $dbs[0]);

        // credentials come back bare (no wrapper) per the contract.
        $client = (new RecordingClient())->queueJson(200, [
            'type' => 'postgres', 'host' => 'h', 'port' => 5432, 'username' => 'appuser',
            'password' => 'pw', 'database' => 'appdb', 'internal_url' => 'postgresql://x', 'external_access' => false,
        ]);
        $creds = $this->ghayma($client)->databases->credentials('d1');
        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/databases/d1/credentials');
        $this->assertInstanceOf(DatabaseCredentials::class, $creds);
        $this->assertSame('pw', $creds->password);

        $client = (new RecordingClient())->queueJson(200, ['metrics' => [
            'status' => 'running', 'size_bytes' => 1, 'size_readable' => '1 B', 'active_connections' => 4,
        ]]);
        $metrics = $this->ghayma($client)->databases->metrics('d1');
        $this->assertRequest($client->lastRequest(), 'GET', '/api/v1/databases/d1/metrics');
        $this->assertSame('running', $metrics->status);
    }
}
