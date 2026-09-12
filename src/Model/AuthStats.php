<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** Aggregate counters for an auth app plus its 20 most recent events. */
final readonly class AuthStats
{
    use DecodesData;

    /**
     * @param list<AuthProviderCount> $providerBreakdown
     * @param list<AuthEvent>         $recentEvents
     */
    public function __construct(
        public int $totalUsers,
        public int $verifiedUsers,
        public int $activeSessions,
        public int $signupsToday,
        public int $signupsWeek,
        public int $signupsMonth,
        public int $eventsToday,
        public int $eventsWeek,
        public int $loginsToday,
        public array $providerBreakdown,
        public array $recentEvents,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            totalUsers: self::int($d, 'total_users'),
            verifiedUsers: self::int($d, 'verified_users'),
            activeSessions: self::int($d, 'active_sessions'),
            signupsToday: self::int($d, 'signups_today'),
            signupsWeek: self::int($d, 'signups_week'),
            signupsMonth: self::int($d, 'signups_month'),
            eventsToday: self::int($d, 'events_today'),
            eventsWeek: self::int($d, 'events_week'),
            loginsToday: self::int($d, 'logins_today'),
            providerBreakdown: self::objectList($d, 'provider_breakdown', AuthProviderCount::fromArray(...)),
            recentEvents: self::objectList($d, 'recent_events', AuthEvent::fromArray(...)),
        );
    }
}
