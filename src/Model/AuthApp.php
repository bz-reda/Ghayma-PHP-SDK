<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/** An auth app's configuration, as the management plane renders it. */
final readonly class AuthApp
{
    use DecodesData;

    /**
     * @param list<string> $allowedOrigins
     * @param list<string> $googleNativeClientIds
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $projectId,
        public ?string $teamId,
        public string $name,
        public string $appId,
        public string $status,
        public array $allowedOrigins,
        public bool $emailVerificationRequired,
        public bool $googleOauthEnabled,
        public ?string $googleClientId,
        public array $googleNativeClientIds,
        public bool $githubOauthEnabled,
        public ?string $githubClientId,
        public string $authTierSlug,
        public bool $twoFaEnabled,
        public bool $smsEnabled,
        public string $twoFaPolicy,
        public string $resetUrl,
        public string $emailLocale,
        public int $jwtExpirySeconds,
        public int $refreshExpirySeconds,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: self::str($d, 'id'),
            userId: self::str($d, 'user_id'),
            projectId: self::str($d, 'project_id'),
            teamId: self::nstr($d, 'team_id'),
            name: self::str($d, 'name'),
            appId: self::str($d, 'app_id'),
            status: self::str($d, 'status'),
            allowedOrigins: self::strList($d, 'allowed_origins'),
            emailVerificationRequired: self::bool($d, 'email_verification_required'),
            googleOauthEnabled: self::bool($d, 'google_oauth_enabled'),
            googleClientId: self::nstr($d, 'google_client_id'),
            googleNativeClientIds: self::strList($d, 'google_native_client_ids'),
            githubOauthEnabled: self::bool($d, 'github_oauth_enabled'),
            githubClientId: self::nstr($d, 'github_client_id'),
            authTierSlug: self::str($d, 'auth_tier_slug'),
            twoFaEnabled: self::bool($d, 'two_fa_enabled'),
            smsEnabled: self::bool($d, 'sms_enabled'),
            twoFaPolicy: self::str($d, 'two_fa_policy'),
            resetUrl: self::str($d, 'reset_url'),
            emailLocale: self::str($d, 'email_locale'),
            jwtExpirySeconds: self::int($d, 'jwt_expiry_seconds'),
            refreshExpirySeconds: self::int($d, 'refresh_expiry_seconds'),
            createdAt: self::date($d, 'created_at'),
            updatedAt: self::date($d, 'updated_at'),
        );
    }
}
