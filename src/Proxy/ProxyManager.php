<?php

namespace Rosreestr\Parser\Proxy;

class ProxyManager
{
    public int $counter = 0;

    public function __construct(public array $proxies = [])
    {
        $this->proxies = array_values(
            array_filter(
                array_map('trim', $this->proxies),
                static fn (string $item): bool => $item !== '',
            ),
        );
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

    public function count(): int
    {
        return count($this->proxies);
    }

    public function hasMultiple(): bool
    {
        return $this->count() > 1;
    }

    public static function fromString(?string $proxy): ?self
    {
        $proxy = trim((string) $proxy);

        if ($proxy === '') {
            return null;
        }

        $proxies = preg_split('/[\r\n,]+/', $proxy) ?: [];
        $manager = new self($proxies);

        return $manager->proxies === [] ? null : $manager;
    }

    public static function loadFromFile(string $path): self
    {
        $proxies = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return new self($proxies);
    }
}
