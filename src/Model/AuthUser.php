<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;
use Ghayma\Sdk\Enum\Provider;

/** An end user of an auth app, as the management plane renders it. */
final readonly class AuthUser
{
    use DecodesData;

    /** @param array<string, mixed> $appMetadata */
    public function __construct(
        public string $id,
        public string $appId,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
        public bool $emailVerified,
        public Provider $provider,
        public bool $disabled,
        public array $appMetadata,
        public bool $phoneVerified,
        public bool $totpEnabled,
        public bool $whatsappOtpEnabled,
        public ?DateTimeImmutable $lastLoginAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            id: self::str($d, 'id'),
            appId: self::str($d, 'app_id'),
            email: self::str($d, 'email'),
            name: self::str($d, 'name'),
            avatarUrl: self::nstr($d, 'avatar_url'),
            emailVerified: self::bool($d, 'email_verified'),
            provider: Provider::from(self::str($d, 'provider')),
            disabled: self::bool($d, 'disabled'),
            appMetadata: self::map($d, 'app_metadata'),
            phoneVerified: self::bool($d, 'phone_verified'),
            totpEnabled: self::bool($d, 'totp_enabled'),
            whatsappOtpEnabled: self::bool($d, 'whatsapp_otp_enabled'),
            lastLoginAt: self::ndate($d, 'last_login_at'),
            createdAt: self::date($d, 'created_at'),
            updatedAt: self::date($d, 'updated_at'),
        );
    }
}
