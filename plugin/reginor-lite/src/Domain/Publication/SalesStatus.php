<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Publication;

use DateTimeImmutable;

final class SalesStatus
{
    /** Visibility is a separate prerequisite; this never opens a hidden course. */
    public function effective(bool $visible, bool $cancelled, string $editorial, ?DateTimeImmutable $from, ?DateTimeImmutable $until, DateTimeImmutable $now): string
    {
        if (!$visible) { return 'hidden'; }
        if ($cancelled || $editorial === 'cancelled') { return 'cancelled'; }
        if ($editorial === 'dropin') { return 'dropin'; }
        if ($from === null || $until === null || $until <= $from) { return 'closed'; }
        if ($now < $from) { return 'later'; }
        if ($now >= $until) { return 'closed'; }
        return in_array($editorial, ['external', 'available', 'full', 'waiting', 'closed', 'later'], true) ? $editorial : 'unknown';
    }
}
