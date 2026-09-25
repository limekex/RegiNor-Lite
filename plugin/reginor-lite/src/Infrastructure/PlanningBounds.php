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
                /* translators: 1: valgt dato, 2: første tillatte dato, 3: siste tillatte dato eller tekst om åpen slutt. */
                __('Datoen %1$s ligger utenfor tillatt tidsrom: %2$s – %3$s. Kontroller første kursdato og sluttdato i kursoppsettet eller kursperioden.', 'reginor-lite'),
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
        // The inherited baseline stays inside the period. Only an explicit first date
        // extends this course's lower boundary; the period itself is never changed.
        self::date($group['start_date'], $period);
        if (!empty($group['first_date']) && $group['first_date'] < $period['start_date'] && empty($group['allow_early_start'])) {
            throw new InvalidArgumentException(sprintf(
                /* translators: 1: selected first date; 2: normal period start. */
                __('Første kursdato %1$s er før kursperiodens start %2$s. Kryss av «Tillat kursstart før kursperioden» under Flere valg hvis dette er et bevisst unntak, eller velg en dato fra periodens start.', 'reginor-lite'),
                $group['first_date'], $period['start_date']
            ));
        }
        $bounds = self::forGroup($group, $period);
        foreach (['first_date', 'latest_date'] as $key) {
            if ($group[$key] !== null) { self::date($group[$key], $bounds); }
        }
        self::breaks($group['breaks'], $bounds);
        if ($sessions) {
            foreach ($group['sessions'] as $session) {
                if ($session['status'] !== 'cancelled') { self::date($session['date'], $bounds); }
            }
        }
    }

    public static function forGroup(array $group, array $period): array
    {
        if (!empty($group['allow_early_start']) && !empty($group['first_date'])) { $period['start_date'] = min($period['start_date'], $group['first_date']); }
        return $period;
    }

    public static function warnings(array $group, array $period): array
    {
        if (empty($group['allow_early_start']) || empty($group['first_date']) || $group['first_date'] >= $period['start_date']) { return []; }
        return [sprintf(
            /* translators: 1: explicit first course date; 2: normal period start date. */
            __('Bekreftet unntak: Kurset starter %1$s, før kursperiodens vanlige start %2$s. Den tidligere datoen gjelder bare dette kurset og blokkerer ikke lagring eller publisering. Periodens datoer og synlighetsvindu er ikke endret.', 'reginor-lite'),
            $group['first_date'], $period['start_date']
        )];
    }
}
