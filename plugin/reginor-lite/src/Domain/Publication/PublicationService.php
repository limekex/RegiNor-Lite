<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Publication;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use InvalidArgumentException;

final class PublicationService
{
    public function __construct(private readonly Clock $clock = new SystemClock())
    {
    }

    public function isPublic(Period $period): bool
    {
        return $this->isPublicAt($period, $this->clock->now());
    }

    public function isGroupPublic(Period $period, string $groupStatus): bool
    {
        return $groupStatus === 'publish' && $this->isPublic($period);
    }

    /** Ended and cancelled periods can remain directly readable, but are not choices.
     * @param list<Period> $periods
     * @return array{current: list<Period>, upcoming: list<Period>, default: ?Period}
     */
    public function choices(array $periods): array
    {
        $now = $this->clock->now();
        $current = [];
        $upcoming = [];
        $seen = [];
        foreach ($periods as $period) {
            if (!$period instanceof Period || isset($seen[$period->id])) {
                throw new InvalidArgumentException(__('Periodevelgeren trenger gyldige perioder med unike ID-er.', 'reginor-lite'));
            }
            $seen[$period->id] = true;
            if (!$this->isPublicAt($period, $now) || $period->cancelled
                || $period->firstSessionStartsAt === null || $period->lastSessionEndsAt === null
                || $period->firstSessionStartsAt >= $period->lastSessionEndsAt) {
                continue;
            }
            if ($period->firstSessionStartsAt <= $now && $now < $period->lastSessionEndsAt) {
                $current[] = $period;
            } elseif ($period->firstSessionStartsAt > $now && $period->showAsUpcoming) {
                $upcoming[] = $period;
            }
        }
        usort($current, static fn (Period $a, Period $b): int =>
            ($b->firstSessionStartsAt <=> $a->firstSessionStartsAt) ?: strcmp($a->id, $b->id));
        usort($upcoming, static fn (Period $a, Period $b): int =>
            ($a->firstSessionStartsAt <=> $b->firstSessionStartsAt) ?: strcmp($a->id, $b->id));
        return ['current' => $current, 'upcoming' => $upcoming, 'default' => $current[0] ?? $upcoming[0] ?? null];
    }

    private function isPublicAt(Period $period, DateTimeImmutable $now): bool
    {
        return $period->status === 'publish' && $period->hasPublishedGroup
            && $period->visibleFrom !== null && $period->visibleUntil !== null
            && $period->visibleFrom < $period->visibleUntil
            && $period->visibleFrom <= $now && $now < $period->visibleUntil;
    }
}
