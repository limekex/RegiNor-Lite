<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Capacity;

final class Retry
{
    /** Retry-After is a normalized duration in seconds, never shortened by local backoff. */
    public static function delay(int $failures, int $jitter, ?int $retryAfter = null): int
    {
        return max(min(3600, 60 * (2 ** min(6, max(0, $failures - 1)))) + max(0, min(30, $jitter)), max(0, $retryAfter ?? 0));
    }
}
