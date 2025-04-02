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
        $this->counter++;
    }

    public function getProxy(): string {
        $i = $this->counter % count($this->proxies);
        return $this->proxies[$i];
    }

    public static function loadFromFile(string $path):self {
        $proxies = file($path);
        $proxies = array_map('trim', $proxies);
        $proxies = array_filter($proxies, static fn($item) => $item);
        return new self($proxies);
    }


}
