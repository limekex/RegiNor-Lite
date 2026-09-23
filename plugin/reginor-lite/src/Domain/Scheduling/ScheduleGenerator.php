<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RegiNor\Lite\Domain\LocalDateTime;

final class ScheduleGenerator
{
    public function __construct(private readonly int $maxWeeks = 104)
    {
        if ($maxWeeks < 1 || $maxWeeks > 520) {
            throw new InvalidArgumentException(__('Søkehorisonten må være mellom 1 og 520 uker.', 'reginor-lite'));
        }
    }

    /** New schedule preview only; never regenerates or replaces persisted sessions.
     * @return list<ScheduledTime>
     */
    public function preview(ScheduleRequest $request): array
    {
        $date = LocalDateTime::date($request->firstDate ?? $request->startDate);
        if ($request->firstDate === null) {
            $offset = ($request->weekday - (int) $date->format('N') + 7) % 7;
            $date = $date->modify('+' . $offset . ' days');
        }
        $breaks = array_merge($request->periodBreaks, $request->groupBreaks);
        $result = [];
        for ($week = 0; $week < $this->maxWeeks; ++$week, $date = $date->modify('+7 days')) {
            $localDate = $date->format('Y-m-d');
            if ($request->latestDate !== null && $localDate > $request->latestDate) {
                throw new InvalidArgumentException(sprintf(
                    /* translators: 1: ønsket antall kvelder, 2: antall som får plass, 3: siste tillatte dato, 4: neste ukedag i beregningen. */
                __('Du har valgt %1$d undervisningskvelder, men bare %2$d får plass før siste tillatte kursdato %3$s når kursfrie dager er trukket fra. Neste ukedag i beregningen er %4$s. Reduser antallet, velg tidligere oppstart eller utvid sluttdatoen i kursoppsettet eller kursperioden.', 'reginor-lite'),
                    $request->sessionCount, count($result), $request->latestDate, $localDate
                ));
            }
            $excluded = false;
            foreach ($breaks as $break) {
                $excluded = $excluded || $break->includes($localDate);
            }
            if ($excluded) {
                if ($week === 0 && $request->firstDate !== null) {
                    throw new InvalidArgumentException(sprintf(/* translators: Den valgte første kursdatoen, ÅÅÅÅ-MM-DD. */
                __('Første kursdato %s er satt som kursfri i kurset eller kursperioden. Velg en annen første dato, eller fjern den kursfrie dagen hvis det skal være undervisning.', 'reginor-lite'), $localDate));
                }
                continue;
            }
            $result[] = new ScheduledTime(
                LocalDateTime::at($localDate, $request->startTime, $request->timezone),
                LocalDateTime::at($localDate, $request->endTime, $request->timezone),
            );
            if (count($result) === $request->sessionCount) {
                return $result;
            }
        }
        throw new InvalidArgumentException(__('Alle kurskveldene kan ikke beregnes innenfor søkehorisonten. Kontroller antall og opphold.', 'reginor-lite'));
    }
}
