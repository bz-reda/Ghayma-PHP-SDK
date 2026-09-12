# Changelog

All notable changes to `ghayma/sdk` are documented here. This project adheres to [Semantic Versioning](https://semver.org/).

## 0.1.0 - 2026-09-12

Initial release.

- `Ghayma` — admin client authenticated with a project API key, with `auth`, `storage` and `databases` sub-clients over the runtime contract (`spec/runtime.v1.yaml`).
- `GhaymaAuth` — stateless end-user auth client keyed by an app slug and optional server key, over the auth contract (`spec/auth.v1.yaml`). Server key and client IP are forwarded together on the rate-limited endpoints.
- Bring-your-own HTTP: depends on the PSR-18/PSR-17 interfaces and discovers an installed client via php-http/discovery; a client and factories may be passed explicitly.
- Typed, immutable DTOs; a `GhaymaException` hierarchy (`Unauthorized`, `Forbidden`, `NotFound`, `RateLimited`, `InvalidGrant`, `InvalidToken`, `Network`).
- `login()`/`register()` return small result hierarchies matched with `instanceof`.
- Conformance-tested against Prism mocks of both published contracts.
