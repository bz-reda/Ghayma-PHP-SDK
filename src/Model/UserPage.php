<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** One page of an auth app's end users. */
final readonly class UserPage
{
    use DecodesData;

    /** @param list<AuthUser> $users */
    public function __construct(
        public array $users,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    /** @param array<string, mixed> $d */
    public static function fromArray(array $d): self
    {
        return new self(
            users: self::objectList($d, 'users', AuthUser::fromArray(...)),
            total: self::int($d, 'total'),
            page: self::int($d, 'page'),
            perPage: self::int($d, 'per_page'),
        );
    }
}
