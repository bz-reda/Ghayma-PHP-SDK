<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Unit;

use Ghayma\Sdk\Enum\DatabaseEngine;
use Ghayma\Sdk\Enum\Provider;
use Ghayma\Sdk\Model\AuthApp;
use Ghayma\Sdk\Model\AuthStats;
use Ghayma\Sdk\Model\AuthUser;
use Ghayma\Sdk\Model\Bucket;
use Ghayma\Sdk\Model\BucketCredentials;
use Ghayma\Sdk\Model\Database;
use Ghayma\Sdk\Model\DatabaseCredentials;
use Ghayma\Sdk\Model\DatabaseMetrics;
use Ghayma\Sdk\Model\LoginResultFactory;
use Ghayma\Sdk\Model\LoginSuccess;
use Ghayma\Sdk\Model\ObjectMetadata;
use Ghayma\Sdk\Model\PresignedUrl;
use Ghayma\Sdk\Model\RegisterResultFactory;
use Ghayma\Sdk\Model\RegisterSuccess;
use Ghayma\Sdk\Model\Session;
use Ghayma\Sdk\Model\StorageObject;
use Ghayma\Sdk\Model\TokenPair;
use Ghayma\Sdk\Model\TotpConfirmation;
use Ghayma\Sdk\Model\TotpEnrollment;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;
use Ghayma\Sdk\Model\User;
use Ghayma\Sdk\Model\VerificationRequired;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testAuthAppFromRuntimeExample(): void
    {
        $app = AuthApp::fromArray([
            'id' => '7b2f9c14-3e5a-4d61-9f8b-2a1c4e6d8f00',
            'user_id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
            'project_id' => 'a1b2c3d4-e5f6-4708-9a0b-1c2d3e4f5061',
            'name' => 'Sample App',
            'app_id' => 'sample-app',
            'status' => 'active',
            'allowed_origins' => ['https://sample.example.com'],
            'email_verification_required' => true,
            'google_oauth_enabled' => false,
            'google_native_client_ids' => [],
            'github_oauth_enabled' => false,
            'auth_tier_slug' => '1k',
            'two_fa_enabled' => false,
            'sms_enabled' => false,
            'two_fa_policy' => 'optional',
            'reset_url' => '',
            'email_locale' => 'en',
            'jwt_expiry_seconds' => 900,
            'refresh_expiry_seconds' => 604800,
            'created_at' => '2026-09-01T10:15:00Z',
            'updated_at' => '2026-09-10T12:00:00Z',
        ]);

        $this->assertSame('sample-app', $app->appId);
        $this->assertSame(['https://sample.example.com'], $app->allowedOrigins);
        $this->assertTrue($app->emailVerificationRequired);
        $this->assertNull($app->teamId);
        $this->assertNull($app->googleClientId);
        $this->assertSame(900, $app->jwtExpirySeconds);
        $this->assertSame('2026-09-01', $app->createdAt->format('Y-m-d'));
    }

    public function testAuthUserMapsProviderEnumAndOptionalLastLogin(): void
    {
        $user = AuthUser::fromArray([
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
            'last_login_at' => '2026-09-09T08:30:00Z',
            'created_at' => '2026-09-01T10:15:00Z',
            'updated_at' => '2026-09-09T08:30:00Z',
        ]);

        $this->assertSame(Provider::Email, $user->provider);
        $this->assertSame(['roles' => ['member']], $user->appMetadata);
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->lastLoginAt);
    }

    public function testAuthStatsNestsProviderBreakdownAndEvents(): void
    {
        $stats = AuthStats::fromArray([
            'total_users' => 128,
            'verified_users' => 100,
            'active_sessions' => 12,
            'signups_today' => 3,
            'signups_week' => 20,
            'signups_month' => 60,
            'events_today' => 45,
            'events_week' => 300,
            'logins_today' => 30,
            'provider_breakdown' => [
                ['provider' => 'email', 'count' => 90],
                ['provider' => 'google', 'count' => 38],
            ],
            'recent_events' => [[
                'id' => '2b8f6a10-1c2d-4e3f-8a9b-0c1d2e3f4a5b',
                'app_id' => '7b2f9c14-3e5a-4d61-9f8b-2a1c4e6d8f00',
                'user_id' => '5c6d7e8f-9a0b-4c1d-8e2f-3a4b5c6d7e8f',
                'event' => 'login',
                'ip' => '203.0.113.10',
                'user_agent' => 'Mozilla/5.0',
                'success' => true,
                'created_at' => '2026-09-10T09:00:00Z',
            ]],
        ]);

        $this->assertSame(128, $stats->totalUsers);
        $this->assertCount(2, $stats->providerBreakdown);
        $this->assertSame('google', $stats->providerBreakdown[1]->provider);
        $this->assertSame(38, $stats->providerBreakdown[1]->count);
        $this->assertCount(1, $stats->recentEvents);
        $this->assertSame('login', $stats->recentEvents[0]->event);
        $this->assertTrue($stats->recentEvents[0]->success);
    }

    public function testDatabaseAndCredentialsAndMetrics(): void
    {
        $db = Database::fromArray([
            'id' => 'd4a1e2b3-6c7d-48e9-b0a1-2c3d4e5f6a7b',
            'user_id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
            'project_id' => 'a1b2c3d4-e5f6-4708-9a0b-1c2d3e4f5061',
            'name' => 'primary',
            'type' => 'postgres',
            'version' => '16',
            'status' => 'running',
            'host' => 'primary-pg.databases.svc.cluster.local',
            'port' => 5432,
            'db_name' => 'appdb',
            'username' => 'appuser',
            'tier_slug' => 'db-s',
            'cpu_request' => '100m',
            'cpu_limit' => '1',
            'memory_request' => '256Mi',
            'memory_limit' => '1Gi',
            'cpu_milli' => 1000,
            'memory_mb' => 1024,
            'storage_mb' => 5120,
            'storage_used_bytes' => 734003200,
            'disk_gb' => 5,
            'backup_tier_slug' => 'weekly',
            'max_connections' => 100,
            'replica_set' => false,
            'external_access' => false,
            'created_at' => '2026-09-01T10:15:00Z',
            'updated_at' => '2026-09-10T12:00:00Z',
        ]);
        $this->assertSame(DatabaseEngine::Postgres, $db->type);
        $this->assertSame(5432, $db->port);
        $this->assertSame(100, $db->maxConnections);
        $this->assertNull($db->externalHost);

        $creds = DatabaseCredentials::fromArray([
            'type' => 'postgres',
            'host' => 'primary-pg.databases.svc.cluster.local',
            'port' => 5432,
            'username' => 'appuser',
            'password' => 'example-password',
            'database' => 'appdb',
            'internal_url' => 'postgresql://appuser:example-password@primary-pg.databases.svc.cluster.local:5432/appdb',
            'external_access' => false,
        ]);
        $this->assertSame(DatabaseEngine::Postgres, $creds->type);
        $this->assertStringStartsWith('postgresql://', $creds->internalUrl);
        $this->assertNull($creds->externalUrl);

        $metrics = DatabaseMetrics::fromArray([
            'status' => 'running',
            'uptime_hours' => 128.5,
            'size_bytes' => 734003200,
            'size_readable' => '700 MB',
            'active_connections' => 4,
            'max_connections' => 100,
            'extra' => ['table_count' => 12, 'cache_hit_ratio' => 0.98],
        ]);
        $this->assertSame(128.5, $metrics->uptimeHours);
        $this->assertSame(12, $metrics->extra['table_count']);
    }

    public function testBucketStorageObjectAndObjectMetadata(): void
    {
        $bucket = Bucket::fromArray([
            'id' => 'b1c2d3e4-5f60-4a7b-8c9d-0e1f2a3b4c5d',
            'user_id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
            'name' => 'assets',
            'garage_bucket' => 'assets-b1c2d3e4',
            'storage_used_bytes' => 10485760,
            'storage_limit_bytes' => 5368709120,
            'is_public' => false,
            'external_access' => false,
            'status' => 'active',
            'allowed_origins' => ['https://sample.example.com'],
            'created_at' => '2026-09-01T10:15:00Z',
            'updated_at' => '2026-09-10T12:00:00Z',
        ]);
        $this->assertSame('assets-b1c2d3e4', $bucket->garageBucket);
        $this->assertFalse($bucket->isPublic);

        $creds = BucketCredentials::fromArray([
            'access_key' => 'GK-example-access-key',
            'secret_key' => 'example-secret-key',
            'bucket' => 'assets-b1c2d3e4',
            'endpoint' => 'https://s3.ghayma.app',
            'region' => 'garage',
        ]);
        $this->assertSame('garage', $creds->region);

        $object = StorageObject::fromArray([
            'key' => 'images/photo.jpg',
            'size' => 20480,
            'last_modified' => '2026-09-10T12:00:00Z',
            'etag' => '"9a0b1c2d3e4f"',
            'is_folder' => false,
        ]);
        $this->assertSame('images/photo.jpg', $object->key);
        $this->assertFalse($object->isFolder);

        $meta = ObjectMetadata::fromArray([
            'key' => 'images/photo.jpg',
            'size' => 20480,
            'content_type' => 'image/jpeg',
            'etag' => '"9a0b1c2d3e4f"',
            'last_modified' => '2026-09-10T12:00:00Z',
        ]);
        $this->assertSame('image/jpeg', $meta->contentType);
        $this->assertNull($meta->cacheControl);
        $this->assertSame([], $meta->metadata);
    }

    public function testPresignedUrlMapsBothDirections(): void
    {
        $up = PresignedUrl::fromArray(['upload_url' => 'https://s3/up?sig=x', 'expires_in' => 3600]);
        $this->assertSame('https://s3/up?sig=x', $up->url);
        $this->assertSame(3600, $up->expiresIn);

        $down = PresignedUrl::fromArray(['download_url' => 'https://s3/down?sig=y', 'expires_in' => 3600]);
        $this->assertSame('https://s3/down?sig=y', $down->url);
    }

    public function testSessionTokenPairAndUser(): void
    {
        $session = Session::fromArray([
            'access_token' => 'eyJ...',
            'refresh_token' => '9f8e7d6c5b4a',
            'expires_in' => 900,
            'token_type' => 'Bearer',
            'user' => [
                'id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
                'email' => 'user@example.com',
                'name' => 'Sample User',
                'email_verified' => true,
                'provider' => 'email',
                'totp_enabled' => false,
                'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 0,
                'created_at' => '2026-09-01T10:15:00Z',
            ],
        ]);
        $this->assertSame('eyJ...', $session->accessToken);
        $this->assertInstanceOf(User::class, $session->user);
        $this->assertSame('user@example.com', $session->user->email);
        $this->assertNull($session->user->lastLoginAt);

        $pair = TokenPair::fromArray([
            'access_token' => 'a',
            'refresh_token' => 'b',
            'expires_in' => 900,
            'token_type' => 'Bearer',
        ]);
        $this->assertSame('b', $pair->refreshToken);
    }

    public function testTotpEnrollmentAndConfirmation(): void
    {
        $enroll = TotpEnrollment::fromArray([
            'secret' => 'JBSWY3DPEHPK3PXP',
            'otpauth_uri' => 'otpauth://totp/My%20App:user%40example.com?secret=JBSWY3DPEHPK3PXP',
        ]);
        $this->assertSame('JBSWY3DPEHPK3PXP', $enroll->secret);

        $voluntary = TotpConfirmation::fromArray([
            'enabled' => true,
            'recovery_codes' => ['K7T2M-9BQXR', '4WD8N-YH3ZC'],
        ]);
        $this->assertTrue($voluntary->enabled);
        $this->assertCount(2, $voluntary->recoveryCodes);
        $this->assertNull($voluntary->session);

        $forced = TotpConfirmation::fromArray([
            'enabled' => true,
            'recovery_codes' => ['K7T2M-9BQXR'],
            'access_token' => 'eyJ...',
            'refresh_token' => '9f8e',
            'expires_in' => 900,
            'token_type' => 'Bearer',
            'user' => [
                'id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
                'email' => 'user@example.com',
                'name' => 'Sample User',
                'email_verified' => true,
                'provider' => 'email',
                'totp_enabled' => true,
                'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 10,
                'created_at' => '2026-09-01T10:15:00Z',
            ],
        ]);
        $this->assertInstanceOf(Session::class, $forced->session);
        $this->assertSame('eyJ...', $forced->session->accessToken);
    }

    public function testLoginResultFactoryPicksVariant(): void
    {
        $success = LoginResultFactory::fromArray([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 900, 'token_type' => 'Bearer',
            'user' => [
                'id' => 'u', 'email' => 'x@example.com', 'name' => '', 'email_verified' => true,
                'provider' => 'email', 'totp_enabled' => false, 'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 0, 'created_at' => '2026-09-01T10:15:00Z',
            ],
        ]);
        $this->assertInstanceOf(LoginSuccess::class, $success);

        $challenge = LoginResultFactory::fromArray([
            'two_fa_required' => true,
            'challenge_token' => '4c1d8ab2e3f5',
            'methods' => ['totp'],
            'phone_hint' => '',
        ]);
        $this->assertInstanceOf(TwoFaRequired::class, $challenge);
        $this->assertSame('4c1d8ab2e3f5', $challenge->challengeToken);
        $this->assertSame(['totp'], $challenge->methods);

        $enroll = LoginResultFactory::fromArray([
            'two_fa_enrollment_required' => true,
            'enroll_token' => '7b2e9c40a1d6',
            'methods' => ['totp'],
        ]);
        $this->assertInstanceOf(TwoFaEnrollmentRequired::class, $enroll);
        $this->assertSame('7b2e9c40a1d6', $enroll->enrollToken);
    }

    public function testRegisterResultFactoryPicksVariant(): void
    {
        $success = RegisterResultFactory::fromArray([
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 900, 'token_type' => 'Bearer',
            'user' => [
                'id' => 'u', 'email' => 'x@example.com', 'name' => '', 'email_verified' => false,
                'provider' => 'email', 'totp_enabled' => false, 'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 0, 'created_at' => '2026-09-01T10:15:00Z',
            ],
        ]);
        $this->assertInstanceOf(RegisterSuccess::class, $success);

        $pending = RegisterResultFactory::fromArray([
            'message' => 'account created, please verify your email',
            'user' => [
                'id' => 'u', 'email' => 'x@example.com', 'name' => '', 'email_verified' => false,
                'provider' => 'email', 'totp_enabled' => false, 'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 0, 'created_at' => '2026-09-01T10:15:00Z',
            ],
        ]);
        $this->assertInstanceOf(VerificationRequired::class, $pending);
        $this->assertStringContainsString('verify your email', $pending->message);
    }
}
