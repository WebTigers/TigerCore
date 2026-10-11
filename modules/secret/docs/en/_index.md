<!-- tiger:doc
title: Secrets & key store
order: 70
visibility: admin
-->

# Secrets & the key store

Tiger resolves every sensitive value — an API key, a provider secret, an encryption key — through one
provider-neutral facade, `Tiger_Secret`. **Where** a secret lives is a configuration choice; the code that
needs it never cares. By default nothing changes from how Tiger has always worked: secrets stay encrypted
in your config. When you run on a cloud that offers a key store, you point one setting at it and your boxes
read their secrets from there instead — nothing baked into an image, nothing copied by hand.

## You usually don't need to do anything

The default driver is **`local`** — an encrypted value in `local.ini` or the config tier, decrypted with
your install's `tiger.crypto.key`. That's exactly how Tiger behaved before this feature existed. A
shared-hosting site needs no key store and no cloud SDK; this whole layer stays invisible.

## Choosing a backend

Set `tiger.secret.driver` to pick where bare secret lookups resolve from:

| Driver | Secrets live in | Needs |
|---|---|---|
| `local` (default) | an encrypted value in your config | nothing |
| `env` | environment variables | nothing |
| `file` | files (e.g. `/run/secrets/…`, systemd/Docker/Kubernetes secrets) | nothing |
| `ssm` | **AWS SSM Parameter Store** (SecureString) | the AWS SDK + an instance IAM role |
| `secretsmanager` | **AWS Secrets Manager** | the AWS SDK + an instance IAM role |
| `chain` | tried in order (e.g. SSM, then env, then local) | per the listed drivers |

```ini
; shared hosting / dev — the default; omit it entirely and you get this
tiger.secret.driver = local

; a cloud box reading from AWS Parameter Store via its instance role
tiger.secret.driver      = ssm
tiger.secret.ssm.region  = us-east-1
tiger.secret.ssm.prefix  = /tiger/prod/node

; resilient: the store if reachable, else an env var, else the local encrypted value
tiger.secret.driver = chain
tiger.secret.chain  = ssm,env,local
```

## Pointing a single value at a store

Any secret in your config can be a plain value, an encrypted value, **or a reference**. A reference is
`scheme:path`, and it's resolved on read:

```ini
tiger.hosting.registrar.opensrs.api_key = ssm:/tiger/prod/relay/opensrs_api_key
tiger.hosting.storage.r2.secret         = env:R2_SECRET
tiger.something.key                      = file:/run/secrets/something
```

A value with no recognized scheme is used as-is (plaintext), so mixing references and plain values in the
same file is fine.

## Why the cloud path is the safe one

When a box reads its secrets from a store, it authenticates with its **cloud IAM role**, not a secret you
put on the box. So nothing sensitive is baked into a machine image and nothing travels in start-up data —
the box boots with a role and pulls exactly the secrets it's allowed to. This is also what lets the
encryption key itself live in the store: the store trusts the role, not the key.

## What never leaves

Secrets are resolved, used, and discarded — they're never written to logs. The admin tooling that tests a
reference reports only whether it resolved, never the value itself.
