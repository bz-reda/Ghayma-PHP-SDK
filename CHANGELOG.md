# Changelog

All notable changes to `ghayma/sdk` are documented here. This project adheres to [Semantic Versioning](https://semver.org/).

## 0.2.0 - 2026-09-29

OAuth sign-in surfaces the app's second factor (TOTP 2FA) instead of a session.

- `exchangeCode()` and `signInWithIdToken()` throw the new `TwoFactorRequiredException` when the app's 2FA policy applies to the user. No session was created: `$e->result` is the pending step, a `TwoFaRequired` (finish with `verify2fa()`) or a `TwoFaEnrollmentRequired` (finish with `enrollTotp()` + `confirmTotp()`). `errorCode` is `two_fa_required` or `two_fa_enrollment_required`; `status` is 200.
- The exception extends `GhaymaException`, so an existing `catch (GhaymaException $e)` treats the pending step as a stopped sign-in. Public signatures are unchanged, and both methods still return the `Session` when no second factor applies.
- `spec/auth.v1.yaml`: `POST /oauth/exchange` and `POST /oauth/id-token` now answer like `POST /login`, with a session or a pending second-factor step.
- Upgrade note: 0.1.0 cannot decode the pending step; against the updated auth service those two methods fail with a PHP `ValueError` (no session is issued).

## 0.1.0 - 2026-09-12

Initial release.

- `Ghayma` — admin client authenticated with a project API key, with `auth`, `storage` and `databases` sub-clients over the runtime contract (`spec/runtime.v1.yaml`).
- `GhaymaAuth` — stateless end-user auth client keyed by an app slug and optional server key, over the auth contract (`spec/auth.v1.yaml`). Server key and client IP are forwarded together on the rate-limited endpoints.
- Bring-your-own HTTP: depends on the PSR-18/PSR-17 interfaces and discovers an installed client via php-http/discovery; a client and factories may be passed explicitly.
- Typed, immutable DTOs; a `GhaymaException` hierarchy (`Unauthorized`, `Forbidden`, `NotFound`, `RateLimited`, `InvalidGrant`, `InvalidToken`, `Network`).
- `login()`/`register()` return small result hierarchies matched with `instanceof`.
- Conformance-tested against Prism mocks of both published contracts.
