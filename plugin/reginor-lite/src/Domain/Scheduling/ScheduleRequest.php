<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RegiNor\Lite\Domain\LocalDateTime;

final readonly class ScheduleRequest
{
    /** @param list<BreakPeriod> $periodBreaks @param list<BreakPeriod> $groupBreaks */
    public function __construct(
        public string $startDate,
        public int $weekday,
        public string $startTime,
        public string $endTime,
        public int $sessionCount,
        public array $periodBreaks = [],
        public array $groupBreaks = [],
        public string $timezone = 'Europe/Oslo',
        public ?string $latestDate = null,
        public ?string $firstDate = null,
    ) {
        LocalDateTime::date($startDate);
        LocalDateTime::validateTime($startTime);
        LocalDateTime::validateTime($endTime);
        LocalDateTime::timezone($timezone);
        if ($weekday < 1 || $weekday > 7 || $sessionCount < 1) {
            throw new InvalidArgumentException(__('Velg ukedag 1–7 og minst én kurskveld.', 'reginor-lite'));
        }
        if ($endTime <= $startTime) {
            throw new InvalidArgumentException(sprintf(/* translators: 1: sluttklokkeslett, 2: startklokkeslett. */
                __('Slutt %1$s er ikke etter start %2$s. Velg et senere sluttklokkeslett samme dag.', 'reginor-lite'), $endTime, $startTime));
        }
        foreach (array_merge($periodBreaks, $groupBreaks) as $break) {
            if (!$break instanceof BreakPeriod) {
                throw new InvalidArgumentException(__('Alle opphold må ha gyldig fra- og til-dato.', 'reginor-lite'));
            }
        }
        if ($latestDate !== null) {
            LocalDateTime::date($latestDate);
        }
        if ($firstDate !== null && (int) LocalDateTime::date($firstDate)->format('N') !== $weekday) {
            throw new InvalidArgumentException(__('Første kursdato må samsvare med valgt ukedag.', 'reginor-lite'));
        }
    }
}
