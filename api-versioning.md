# API Versioning

The Panel's HTTP API is unversioned and carries a non-breaking guarantee: from 1.0 onward, a response field, envelope shape, or accepted request input only changes in ways existing API clients can ignore. New fields and endpoints may appear at any time, but nothing an existing client depends on is renamed, removed, or retyped.

Two things in this repository define that guarantee. The generated OpenAPI documents for the application and client APIs are exported and validated in CI, and the test fixture suite under `tests/Integration/Api/Fixtures` records the exact JSON every endpoint returns. Any change that alters one of those snapshots or the exported specs is a breaking API change and should be treated as such in review, regardless of how small the diff looks.

The full versioning policy, including how deprecations are announced and how long deprecated payload fields stick around, lives in the [Pelican documentation](https://pelican.dev/docs).
