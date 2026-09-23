<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** An unpersisted preview interval, not a stored session identity. */
final readonly class ScheduledTime
{
    public function __construct(public DateTimeImmutable $startsAt, public DateTimeImmutable $endsAt)
    {
        if ($endsAt <= $startsAt) {
            throw new InvalidArgumentException(__('Kursøkten må slutte etter at den starter.', 'reginor-lite'));
        }
    }

    public function startsAtUtc(): DateTimeImmutable
    {
        return $this->startsAt->setTimezone(new DateTimeZone('UTC'));
    }

    public function endsAtUtc(): DateTimeImmutable
    {
        return $this->endsAt->setTimezone(new DateTimeZone('UTC'));
    }
}
