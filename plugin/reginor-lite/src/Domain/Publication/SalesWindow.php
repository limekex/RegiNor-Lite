<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Publication;

use DateTimeImmutable;

/** Course dates may narrow the period's registration window, never open an unconfigured period. */
final class SalesWindow
{
    public static function bounds(array $period, array $course): array
    {
        $from = empty($period['sales_from']) ? null : new DateTimeImmutable($period['sales_from']);
        $until = empty($period['sales_until']) ? null : new DateTimeImmutable($period['sales_until']);
        if ($from && !empty($course['registration_from'])) { $from = max($from, new DateTimeImmutable($course['registration_from'])); }
        if ($until && !empty($course['registration_until'])) { $until = min($until, new DateTimeImmutable($course['registration_until'])); }
        return [$from, $until];
    }
}
