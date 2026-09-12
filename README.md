# ghayma/sdk

PHP client for the [Ghayma](https://ghayma.cloud) platform. Two entry classes over the two published contracts:

- **`Ghayma`** — the admin client, authenticated with a **project API key** (`gsk_…`). Sub-clients `->auth`, `->storage`, `->databases`. Server-side only.
- **`GhaymaAuth`** — the stateless end-user auth client for one app, keyed by an **app slug** and an optional **server key** (`ghs_…`). Registers, signs in and manages an app's end users.

Requires PHP 8.2+.

## Install

```bash
composer require ghayma/sdk
```

The SDK talks HTTP through the [PSR-18](https://www.php-fig.org/psr/psr-18/) / [PSR-17](https://www.php-fig.org/psr/psr-17/) interfaces and never hard-depends on a client. It discovers any installed implementation via [php-http/discovery](https://github.com/php-http/discovery). If your app doesn't already ship one, add Guzzle and nyholm:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

You can also pass your own client and factories explicitly (see [Bring your own HTTP client](#bring-your-own-http-client)).

## Admin client (`Ghayma`)

Authenticate with a project API key from **Project → Settings → API keys**.

```php
use Ghayma\Sdk\Ghayma;

$ghayma = new Ghayma('gsk_your_project_key');

// Storage
$ghayma->storage->uploadObject('bucket-id', 'images/photo.jpg', $bytes, 'image/jpeg');
$file = $ghayma->storage->downloadObject('bucket-id', 'images/photo.jpg');   // ObjectContent
$link = $ghayma->storage->presignDownload('bucket-id', 'images/photo.jpg');  // PresignedUrl

// Databases
$creds = $ghayma->databases->credentials('db-id');  // host, port, user, password, internalUrl…

// Auth apps — administer an app's end users
$page = $ghayma->auth->listUsers('auth-app-id', page: 1, limit: 20);         // UserPage
$ghayma->auth->setAppMetadata('auth-app-id', 'user-id', ['roles' => ['admin']]);
```

`->auth`, `->storage` and `->databases` return typed, immutable DTOs (`Ghayma\Sdk\Model\*`). See [`examples/admin.php`](examples/admin.php).

## End-user auth client (`GhaymaAuth`)

`GhaymaAuth` holds **no session state** — a PHP request is stateless, so the caller passes the `accessToken` (or `refreshToken`) it stored. There is no token storage, no auto-refresh timer.

```php
use Ghayma\Sdk\GhaymaAuth;
use Ghayma\Sdk\Model\LoginSuccess;
use Ghayma\Sdk\Model\TwoFaRequired;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;

$auth = new GhaymaAuth(appSlug: 'my-app', serverKey: getenv('GHAYMA_AUTH_SERVER_KEY') ?: null);

$result = $auth->login('user@example.com', $password, $clientIp);

if ($result instanceof LoginSuccess) {
    $session = $result->session;         // store $session->accessToken / ->refreshToken yourself
} elseif ($result instanceof TwoFaRequired) {
    $session = $auth->verify2fa($result->challengeToken, $code);
} elseif ($result instanceof TwoFaEnrollmentRequired) {
    $enrollment  = $auth->enrollTotp($result->enrollToken);   // render $enrollment->otpauthUri as a QR
    $confirmation = $auth->confirmTotp($code, $result->enrollToken);
}

// Later, on a subsequent request, with the tokens you stored:
$user = $auth->getUser($accessToken);
$pair = $auth->refresh($refreshToken);
```

`login()` returns a `LoginResult` (`LoginSuccess` | `TwoFaRequired` | `TwoFaEnrollmentRequired`); `register()` returns a `RegisterResult` (`RegisterSuccess` | `VerificationRequired`). Match with `instanceof`. See [`examples/auth.php`](examples/auth.php).

### Server key and client IP

When you construct `GhaymaAuth` with a server key, pass the real end-user IP so the auth service applies its rate limits to that user rather than to your server. The key and the IP travel **together or not at all** (a forwarded IP is only honoured from a caller that proves itself with the key), and the IP is validated as a literal before it is sent:

```php
$auth = new GhaymaAuth(appSlug: 'my-app', serverKey: 'ghs_…');
$auth->login($email, $password, $request->ip());   // sends X-Ghayma-Server-Key + X-Ghayma-Client-IP
```

`clientIp` is accepted on the rate-limited entry points: `register`, `login`, `forgotPassword`, `exchangeCode`, `signInWithIdToken`. Never expose the server key to a browser — build `GhaymaAuth` in server code only.

## OAuth

Build a provider sign-in URL for a login page, then redirect the browser to it:

```php
$url = $auth->googleAuthUrl('https://app.example.com/auth/callback');
// PKCE (mobile / SPA): pass the code_challenge; the callback returns a one-time code.
$url = $auth->googleAuthUrl('https://app.example.com/auth/callback', $codeChallenge);
```

Complete a native or PKCE sign-in server-side:

```php
$session = $auth->exchangeCode($code, $codeVerifier, $clientIp);        // PKCE one-time code
$session = $auth->signInWithIdToken($googleIdToken, $nonce, $clientIp); // native ID token
```

The `redirect_uri` must match one of the app's **Allowed Origins**, and native client IDs must be registered under **Native client IDs**, both in the console. See the mobile OAuth guide: <https://docs.ghayma.cloud/guides/oauth-mobile>.

## Errors

Every failure is a `Ghayma\Sdk\Exception\GhaymaException` carrying `status`, `errorCode` and (on a 429) `retryAfter`. Catch the base type broadly, or a subclass to branch:

| Exception | Raised when |
|---|---|
| `UnauthorizedException` | 401 — missing, malformed or expired credentials |
| `ForbiddenException` | 403 — valid credentials, not entitled to the route or action |
| `NotFoundException` | 404 — no such resource for these credentials |
| `RateLimitedException` | 429 — too many requests; `retryAfter` holds the cooldown in seconds when known |
| `InvalidGrantException` | an expired or already-spent token/code (`code: invalid_grant`) |
| `InvalidTokenException` | a token that failed verification (`code: invalid_token`) |
| `NetworkException` | a transport failure before any HTTP status (`status` 0) |

```php
use Ghayma\Sdk\Exception\RateLimitedException;
use Ghayma\Sdk\Exception\GhaymaException;

try {
    $auth->login($email, $password, $clientIp);
} catch (RateLimitedException $e) {
    // back off for $e->retryAfter seconds
} catch (GhaymaException $e) {
    // $e->status, $e->errorCode, $e->getMessage()
}
```

## Bring your own HTTP client

The constructors accept an optional PSR-18 client and PSR-17 request/stream factories; when omitted, they are discovered:

```php
$ghayma = new Ghayma('gsk_…', 'https://api.ghayma.tech', $psr18Client, $requestFactory, $streamFactory);
$auth   = new GhaymaAuth('my-app', 'https://auth.ghayma.tech', 'ghs_…', $psr18Client, $requestFactory, $streamFactory);
```

## Laravel

No facade or companion package is required — bind the clients in a service provider:

```php
use Ghayma\Sdk\Ghayma;
use Ghayma\Sdk\GhaymaAuth;

public function register(): void
{
    $this->app->singleton(Ghayma::class, fn () => new Ghayma(config('services.ghayma.api_key')));

    $this->app->singleton(GhaymaAuth::class, fn () => new GhaymaAuth(
        appSlug: config('services.ghayma.app_slug'),
        serverKey: config('services.ghayma.server_key'),
    ));
}
```

Then type-hint `Ghayma` or `GhaymaAuth` anywhere the container resolves. Laravel's Guzzle satisfies the PSR-18/17 discovery. Forward the caller's IP with `$request->ip()`.

## Contracts and conformance

Every wire shape comes from the two vendored OpenAPI contracts in [`spec/`](spec/): `runtime.v1.yaml` (the project-key surface) and `auth.v1.yaml` (the end-user auth surface). `tool/conformance.sh` boots a [Prism](https://github.com/stoplightio/prism) mock of each and runs the SDK against them; `tool/sync-spec.sh` refreshes the vendored copies from the served documents.

## License

MIT © Throct
