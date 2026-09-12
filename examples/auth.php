<?php

declare(strict_types=1);

// End-user auth (server-side): sign an app's user in and read their profile.
// The client is stateless — you store the tokens it returns.
//
//   GHAYMA_APP_SLUG=my-app GHAYMA_AUTH_SERVER_KEY=ghs_... php examples/auth.php

require __DIR__ . '/../vendor/autoload.php';

use Ghayma\Sdk\Exception\GhaymaException;
use Ghayma\Sdk\GhaymaAuth;
use Ghayma\Sdk\Model\LoginSuccess;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;

$auth = new GhaymaAuth(
    appSlug: getenv('GHAYMA_APP_SLUG') ?: 'my-app',
    serverKey: getenv('GHAYMA_AUTH_SERVER_KEY') ?: null,
);

// Forward the real end-user IP so rate limits apply to them, not to your server.
// Only honoured together with a server key.
$clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    $result = $auth->login('user@example.com', 's3cret-passphrase', $clientIp);

    if ($result instanceof LoginSuccess) {
        $session = $result->session;
        // Persist $session->accessToken and $session->refreshToken in your own store.
        $user = $auth->getUser($session->accessToken);
        printf("signed in as %s\n", $user->email);

        // When the access token nears expiry, rotate the refresh token:
        $pair = $auth->refresh($session->refreshToken);
        printf("refreshed; new access token expires in %ds\n", $pair->expiresIn);
    } elseif ($result instanceof TwoFaRequired) {
        // Prompt for the user's TOTP or recovery code, then finish the login.
        $session = $auth->verify2fa($result->challengeToken, '123456');
        printf("signed in with 2FA as %s\n", $session->user->email);
    } elseif ($result instanceof TwoFaEnrollmentRequired) {
        // Enforced policy: enrol, render the QR, confirm — this completes the login.
        $enrollment = $auth->enrollTotp($result->enrollToken);
        printf("scan into an authenticator: %s\n", $enrollment->otpauthUri);
        $confirmation = $auth->confirmTotp('123456', $result->enrollToken);
        // Show $confirmation->recoveryCodes once; $confirmation->session is the signed-in session.
    }
} catch (GhaymaException $e) {
    fwrite(STDERR, sprintf("auth error [%d %s]: %s\n", $e->status, $e->errorCode, $e->getMessage()));
    exit(1);
}
