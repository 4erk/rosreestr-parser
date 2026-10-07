[Reading 56 lines from start (total: 56 lines, 0 remaining)]

## Repository topology

Development and releases are owned by `4erk/rosreestr-parser`.

- `origin` → `4erk/rosreestr-parser` — work/original repository.
- `upstream` → `goskadastr/rosreestr-parser`, release branch `main`.
- `medialuki` → `medialuki/parser-poiska-rr`, release branch `dev` (the repository's default branch).

The package name stays `rosreestr/parser` in every distribution repository. The distribution branches are mirrors of released parser history; normal development happens only in `origin`.

With dev-flow v0.2.0+, `.dev-flow/config.sh` publishes each SemVer release to both upstreams using non-force pushes. Before releasing, inspect the relationships with:

```bash
dev upstream status
```

A release is refused when any configured upstream branch has diverged from the current release branch. After a transient or partial publication failure, `dev upstream publish` safely retries the exact existing SemVer tag.

For a fresh local clone, configure both distribution remotes before release publication:

```bash
git remote add upstream git@github.com:goskadastr/rosreestr-parser.git
git remote add medialuki git@github.com:medialuki/parser-poiska-rr.git
```

**PHPStorm**
---------

Setting -> PHP -> Cli Interpreter -> Remote -> Docker Compose -> app

Setting -> PHP -> Servers -> name = swoole, path mapping = /var/www

Setting -> PHP -> Composer -> Remote Interpreter -> app

Setting -> PHP -> Test Frameworks -> Add Main local

Run docker-compose.yml

## Network transport

The client connects directly to Rosreestr by default.

For a development environment that cannot reach Rosreestr directly, use a restricted HTTPS relay:

```
ROSREESTR_RELAY_URL=https://example.com/reestr_rest/relay.php
ROSREESTR_RELAY_TOKEN=secret

For stronger development relay authentication, use a local RSA private key instead of a shared token:

ROSREESTR_RELAY_PRIVATE_KEY=/path/to/private.pem
```

A standard Guzzle HTTP proxy is also supported:

```
ROSREESTR_PROXY=http://user:password@proxy.example:3128
```

Address search can rotate through multiple proxies. Use a comma- or newline-separated list:

```
ROSREESTR_PROXIES=http://proxy-a:3128,http://proxy-b:3128
```

`ROSREESTR_PROXIES` takes precedence over the legacy single `ROSREESTR_PROXY` value.

Create the client with `Client::fromEnvironment($cookiePath)`. Relay configuration takes precedence over proxy configuration; when neither is set, direct transport is used.

Address search uses the same transport selection via `AddressSearchClient::fromEnvironment()`. It applies per-route pacing and a shared cooldown after HTTP 429. With multiple proxies, 429, network failures and retryable 5xx responses rotate to another proxy when one is available; a 429 is never retried immediately on the same proxy/IP. Relay transport is treated as one route because changing a local proxy would not change the relay server's upstream IP.

Optional address-search controls:

```
ROSREESTR_ADDRESS_MIN_INTERVAL_MS=1500
ROSREESTR_ADDRESS_429_COOLDOWN_SECONDS=10
ROSREESTR_ADDRESS_MAX_ATTEMPTS=2
ROSREESTR_ADDRESS_RATE_STATE_DIR=/path/to/shared/runtime-state
```

Rate limiting raises `Rosreestr\Parser\Exception\RateLimitException`, which exposes `retryAfterSeconds` and an opaque route identifier suitable for logs.

[executed on device: 4ERK-PC (e18afdfd-0125-49be-beb0-0f9bbac46842)]