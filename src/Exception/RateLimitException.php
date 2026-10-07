<?php

namespace Rosreestr\Parser\Exception;

use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Throwable;

final class RateLimitException extends RuntimeException implements GuzzleException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
        public readonly string $route,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Rosreestr address search is rate limited on route %s. Retry after %d second(s).',
                $route,
                $retryAfterSeconds,
            ),
            429,
            $previous,
        );
    }
}
