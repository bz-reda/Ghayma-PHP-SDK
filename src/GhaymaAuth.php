<?php

declare(strict_types=1);

namespace Ghayma\Sdk;

use Ghayma\Sdk\Http\ClientResolver;
use Ghayma\Sdk\Http\Transport;
use Ghayma\Sdk\Model\DecodesData;
use Ghayma\Sdk\Model\EmailChangeRequest;
use Ghayma\Sdk\Model\LoginResult;
use Ghayma\Sdk\Model\LoginResultFactory;
use Ghayma\Sdk\Model\RegisterResult;
use Ghayma\Sdk\Model\RegisterResultFactory;
use Ghayma\Sdk\Model\ResetTokenInfo;
use Ghayma\Sdk\Model\Session;
use Ghayma\Sdk\Model\TokenPair;
use Ghayma\Sdk\Model\TotpConfirmation;
use Ghayma\Sdk\Model\TotpEnrollment;
use Ghayma\Sdk\Model\User;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The Ghayma end-user auth client for a single app, over the auth contract.
 *
 * Stateless by design: it stores no session. Methods that act on a signed-in
 * user take the caller-held `accessToken`; the caller stores the tokens. When
 * constructed with a server key, the per-call `clientIp` is forwarded (both or
 * neither) on the rate-limited endpoints so limits apply to the real end user.
 */
final class GhaymaAuth
{
    use DecodesData;

    /** Shape guard for a forwarded IP literal (IPv4/IPv6, optional zone id). */
    private const IP_LITERAL = '/^[0-9a-fA-F.:]+(%[0-9a-zA-Z._-]+)?$/';

    private readonly string $baseUrl;
    private readonly Transport $transport;

    public function __construct(
        private readonly string $appSlug,
        string $baseUrl = 'https://auth.ghayma.tech',
        private readonly ?string $serverKey = null,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $rf = null,
        ?StreamFactoryInterface $sf = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = new Transport(
            $this->baseUrl . '/v1/' . $appSlug,
            ClientResolver::client($http),
            ClientResolver::requestFactory($rf),
            ClientResolver::streamFactory($sf),
        );
    }

    // ── Registration & session ──────────────────────────────────────

    /** Create an end-user account. Returns a session, or a pending-verification result. */
    public function register(string $email, string $password, ?string $name = null, ?string $clientIp = null): RegisterResult
    {
        $body = ['email' => $email, 'password' => $password];
        if ($name !== null) {
            $body['name'] = $name;
        }

        return RegisterResultFactory::fromArray(
            $this->transport->request('POST', '/register', $body, $this->forwardHeaders($clientIp)),
        );
    }

    /** Exchange email and password for a session, or a pending second-factor step. */
    public function login(string $email, string $password, ?string $clientIp = null): LoginResult
    {
        return LoginResultFactory::fromArray(
            $this->transport->request('POST', '/login', ['email' => $email, 'password' => $password], $this->forwardHeaders($clientIp)),
        );
    }

    /** Finish a login that asked for a second factor, with a TOTP or recovery code. */
    public function verify2fa(string $challengeToken, string $code): Session
    {
        return Session::fromArray(
            $this->transport->request('POST', '/2fa/verify', ['challenge_token' => $challengeToken, 'code' => $code]),
        );
    }

    /** Rotate a refresh token for a new token pair. */
    public function refresh(string $refreshToken): TokenPair
    {
        return TokenPair::fromArray(
            $this->transport->request('POST', '/refresh', ['refresh_token' => $refreshToken]),
        );
    }

    /** Revoke one refresh token (always succeeds, whether or not it existed). */
    public function logout(string $refreshToken): void
    {
        $this->transport->request('POST', '/logout', ['refresh_token' => $refreshToken]);
    }

    // ── Two-factor ──────────────────────────────────────────────────

    /**
     * Mint a TOTP secret. Pass the `enrollToken` from a login under an enforced
     * policy; render the returned otpauth URI as a QR code, then confirm it.
     */
    public function enrollTotp(?string $enrollToken = null): TotpEnrollment
    {
        $body = $enrollToken !== null ? ['enroll_token' => $enrollToken] : [];

        return TotpEnrollment::fromArray(
            $this->transport->request('POST', '/2fa/totp/enroll', $body),
        );
    }

    /**
     * Confirm TOTP with a code from the authenticator. Returns the recovery codes
     * (shown once); on the enforced path the result also carries the session.
     */
    public function confirmTotp(string $code, ?string $enrollToken = null): TotpConfirmation
    {
        $body = ['code' => $code];
        if ($enrollToken !== null) {
            $body['enroll_token'] = $enrollToken;
        }

        return TotpConfirmation::fromArray(
            $this->transport->request('POST', '/2fa/totp/confirm', $body),
        );
    }

    /**
     * Replace the recovery codes with a fresh set (every previous code stops working).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(string $accessToken, string $password, string $code): array
    {
        $res = $this->transport->request(
            'POST',
            '/2fa/recovery/regenerate',
            ['password' => $password, 'code' => $code],
            $this->bearer($accessToken),
        );

        return self::strList($res, 'recovery_codes');
    }

    /** Turn the second factor off; requires the password and a currently-valid code. */
    public function disable2fa(string $accessToken, string $password, string $code): void
    {
        $this->transport->request(
            'POST',
            '/2fa/disable',
            ['password' => $password, 'code' => $code],
            $this->bearer($accessToken),
        );
    }

    // ── Password & verification ─────────────────────────────────────

    /** Send a password-reset email (answers success whether or not the address exists). */
    public function forgotPassword(string $email, ?string $clientIp = null): void
    {
        $this->transport->request('POST', '/forgot-password', ['email' => $email], $this->forwardHeaders($clientIp));
    }

    /** Set a new password with an emailed reset token (revokes every session). */
    public function resetPassword(string $token, string $password): void
    {
        $this->transport->request('POST', '/reset-password', ['token' => $token, 'password' => $password]);
    }

    /** Pre-check a reset token without consuming it; returns the account email for display. */
    public function verifyResetToken(string $token): ResetTokenInfo
    {
        return ResetTokenInfo::fromArray(
            $this->transport->request('POST', '/verify-reset-token', ['token' => $token]),
        );
    }

    /** Send the verification email again (no-op when already verified). */
    public function resendVerification(string $email): void
    {
        $this->transport->request('POST', '/resend-verification', ['email' => $email]);
    }

    // ── Account (bearer-authenticated) ──────────────────────────────

    /** Read the signed-in user's profile. */
    public function getUser(string $accessToken): User
    {
        $res = $this->transport->request('GET', '/me', null, $this->bearer($accessToken));

        return User::fromArray(self::map($res, 'user'));
    }

    /**
     * Update the signed-in user's own profile fields; only the fields given are written.
     *
     * @param array<string, mixed>|null $metadata the user-owned bag
     */
    public function updateUser(
        string $accessToken,
        ?string $name = null,
        ?string $avatarUrl = null,
        ?array $metadata = null,
    ): User {
        $body = [];
        if ($name !== null) {
            $body['name'] = $name;
        }
        if ($avatarUrl !== null) {
            $body['avatar_url'] = $avatarUrl;
        }
        if ($metadata !== null) {
            $body['metadata'] = (object) $metadata;
        }

        $res = $this->transport->request('PATCH', '/me', $body, $this->bearer($accessToken));

        return User::fromArray(self::map($res, 'user'));
    }

    /** Change the signed-in user's password (revokes every session). */
    public function changePassword(string $accessToken, string $current, string $new): void
    {
        $this->transport->request(
            'POST',
            '/change-password',
            ['current_password' => $current, 'new_password' => $new],
            $this->bearer($accessToken),
        );
    }

    /** Start changing the signed-in user's email; a confirmation link is emailed to the new address. */
    public function changeEmail(string $accessToken, string $newEmail, string $currentPassword): EmailChangeRequest
    {
        return EmailChangeRequest::fromArray($this->transport->request(
            'POST',
            '/email/change-request',
            ['new_email' => $newEmail, 'current_password' => $currentPassword],
            $this->bearer($accessToken),
        ));
    }

    /** Void any pending email change (succeeds even when nothing was pending). */
    public function cancelEmailChange(string $accessToken): void
    {
        $this->transport->request('DELETE', '/email/change-request', null, $this->bearer($accessToken));
    }

    /** Delete the signed-in user's account (email accounts confirm with their password). */
    public function deleteAccount(string $accessToken, ?string $password = null): void
    {
        $body = $password !== null ? ['password' => $password] : null;
        $this->transport->request('DELETE', '/me', $body, $this->bearer($accessToken));
    }

    // ── OAuth ───────────────────────────────────────────────────────

    /** Build the Google sign-in URL to redirect a browser to (adds PKCE params when a challenge is given). */
    public function googleAuthUrl(string $redirectUri, ?string $codeChallenge = null): string
    {
        return $this->oauthUrl('google', $redirectUri, $codeChallenge);
    }

    /** Build the GitHub sign-in URL to redirect a browser to (adds PKCE params when a challenge is given). */
    public function githubAuthUrl(string $redirectUri, ?string $codeChallenge = null): string
    {
        return $this->oauthUrl('github', $redirectUri, $codeChallenge);
    }

    /** Trade a PKCE one-time code for a session. */
    public function exchangeCode(string $code, string $codeVerifier, ?string $clientIp = null): Session
    {
        return Session::fromArray($this->transport->request(
            'POST',
            '/oauth/exchange',
            ['code' => $code, 'code_verifier' => $codeVerifier],
            $this->forwardHeaders($clientIp),
        ));
    }

    /** Sign in with a Google ID token obtained natively (iOS/Android), no browser involved. */
    public function signInWithIdToken(string $idToken, ?string $nonce = null, ?string $clientIp = null): Session
    {
        $body = ['provider' => 'google', 'id_token' => $idToken];
        if ($nonce !== null) {
            $body['nonce'] = $nonce;
        }

        return Session::fromArray($this->transport->request(
            'POST',
            '/oauth/id-token',
            $body,
            $this->forwardHeaders($clientIp),
        ));
    }

    // ── Internal ────────────────────────────────────────────────────

    private function oauthUrl(string $provider, string $redirectUri, ?string $codeChallenge): string
    {
        $url = $this->baseUrl . '/v1/' . $this->appSlug . '/auth/' . $provider
            . '?redirect_uri=' . self::encode($redirectUri);
        if ($codeChallenge !== null) {
            $url .= '&code_challenge=' . self::encode($codeChallenge) . '&code_challenge_method=S256';
        }

        return $url;
    }

    /** JavaScript `encodeURIComponent` parity, so redirect URIs are byte-for-byte identical across SDKs. */
    private static function encode(string $value): string
    {
        return strtr(rawurlencode($value), [
            '%21' => '!',
            '%2A' => '*',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
        ]);
    }

    /** @return array<string, string> */
    private function bearer(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
    }

    /**
     * Server key and client IP travel together or not at all: the service only
     * honours a forwarded IP from a caller that proves itself with the key.
     *
     * @return array<string, string>
     */
    private function forwardHeaders(?string $clientIp): array
    {
        if ($this->serverKey === null || $clientIp === null) {
            return [];
        }
        $ip = trim($clientIp);
        if ($ip === '' || preg_match(self::IP_LITERAL, $ip) !== 1) {
            return [];
        }

        return [
            'X-Ghayma-Server-Key' => $this->serverKey,
            'X-Ghayma-Client-IP' => $ip,
        ];
    }
}
