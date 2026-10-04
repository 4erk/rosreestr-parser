<?php

namespace Rosreestr\Parser\Proxy;

class ProxyManager
{
    public int $counter = 0;

    public function __construct(public array $proxies = [])
    {
    }

    public function next(): void
    {
        if ($this->proxies !== []) {
            $this->counter++;
        }
    }

    public function getProxy(): ?string
    {
        if ($this->proxies === []) {
            return null;
        }

        $i = $this->counter % count($this->proxies);

        return $this->proxies[$i];
    }

    public static function fromString(?string $proxy): ?self
    {
        $proxy = trim((string) $proxy);

        return $proxy === '' ? null : new self([$proxy]);
    }

    public static function loadFromFile(string $path): self
    {
        $proxies = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $proxies = array_map('trim', $proxies);
        $proxies = array_values(array_filter($proxies, static fn (string $item): bool => $item !== ''));

        return new self($proxies);
    }
}
