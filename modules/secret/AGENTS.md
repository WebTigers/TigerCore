# AGENTS.md — working on the `secret` module

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../../CAPABILITIES.md`](../../CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and
> module. Core probably already has it. **Assume a capability exists until you have grepped the index and
> confirmed it doesn't.**

Instructions for an AI assistant (or a new contributor) working on Tiger's **secret** module. For platform
conventions read the root **AGENTS.md**; this is the module-specific layer.

> Provider-neutral secrets / key-store resolution. `Tiger_Secret` is the facade; a backend is a config
> choice (`tiger.secret.driver`), never baked into the caller — the same pattern as `Tiger_Location`,
> `Tiger_Mail`, and the auth credential provider. AWS SSM is **an** adapter, never **the** interface.

## The one principle

**A secret reference is `scheme:path`; the facade dispatches by scheme; an unknown scheme (or no scheme)
is plaintext.** That single rule makes adoption a one-liner at an `*_enc` call site: replace
`Tiger_Crypto::decrypt($enc)` / plaintext-fallback logic with `Tiger_Secret::resolve($value)`, and the
same config now accepts a plaintext value, an `enc:` blob, or an `ssm:` / `env:` / `file:` / `vault:`
reference. Nothing a caller does is AWS-specific.

## Where things live

- `library/Tiger/Secret.php` — the facade (`resolve`, `get`, `register`, `put`, `adapter`, `has`, `schemes`).
- `library/Tiger/Secret/Adapter/Interface.php` — the backend contract (`available()` + `get()`).
- `library/Tiger/Secret/Writer/Interface.php` — the optional write side (`put` / `delete`), for ops tooling.
- `library/Tiger/Secret/Adapter/{Local,Env,File,Chain,Ssm,SecretsManager}.php` — the backends.
- `modules/secret/Bootstrap.php` — eager-wires the config-driven backends at boot (convenience only).

## The rules

- **`local` is the default and a no-op change.** With `tiger.secret.driver` unset, resolution is today's
  behavior (an inline `Tiger_Crypto` value via the `enc:` scheme). A shared-hosting install is unaffected
  and needs no SDK. Never make `local` require anything external.
- **The facade must work with no Bootstrap.** Built-ins self-register lazily; ssm/secretsmanager/chain
  wire lazily from `tiger.secret.<scheme>.*`. Correctness never depends on this module being active — the
  Bootstrap only eager-wires for `schemes()` visibility + early error surfacing. Keep it that way.
- **Fail-soft, never fatal.** `available()` is capability-detection; `get()` returns null for
  missing/unreadable/SDK-absent and must not throw for the ordinary case. The facade also guards every
  call. A missing cloud SDK is a null, never a 500.
- **Cloud adapters are capability-gated and SDK-optional.** `Ssm`/`SecretsManager` reference
  `Aws\…\…Client` only behind `class_exists` (or an injected client), so core vendors no AWS SDK — the
  tiger-sdk-aws provider module supplies it on the fleet. Mirror `Hosting_Lib_Provisioner_Tigerden`.
- **Never log a secret.** Resolve, use, discard. No `Tiger_Log` line carries a resolved value.
- **The bootstrap secret is special.** `tiger.crypto.key` cannot come from the store it unlocks (it IS
  the decrypt key) — it arrives via the instance role at firstboot (the fleet) or stays in local.ini
  (shared hosting). The store's root of trust is the IAM role + KMS, not a pre-shared secret.

## Adding a backend

Implement `Tiger_Secret_Adapter_Interface` (+ `Tiger_Secret_Writer_Interface` if it writes), make
`available()` honest (return false when its dependency is absent), and register it under its scheme —
either from a module Bootstrap (`Tiger_Secret::register('vault', new …)`) or by adding a lazy case in
`Tiger_Secret::_lazyFromConfig()` for a config-driven one. Add unit tests under `tests/Unit/Secret/`
(the dep-free ones run in CI with no network; stub the client for a cloud one).
