<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Admin;

use Ghayma\Sdk\Http\Transport;
use Ghayma\Sdk\Model\AuthApp;
use Ghayma\Sdk\Model\AuthStats;
use Ghayma\Sdk\Model\AuthUser;
use Ghayma\Sdk\Model\DecodesData;
use Ghayma\Sdk\Model\ResetLink;
use Ghayma\Sdk\Model\UserPage;

/**
 * Administer an auth app's end users from your server (the `auth` capability).
 * Reachable through {@see \Ghayma\Sdk\Ghayma::$auth}.
 */
final class AuthAppsClient
{
    use DecodesData;

    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    /**
     * List the auth apps in the key's project.
     *
     * @return list<AuthApp>
     */
    public function listApps(): array
    {
        $res = $this->transport->request('GET', '/api/v1/auth-apps');

        return self::objectList($res, 'auth_apps', AuthApp::fromArray(...));
    }

    /** Get one auth app's configuration. */
    public function getApp(string $id): AuthApp
    {
        $res = $this->transport->request('GET', '/api/v1/auth-apps/' . $id);

        return AuthApp::fromArray(self::map($res, 'auth_app'));
    }

    /** Get an auth app's aggregate statistics. */
    public function stats(string $id): AuthStats
    {
        $res = $this->transport->request('GET', '/api/v1/auth-apps/' . $id . '/stats');

        return AuthStats::fromArray(self::map($res, 'stats'));
    }

    /** Mint a live, single-use password-reset link for an end user (send it only to their verified email). */
    public function resetLink(string $id, string $email): ResetLink
    {
        $res = $this->transport->request('POST', '/api/v1/auth-apps/' . $id . '/reset-link', ['email' => $email]);

        return ResetLink::fromArray($res);
    }

    /**
     * Page through an auth app's end users. `$limit` maps to the contract's `per_page` (1–100).
     */
    public function listUsers(string $id, ?int $page = null, ?int $limit = null): UserPage
    {
        $query = [];
        if ($page !== null) {
            $query['page'] = $page;
        }
        if ($limit !== null) {
            $query['per_page'] = $limit;
        }
        $path = '/api/v1/auth-apps/' . $id . '/users';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }

        return UserPage::fromArray($this->transport->request('GET', $path));
    }

    /** Get one end user of an auth app. */
    public function getUser(string $id, string $userId): AuthUser
    {
        $res = $this->transport->request('GET', '/api/v1/auth-apps/' . $id . '/users/' . $userId);

        return AuthUser::fromArray(self::map($res, 'user'));
    }

    /** Delete an end user from the auth app. */
    public function deleteUser(string $id, string $userId): void
    {
        $this->transport->request('DELETE', '/api/v1/auth-apps/' . $id . '/users/' . $userId);
    }

    /** Disable an end user (block sign-in). */
    public function disableUser(string $id, string $userId): void
    {
        $this->transport->request('POST', '/api/v1/auth-apps/' . $id . '/users/' . $userId . '/disable');
    }

    /** Re-enable a previously disabled end user. */
    public function enableUser(string $id, string $userId): void
    {
        $this->transport->request('POST', '/api/v1/auth-apps/' . $id . '/users/' . $userId . '/enable');
    }

    /** Clear an end user's 2FA enrolment (support hammer for a locked-out user). */
    public function reset2fa(string $id, string $userId): void
    {
        $this->transport->request('POST', '/api/v1/auth-apps/' . $id . '/users/' . $userId . '/reset-2fa');
    }

    /**
     * Replace an end user's developer-owned app_metadata; `[]` clears it. The new
     * value reaches the user's access token at their next refresh.
     *
     * @param array<string, mixed> $metadata
     */
    public function setAppMetadata(string $id, string $userId, array $metadata): AuthUser
    {
        $res = $this->transport->request(
            'PATCH',
            '/api/v1/auth-apps/' . $id . '/users/' . $userId . '/app-metadata',
            ['app_metadata' => (object) $metadata],
        );

        return AuthUser::fromArray(self::map($res, 'user'));
    }
}
