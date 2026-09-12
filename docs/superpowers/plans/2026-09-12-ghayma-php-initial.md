# `ghayma/sdk` (PHP) 0.1.0 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A PHP client for Ghayma with two entry classes — `Ghayma` (admin, project key `gsk_`, api.ghayma.tech) and `GhaymaAuth` (end-user auth, app slug + server key `ghs_`, auth.ghayma.tech) — typed against the two published contracts, tested with PHPUnit and against Prism mocks of both, ready to publish as `ghayma/sdk` 0.1.0.

**Architecture:** One package, PSR-4 root `Ghayma\Sdk\`. A shared `Http\Transport` (PSR-18 client + PSR-17 factories, discovered via php-http/discovery, overridable) does URL/headers/JSON/error-mapping. `Ghayma` composes three admin sub-clients (`->auth`, `->storage`, `->databases`) over the runtime contract; `GhaymaAuth` is a stateless auth client over the auth contract (the caller passes tokens — no session storage, no refresh timer). Immutable `readonly` DTOs, one `GhaymaException` hierarchy.

**Tech Stack:** PHP >=8.2 (dev 8.5.10), Composer 2.10; `psr/http-client`, `psr/http-factory`, `psr/http-message`, `php-http/discovery`; dev `phpunit/phpunit ^11`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`, `guzzlehttp/guzzle`, `nyholm/psr7`. Prism 5 via `npx` for conformance.

**Spec (read first):** `/Users/bouzi/Projects/THROCT/GHAYMA/PHP-SDK-DESIGN-2026-09-12.md` — §4 is the fixed public surface. The two contracts are vendored at `spec/runtime.v1.yaml` (36 ops) and `spec/auth.v1.yaml` (29 ops) — every wire shape comes from them. Behaviour parity reference (read-only, do not modify): the JS SDK — `git -C /Users/bouzi/Projects/THROCT/GHAYMA/SDK show origin/main:src/index.ts` (server entry `Ghayma`), `src/auth/index.ts`, `src/storage/index.ts`, `src/database/index.ts` (admin sub-clients), `src/client/index.ts` (end-user auth). Names follow PHP idiom; behaviour follows the JS client and the contracts.

## Global Constraints

- Work only in the worktree `/Users/bouzi/Projects/THROCT/worktrees/PHP-SDK/feat-initial-sdk` (branch `feat/initial-sdk`, base = the scaffold on `main`).
- Every wire field, status and error string comes from the vendored specs; never invent. When the JS SDK and a spec disagree, the spec wins (it was generated from the handlers) — list any discrepancy in the PR body.
- No hard HTTP-client dependency: depend on the PSR interfaces + `php-http/discovery`; accept an optional `ClientInterface`/factories in the constructor; fall back to discovery. Guzzle + nyholm are dev/suggest only.
- PHP `>=8.2` — no syntax newer than 8.2 (readonly promoted properties and enums are fine; avoid 8.3+ features).
- Before every commit that touches code: `composer validate --strict`, `vendor/bin/php-cs-fixer fix --dry-run --diff`, `vendor/bin/phpstan analyse --level 8 src`, `vendor/bin/phpunit` (unit) — all green. Before the final task also `tool/conformance.sh` green and `composer archive --dry-run` clean.
- NO AI attribution anywhere (commits, comments, files, composer.json). Minimal clean comments; short docblocks on public methods (param/return types are in signatures, so keep prose short).
- Do NOT push, tag or publish. After the final task write `.superpowers/pr-body.md` (untracked; `.superpowers/` is gitignored). Commit after each task.

## Public surface (fixed — design §4)

`Ghayma` (project key): `->auth` = AuthAppsClient{ `listApps()`, `getApp(string $id)`, `stats($id)`, `resetLink($id, string $email)`, `listUsers($id, ?int $page, ?int $limit)`, `getUser($id, $userId)`, `deleteUser($id, $userId)`, `disableUser($id, $userId)`, `enableUser($id, $userId)`, `reset2fa($id, $userId)`, `setAppMetadata($id, $userId, array $metadata)` }; `->storage` = StorageClient{ `listBuckets()`, `getBucket($id)`, `credentials($id)`, `presignUpload($id, string $key, ?int $expiry)`, `presignDownload($id, string $key, ?int $expiry)`, `listObjects($id, ?string $prefix)`, `deleteObject($id, string $key)`, `uploadObject($id, string $key, string|StreamInterface $body, ?string $contentType)`, `downloadObject($id, string $key)`, `objectInfo($id, string $key)`, `deleteBatch($id, array $keys)`, `deletePrefix($id, string $prefix)` }; `->databases` = DatabasesClient{ `list()`, `get($id)`, `credentials($id)`, `metrics($id)` }. Auth-app `$id` is a UUID (per the runtime contract).

`GhaymaAuth` (app slug + optional server key): `register(...)→RegisterResult`, `login(...)→LoginResult`, `verify2fa($challengeToken,$code)→Session`, `enrollTotp(?$enrollToken)→TotpEnrollment`, `confirmTotp($code,?$enrollToken)→TotpConfirmation`, `regenerateRecoveryCodes($accessToken,$password,$code)→string[]`, `disable2fa($accessToken,$password,$code)`, `refresh($refreshToken)→TokenPair`, `logout($refreshToken)`, `forgotPassword($email,?$clientIp)`, `resetPassword($token,$password)`, `verifyResetToken($token)→ResetTokenInfo`, `resendVerification($email)`, `getUser($accessToken)→User`, `updateUser($accessToken,...)→User`, `changePassword($accessToken,$current,$new)`, `changeEmail($accessToken,$newEmail,$currentPassword)→EmailChangeRequest`, `cancelEmailChange($accessToken)`, `deleteAccount($accessToken,?$password)`, `googleAuthUrl($redirectUri,?$codeChallenge)→string`, `githubAuthUrl(...)→string`, `exchangeCode($code,$codeVerifier,?$clientIp)→Session`, `signInWithIdToken($idToken,?$nonce,?$clientIp)→Session`. Stateless: methods that act on a signed-in user take the `accessToken` as a parameter; the client stores nothing. `clientIp` is sent (with the server key) only where the auth endpoints are rate-limited by caller.

---

### Task 1: composer.json, tooling, CI, skeleton

**Files:** `composer.json`, `phpunit.xml.dist`, `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `src/` skeleton (empty namespaced dirs with a `.gitkeep` or the first real file in Task 2), `tool/sync-spec.sh`, `.github/workflows/ci.yml`.

- [ ] `composer.json`: `name: ghayma/sdk`, `description`, `type: library`, `license: MIT`, `require: {php: ">=8.2", psr/http-client: ^1.0, psr/http-factory: ^1.0, psr/http-message: ^2.0, php-http/discovery: ^1.19}`, `require-dev: {phpunit/phpunit: ^11, phpstan/phpstan: ^2, friendsofphp/php-cs-fixer: ^3, guzzlehttp/guzzle: ^7, nyholm/psr7: ^1.8}`, `suggest: {guzzlehttp/guzzle: "A PSR-18 client", nyholm/psr7: "PSR-7/17 messages"}`, `autoload PSR-4 Ghayma\\Sdk\\ → src/`, `autoload-dev Ghayma\\Sdk\\Tests\\ → tests/`, `config.allow-plugins.php-http/discovery: true`, no `version` field. Run `composer update` to resolve; record resolved versions.
- [ ] `phpunit.xml.dist`: testsuites `unit` (tests/Unit) and `conformance` (tests/Conformance); default run excludes the `conformance` group (`<groups><exclude><group>conformance</group></exclude></groups>`), so `phpunit` alone needs no Prism.
- [ ] `phpstan.neon.dist`: level 8, paths `src`.
- [ ] `.php-cs-fixer.dist.php`: `@PSR12` + `declare_strict_types`, over `src` and `tests`.
- [ ] `tool/sync-spec.sh`: curl both served docs to `spec/runtime.v1.yaml` and `spec/auth.v1.yaml`; CI runs it then `git diff --exit-code spec/`.
- [ ] `.github/workflows/ci.yml`: matrix php 8.2/8.3/8.4/8.5 — `shivammathur/setup-php`, `composer validate --strict`, `composer install`, php-cs-fixer `--dry-run`, phpstan, phpunit; a spec-freshness step; a separate `conformance` job (php 8.4 + `actions/setup-node@v4` node 22) running `tool/conformance.sh`.
- [ ] `composer validate --strict` clean; commit `chore: package skeleton, tooling and CI`.

### Task 2: Exceptions

**Files:** `src/Exception/*.php`, `tests/Unit/ExceptionTest.php`.

- `GhaymaException extends \RuntimeException` with `public readonly int $status`, `public readonly string $errorCode`, `public readonly ?int $retryAfter`. Subclasses (all extend it): `UnauthorizedException` (401), `ForbiddenException` (403), `NotFoundException` (404), `RateLimitedException` (429, `retryAfter`), `InvalidGrantException`, `InvalidTokenException`, `NetworkException` (transport, status 0). A static `GhaymaException::fromResponse(int $status, array $body, ?int $retryAfter)` maps to the right subclass by `body['code']` then by status.
- [ ] Test: `fromResponse` picks the subclass for each code/status; `retryAfter` carried on 429; unknown → base with `errorCode` default. Commit `feat: exception hierarchy`.

### Task 3: HTTP transport

**Files:** `src/Http/Transport.php`, `src/Http/ClientResolver.php`, `tests/Unit/TransportTest.php`.

- `ClientResolver`: given optional `ClientInterface`, `RequestFactoryInterface`, `StreamFactoryInterface`, return them or discover via `Psr18ClientDiscovery` / `Psr17FactoryDiscovery`.
- `Transport(string $baseUrl, ClientInterface $c, RequestFactoryInterface $rf, StreamFactoryInterface $sf, array $defaultHeaders)`: `request(string $method, string $path, ?array $json = null, array $headers = [], ?string $rawBody = null, ?string $contentType = null): array`. Builds `$baseUrl.$path`, sets Accept/Content-Type, merges default + per-call headers, JSON-encodes `$json`; sends; on a non-2xx decodes `{error, code}` and throws `GhaymaException::fromResponse(...)` with `Retry-After` parsed (seconds or HTTP-date); on a PSR-18 `ClientExceptionInterface` throws `NetworkException`; returns decoded array (`[]` for 204/empty). A `requestRaw(...)` variant returns the `ResponseInterface` for object download.
- Default headers: `Ghayma` sets `Authorization: Bearer <gsk_>`; `GhaymaAuth` sets the server-key header + client-IP per call (both-or-neither, IP-literal guarded) and the bearer only where an endpoint is user-authenticated.
- [ ] Tests with a stub `ClientInterface` recording the `RequestInterface`: URL, method, headers, body for a POST; bearer; both-or-neither server-key+IP; 400-with-code, 401-without-code, 429-with-`Retry-After: 7`, invalid-JSON body, a thrown `ClientExceptionInterface` → `NetworkException`. Commit `feat: PSR-18 transport with error mapping`.

### Task 4: DTOs

**Files:** `src/Model/*.php`, `src/Enum/*.php`, `tests/Unit/ModelTest.php`.

- Read the `components.schemas` of BOTH vendored specs and mirror each used schema as a `final readonly class` with promoted constructor properties and a `public static function fromArray(array $d): self`. Ignore unknown keys. Dates → `\DateTimeImmutable`. Cover: `AuthApp`, `AuthUser`, `AuthStats`, `Bucket`, `StorageObject`, `PresignedUrl` (map `upload_url`/`download_url`→`url`, note in a comment), `BucketCredentials`, `Database`, `DatabaseCredentials`, `DatabaseMetrics`, `Session`, `TokenPair`, `User`, `TotpEnrollment`, `TotpConfirmation`, `ResetTokenInfo`, `EmailChangeRequest`. Enums: `Provider {email,google,github}`, `DatabaseEngine {postgres,mongodb,redis}` (redis legacy). `LoginResult` interface + `LoginSuccess`/`TwoFaRequired`/`TwoFaEnrollmentRequired`, `RegisterResult` interface + `RegisterSuccess`/`VerificationRequired`, each with a `fromArray` factory on the interface that discriminates by the contract's keys.
- [ ] Test: parse each spec's `success`/variant example into its DTO and assert fields; the two result-interface factories pick the right variant. Commit `feat: DTOs from both contracts`.

### Task 5: Admin client (`Ghayma`)

**Files:** `src/Ghayma.php`, `src/Admin/{AuthAppsClient,StorageClient,DatabasesClient}.php`, `tests/Unit/AdminTest.php`.

- `Ghayma(string $projectKey, string $baseUrl = 'https://api.ghayma.tech', ?ClientInterface $http = null, ?RequestFactoryInterface $rf = null, ?StreamFactoryInterface $sf = null)`: resolves the client, builds a `Transport` with the bearer default header, exposes `public readonly AuthAppsClient $auth` / `StorageClient $storage` / `DatabasesClient $databases`. Each sub-client takes the `Transport` and implements its methods (design §4) against the runtime contract paths (`/api/v1/...`), returning DTOs. Object upload uses `requestRaw`/stream; download returns a small `ObjectContent` (bytes + content-type + length) from the raw response. Presign posts `{key, expiry?}`.
- [ ] Tests with a stub client: each method hits the right method+path, sends the right body, parses the DTO; `presignUpload` body is `{key,...}`; `listUsers` passes `page`/`limit` as query. Commit `feat: admin client (auth apps, storage, databases)`.

### Task 6: Auth client (`GhaymaAuth`)

**Files:** `src/GhaymaAuth.php`, `tests/Unit/AuthClientTest.php`.

- `GhaymaAuth(string $appSlug, string $baseUrl = 'https://auth.ghayma.tech', ?string $serverKey = null, ?ClientInterface $http = null, ...factories)`: `Transport` over `/v1/{appSlug}`. Implement every method in §4 against the auth contract; stateless — user-scoped calls take `string $accessToken`. `login`/`register` return the result interfaces. `googleAuthUrl`/`githubAuthUrl` build the URL string (percent-encode `redirect_uri`, add `code_challenge`+`code_challenge_method=S256` when given) without a request. `clientIp` forwarded (with the server key) on the rate-limited endpoints.
- [ ] Tests with a stub client: login three shapes → the right result class; register two shapes; verify2fa stores nothing but returns a `Session`; refresh returns a `TokenPair`; getUser sends the bearer; server-key+clientIp both-or-neither; the two URL builders match byte-for-byte expected strings (incl. a redirect URI with `~`/`!`); exchangeCode + signInWithIdToken bodies. Commit `feat: stateless end-user auth client`.

### Task 7: Conformance harness (both contracts)

**Files:** `tool/conformance.sh`, `tests/Conformance/*.php`, `tests/Conformance/PreferClient.php`.

- `PreferClient implements ClientInterface`: wraps a real PSR-18 client and adds a `Prefer` header set per test.
- `tool/conformance.sh`: start `npx --yes @stoplight/prism-cli@5 mock spec/runtime.v1.yaml -p 4010 --errors` and `... spec/auth.v1.yaml -p 4011 --errors`, poll both until up (max 60s each), run `vendor/bin/phpunit --testsuite conformance` with `GHAYMA_RUNTIME_URL=http://127.0.0.1:4010` / `GHAYMA_AUTH_URL=http://127.0.0.1:4011`, tear both down, propagate the code.
- Conformance tests (`@group conformance`, skipped unless the env URLs are set): every `Ghayma` and `GhaymaAuth` method against its `success` example, plus `login`→`two_fa_required`/`enrollment_required`, `register`→`verification_required`, `forbidden`, `not_found`, `invalid_grant`, `invalid_token`, and a `rate_limited` with `Retry-After`, selected via the `Prefer` header. Use the real discovered Guzzle client (a dev dep) wrapped in `PreferClient`.
- [ ] `tool/conformance.sh` green locally. Commit `test: conformance against both published contracts`.

### Task 8: Example, README, CHANGELOG

**Files:** `examples/admin.php`, `examples/auth.php`, `README.md`, `CHANGELOG.md`.

- Two runnable examples (project-key admin; server-side login). README: install (`composer require ghayma/sdk`), the two clients with quick starts, the BYO-HTTP-client note (and that Guzzle/nyholm are auto-discovered if installed), the stateless-auth model (you store the tokens), a Laravel snippet (construct in a service provider, no facade required), the `GhaymaException` table, server-key usage, console setup (Allowed Origins, Native client IDs) linking `https://docs.ghayma.cloud/guides/oauth-mobile`, and the contract/conformance note. CHANGELOG `## 0.1.0`.
- [ ] `composer validate`, cs-fixer, phpstan, phpunit green. Commit `docs: examples, README and changelog for 0.1.0`.

### Task 9: Final verification and PR

- [ ] `composer validate --strict`; `vendor/bin/php-cs-fixer fix --dry-run --diff` clean; `vendor/bin/phpstan analyse --level 8 src` clean; `vendor/bin/phpunit` (unit) green; `tool/conformance.sh` green; `composer archive --dry-run` lists no junk (spec/ and src/ in, tests/docs excluded via `.gitattributes export-ignore` — add one).
- [ ] `git status` clean apart from `.superpowers/`. Write `.superpowers/pr-body.md`: the two clients and what each covers, the two-contract conformance, the BYO-client and stateless-auth decisions, the handler-vs-JS discrepancies, and how to publish 0.1.0 (Reda: tag `v0.1.0`; Packagist auto-syncs from the GitHub push hook — confirm the repo is submitted to Packagist and the GitHub service hook is enabled). No attribution. Do NOT push.
