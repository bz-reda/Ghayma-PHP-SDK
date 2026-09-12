<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;
use Ghayma\Sdk\Enum\Provider;

/** An end user of the auth app, as the auth service renders it. */
final readonly class User
{
    use DecodesData;

    /**
     * @param array<string, mixed> $metadata    user-owned bag (accepted by updateUser; not rendered here)
     * @param array<string, mixed> $appMetadata developer-owned bag, embedded in the access token
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
        public bool $emailVerified,
        public Provider $provider,
        public array $metadata,
        public array $appMetadata,
        public bool $totpEnabled,
        public bool $whatsappOtpEnabled,
        public int $recoveryCodesLeft,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastLoginAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: self::str($d, 'id'),
            email: self::str($d, 'email'),
            name: self::str($d, 'name'),
            avatarUrl: self::nstr($d, 'avatar_url'),
            emailVerified: self::bool($d, 'email_verified'),
            provider: Provider::from(self::str($d, 'provider')),
            metadata: self::map($d, 'metadata'),
            appMetadata: self::map($d, 'app_metadata'),
            totpEnabled: self::bool($d, 'totp_enabled'),
            whatsappOtpEnabled: self::bool($d, 'whatsapp_otp_enabled'),
            recoveryCodesLeft: self::int($d, 'recovery_codes_left'),
            createdAt: self::date($d, 'created_at'),
            lastLoginAt: self::ndate($d, 'last_login_at'),
        );
    }
}
