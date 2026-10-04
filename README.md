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
```

A standard Guzzle HTTP proxy is also supported:

```
ROSREESTR_PROXY=http://user:password@proxy.example:3128
```

Create the client with `Client::fromEnvironment($cookiePath)`. Relay configuration takes precedence over proxy configuration; when neither is set, direct transport is used.
