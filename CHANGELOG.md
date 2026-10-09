# Changelog

All notable changes to `ghayma/sdk` are documented here. This project adheres to [Semantic Versioning](https://semver.org/).

## 0.4.0 - 2026-10-09

The credentials reads return the site's own connection credential, and nothing else.

### Changed

- `databases->credentials()` and `storage->credentials()` answer a site's key (`GHAYMA_API_KEY`) with that site's own connection credential, never a database's own login or a bucket's owner key. While the connection waits for its own, they throw a `GhaymaException` with `status` 409 and `errorCode` `no_own_credential` (retry in a few minutes); an account token gets a 410 `shared_credentials_retired`. From a laptop: `ghayma connect --local` for a database, `ghayma env pull` for a bucket (your site's `STORAGE_*` variables; the S3 endpoint is public).
- `spec/runtime.v1.yaml` synced with the runtime contract.

### Added

- `DatabaseCredentials` and `BucketCredentials`: trailing `?string $level` (`read-only`/`connect` for a database, `read`/`read-write` for a bucket) and `?string $credential` (always `connection`), null when the response omits them.

### Removed

- `DatabaseCredentials::$externalAccess`, `$externalHost`, `$externalPort`, `$externalUrl` and `Database::$externalAccess`, `$externalHost`, `$externalPort`, as properties and constructor parameters: the old database external-access model is gone and the backend no longer sends them. A system outside Ghayma gets a credential of its own with `ghayma access add database <name> --name <principal>`. `Bucket::$externalAccess` (a public bucket) stays.
- Upgrade note: drop any read of these properties and any named argument passing them; positional `new Database(...)` calls lose the three arguments after `$replicaSet`.

### Deprecated

- `Database::$username`: not sent since October 2026 (the database's own login is never handed out), so it is null.

## 0.3.0 - 2026-10-06

Every call the auth service rate-limits per IP now takes `clientIp`.

- `verify2fa()`, `refresh()`, `resetPassword()`, `verifyResetToken()` and `resendVerification()` accept an optional trailing `?string $clientIp`. With a server key, it is forwarded as `X-Ghayma-Client-IP` alongside `X-Ghayma-Server-Key` (both or neither, as on `login()`), so these limits count the end user rather than your server.
- Additive: calls without `clientIp` send neither header, as before.
- README and `examples/auth.php`: forward the visitor's IP from the `X-Real-IP` header (`$_SERVER['HTTP_X_REAL_IP']` in plain PHP, `$request->header('X-Real-IP')` in Laravel). Laravel's `$request->ip()` is Ghayma's edge unless TrustProxies is configured.

## 0.2.0 - 2026-09-29

OAuth sign-in surfaces the app's second factor (TOTP 2FA) instead of a session.

- `exchangeCode()` and `signInWithIdToken()` throw the new `TwoFactorRequiredException` when the app's 2FA policy applies to the user. No session was created: `$e->result` is the pending step, a `TwoFaRequired` (finish with `verify2fa()`) or a `TwoFaEnrollmentRequired` (finish with `enrollTotp()` + `confirmTotp()`). `errorCode` is `two_fa_required` or `two_fa_enrollment_required`; `status` is 200.
- The exception extends `GhaymaException`, so an existing `catch (GhaymaException $e)` treats the pending step as a stopped sign-in. Public signatures are unchanged, and both methods still return the `Session` when no second factor applies.
- `spec/auth.v1.yaml`: `POST /oauth/exchange` and `POST /oauth/id-token` now answer like `POST /login`, with a session or a pending second-factor step.
- `signInWithIdToken()` refuses a Google account whose email Google has not verified: `email_not_verified` (403, `ForbiddenException`), through the existing error mapping with `errorCode` kept.
- Upgrade note: 0.1.0 cannot decode the pending step; against the updated auth service those two methods fail with a PHP `ValueError` (no session is issued).

## 0.1.0 - 2026-09-12

Initial release.

- `Ghayma` — admin client authenticated with a project API key, with `auth`, `storage` and `databases` sub-clients over the runtime contract (`spec/runtime.v1.yaml`).
- `GhaymaAuth` — stateless end-user auth client keyed by an app slug and optional server key, over the auth contract (`spec/auth.v1.yaml`). Server key and client IP are forwarded together on the rate-limited endpoints.
- Bring-your-own HTTP: depends on the PSR-18/PSR-17 interfaces and discovers an installed client via php-http/discovery; a client and factories may be passed explicitly.
- Typed, immutable DTOs; a `GhaymaException` hierarchy (`Unauthorized`, `Forbidden`, `NotFound`, `RateLimited`, `InvalidGrant`, `InvalidToken`, `Network`).
- `login()`/`register()` return small result hierarchies matched with `instanceof`.
- Conformance-tested against Prism mocks of both published contracts.
