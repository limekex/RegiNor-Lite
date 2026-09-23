<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use InvalidArgumentException;
use function RegiNor\Lite\translate as __;

/** Teaching dates are independent of visibility and registration windows. */
final class PlanningBounds
{
    public static function date(string $date, array $period): void
    {
        if ($date < $period['start_date'] || ($period['end_date'] ?? null) !== null && $date > $period['end_date']) {
            throw new InvalidArgumentException(sprintf(
                /* translators: 1: valgt dato, 2: periodestart, 3: periodeslutt eller tekst om åpen slutt. */
                __('Datoen %1$s ligger utenfor kursperioden, som starter %2$s og slutter %3$s. Velg en dato innenfor perioden, eller endre periodens datoer først.', 'reginor-lite'),
                $date, $period['start_date'], $period['end_date'] ?? __('uten fast sluttdato', 'reginor-lite')
            ));
        }
    }

    public static function breaks(array $breaks, array $period): void
    {
        foreach ($breaks as $break) {
            self::date($break['from'], $period);
            self::date($break['until'], $period);
        }
    }

    public static function group(array $group, array $period, bool $sessions = false): void
    {
        foreach (['start_date', 'first_date', 'latest_date'] as $key) {
            if ($group[$key] !== null) { self::date($group[$key], $period); }
        }
        self::breaks($group['breaks'], $period);
        if ($sessions) {
            foreach ($group['sessions'] as $session) {
                if ($session['status'] !== 'cancelled') { self::date($session['date'], $period); }
            }
        }
    }
}
