#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
action="${1:-}"; shift || true
case "$action" in
  test)
    image="${DEVFLOW_PHP_IMAGE:-goskadastr-dev-web}"
    docker image inspect "$image" >/dev/null 2>&1 || { echo "missing local PHP 8.2 image: $image" >&2; exit 2; }
    docker run --rm --user "$(id -u):$(id -g)" \
      -v "$ROOT:/app" -w /app "$image" sh -lc \
      'composer validate --strict --no-check-publish && composer install --prefer-dist --no-interaction --no-progress && composer audit --no-interaction && composer test'
    ;;
  deploy)
    echo 'library has no deploy step' >&2
    exit 2
    ;;
  *) echo "unknown adapter action: $action" >&2; exit 2 ;;
esac
