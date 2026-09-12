<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Conformance;

use Ghayma\Sdk\Exception\ForbiddenException;
use Ghayma\Sdk\Exception\NotFoundException;
use Ghayma\Sdk\Exception\RateLimitedException;
use Ghayma\Sdk\Ghayma;
use Ghayma\Sdk\Model\AuthApp;
use Ghayma\Sdk\Model\AuthStats;
use Ghayma\Sdk\Model\AuthUser;
use Ghayma\Sdk\Model\Bucket;
use Ghayma\Sdk\Model\BucketCredentials;
use Ghayma\Sdk\Model\Database;
use Ghayma\Sdk\Model\DatabaseCredentials;
use Ghayma\Sdk\Model\DatabaseMetrics;
use Ghayma\Sdk\Model\ObjectContent;
use Ghayma\Sdk\Model\ObjectList;
use Ghayma\Sdk\Model\ObjectMetadata;
use Ghayma\Sdk\Model\PresignedUrl;
use Ghayma\Sdk\Model\ResetLink;
use Ghayma\Sdk\Model\UserPage;
use Http\Discovery\Psr18ClientDiscovery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('conformance')]
final class RuntimeConformanceTest extends TestCase
{
    private const APP_ID = '7b2f9c14-3e5a-4d61-9f8b-2a1c4e6d8f00';
    private const USER_ID = '5c6d7e8f-9a0b-4c1d-8e2f-3a4b5c6d7e8f';
    private const DB_ID = 'd4a1e2b3-6c7d-48e9-b0a1-2c3d4e5f6a7b';
    private const BUCKET_ID = 'b1c2d3e4-5f60-4a7b-8c9d-0e1f2a3b4c5d';

    private PreferClient $prefer;
    private Ghayma $ghayma;

    protected function setUp(): void
    {
        $url = getenv('GHAYMA_RUNTIME_URL');
        if ($url === false || $url === '') {
            $this->markTestSkipped('GHAYMA_RUNTIME_URL not set (start Prism via tool/conformance.sh)');
        }

        $this->prefer = new PreferClient(Psr18ClientDiscovery::find());
        $this->ghayma = new Ghayma('gsk_test', rtrim($url, '/'), $this->prefer);
    }

    public function testAuthAppMethods(): void
    {
        $apps = $this->ghayma->auth->listApps();
        $this->assertNotEmpty($apps);
        $this->assertInstanceOf(AuthApp::class, $apps[0]);

        $this->assertInstanceOf(AuthApp::class, $this->ghayma->auth->getApp(self::APP_ID));
        $this->assertInstanceOf(AuthStats::class, $this->ghayma->auth->stats(self::APP_ID));

        $link = $this->ghayma->auth->resetLink(self::APP_ID, 'user@example.com');
        $this->assertInstanceOf(ResetLink::class, $link);

        $page = $this->ghayma->auth->listUsers(self::APP_ID, 1, 20);
        $this->assertInstanceOf(UserPage::class, $page);
        $this->assertInstanceOf(AuthUser::class, $page->users[0]);

        $this->assertInstanceOf(AuthUser::class, $this->ghayma->auth->getUser(self::APP_ID, self::USER_ID));
        $this->assertInstanceOf(AuthUser::class, $this->ghayma->auth->setAppMetadata(self::APP_ID, self::USER_ID, ['roles' => ['admin']]));

        // Message-only endpoints: assert they complete without throwing.
        $this->ghayma->auth->disableUser(self::APP_ID, self::USER_ID);
        $this->ghayma->auth->enableUser(self::APP_ID, self::USER_ID);
        $this->ghayma->auth->reset2fa(self::APP_ID, self::USER_ID);
        $this->ghayma->auth->deleteUser(self::APP_ID, self::USER_ID);
        $this->addToAssertionCount(1);
    }

    public function testStorageMethods(): void
    {
        $buckets = $this->ghayma->storage->listBuckets();
        $this->assertInstanceOf(Bucket::class, $buckets[0]);
        $this->assertInstanceOf(Bucket::class, $this->ghayma->storage->getBucket(self::BUCKET_ID));
        $this->assertInstanceOf(BucketCredentials::class, $this->ghayma->storage->credentials(self::BUCKET_ID));

        $this->assertInstanceOf(PresignedUrl::class, $this->ghayma->storage->presignUpload(self::BUCKET_ID, 'images/photo.jpg', 3600));
        $this->assertInstanceOf(PresignedUrl::class, $this->ghayma->storage->presignDownload(self::BUCKET_ID, 'images/photo.jpg'));

        $this->assertInstanceOf(ObjectList::class, $this->ghayma->storage->listObjects(self::BUCKET_ID, 'images/'));
        $this->assertInstanceOf(ObjectMetadata::class, $this->ghayma->storage->objectInfo(self::BUCKET_ID, 'images/photo.jpg'));
        $this->assertInstanceOf(ObjectContent::class, $this->ghayma->storage->downloadObject(self::BUCKET_ID, 'images/photo.jpg'));

        $this->ghayma->storage->uploadObject(self::BUCKET_ID, 'images/photo.jpg', 'RAWBYTES', 'image/jpeg');
        $this->ghayma->storage->deleteObject(self::BUCKET_ID, 'images/photo.jpg');

        $batch = $this->ghayma->storage->deleteBatch(self::BUCKET_ID, ['images/a.jpg', 'images/b.jpg']);
        $this->assertSame(['images/a.jpg', 'images/b.jpg'], $batch->deleted);
        $prefix = $this->ghayma->storage->deletePrefix(self::BUCKET_ID, 'images/');
        $this->assertSame(12, $prefix->deletedCount);
    }

    public function testDatabaseMethods(): void
    {
        $dbs = $this->ghayma->databases->list();
        $this->assertInstanceOf(Database::class, $dbs[0]);
        $this->assertInstanceOf(Database::class, $this->ghayma->databases->get(self::DB_ID));
        $this->assertInstanceOf(DatabaseCredentials::class, $this->ghayma->databases->credentials(self::DB_ID));
        $this->assertInstanceOf(DatabaseMetrics::class, $this->ghayma->databases->metrics(self::DB_ID));
    }

    public function testForbidden(): void
    {
        $this->prefer->prefer('code=403, example=forbidden');
        $this->expectException(ForbiddenException::class);
        $this->ghayma->auth->listApps();
    }

    public function testNotFound(): void
    {
        $this->prefer->prefer('code=404, example=not_found');
        $this->expectException(NotFoundException::class);
        $this->ghayma->auth->getApp(self::APP_ID);
    }

    public function testRateLimited(): void
    {
        $this->prefer->prefer('code=429, example=rate_limited');
        try {
            $this->ghayma->auth->resetLink(self::APP_ID, 'user@example.com');
            $this->fail('expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('rate_limited', $e->errorCode);
        }
    }
}
