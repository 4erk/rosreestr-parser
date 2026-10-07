<?php

namespace Rosreestr\Parser\RateLimit;

use Rosreestr\Parser\Exception\RateLimitException;
use RuntimeException;

final class AddressSearchRateLimiter
{
    public function __construct(
        private readonly string $stateDir,
        private readonly int $minIntervalMs = 1500,
        private readonly int $defaultCooldownSeconds = 10,
    ) {
        if ($minIntervalMs < 0) {
            throw new RuntimeException('Address-search minimum interval cannot be negative.');
        }

        if ($defaultCooldownSeconds < 1) {
            throw new RuntimeException('Address-search cooldown must be at least one second.');
        }
    }

    public static function fromEnvironment(): self
    {
        $stateDir = self::environmentValue('ROSREESTR_ADDRESS_RATE_STATE_DIR')
            ?? rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . 'rosreestr-parser-address-rate-limit';

        return new self(
            $stateDir,
            self::environmentInt('ROSREESTR_ADDRESS_MIN_INTERVAL_MS', 1500, 0, 60000),
            self::environmentInt('ROSREESTR_ADDRESS_429_COOLDOWN_SECONDS', 10, 1, 3600),
        );
    }

    public function beforeRequest(string $route): void
    {
        [$statePath, $lockPath] = $this->routePaths($route);
        $lock = fopen($lockPath, 'c+');

        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock Rosreestr address-search rate state.');
        }

        try {
            $state = $this->readState($statePath);
            $now = microtime(true);
            $cooldownUntil = (float) ($state['cooldown_until'] ?? 0.0);

            if ($cooldownUntil > $now) {
                throw new RateLimitException(
                    max(1, (int) ceil($cooldownUntil - $now)),
                    $route,
                );
            }

            $lastStart = (float) ($state['last_start'] ?? 0.0);
            $waitMs = max(
                0,
                (int) ceil(
                    $this->minIntervalMs
                    - max(0.0, ($now - $lastStart) * 1000),
                ),
            );

            if ($waitMs > 0) {
                usleep($waitMs * 1000);
            }

            $state['last_start'] = microtime(true);
            if ($cooldownUntil > 0) {
                unset($state['cooldown_until']);
            }

            $this->writeState($statePath, $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function markRateLimited(
        string $route,
        ?int $retryAfterSeconds = null,
    ): void {
        [$statePath, $lockPath] = $this->routePaths($route);
        $lock = fopen($lockPath, 'c+');

        $retryAfterSeconds = max(
            1,
            $retryAfterSeconds ?? $this->defaultCooldownSeconds,
        );

        if ($lock === false || !flock($lock, LOCK_EX)) {
            return;
        }

        try {
            $state = $this->readState($statePath);
            $state['cooldown_until'] = max(
                (float) ($state['cooldown_until'] ?? 0.0),
                microtime(true) + $retryAfterSeconds,
            );
            $this->writeState($statePath, $state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return;
    }

    private function routePaths(string $route): array
    {
        if (
            !is_dir($this->stateDir)
            && !mkdir($this->stateDir, 0770, true)
            && !is_dir($this->stateDir)
        ) {
            throw new RuntimeException('Unable to create Rosreestr rate-limit state directory.');
        }

        $key = hash('sha256', $route);

        return [
            $this->stateDir . DIRECTORY_SEPARATOR . $key . '.json',
            $this->stateDir . DIRECTORY_SEPARATOR . $key . '.lock',
        ];
    }

    private function readState(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeState(string $path, array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write Rosreestr rate-limit state.');
        }

        @chmod($tmp, 0660);

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to publish Rosreestr rate-limit state.');
        }
    }

    private static function environmentValue(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function environmentInt(
        string $name,
        int $default,
        int $minimum,
        int $maximum,
    ): int {
        $value = self::environmentValue($name);

        if ($value === null || !preg_match('/^\d+$/', $value)) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $value));
    }
}
