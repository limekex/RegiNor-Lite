<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RegiNor\Lite\Domain\LocalDateTime;

/** Inclusive local dates; persistence adds identity, scope and public explanation later. */
final readonly class BreakPeriod
{
    public function __construct(public string $from, public string $until)
    {
        LocalDateTime::date($from);
        LocalDateTime::date($until);
        if ($until < $from) {
            throw new InvalidArgumentException(__('Oppholdets sluttdato må være på eller etter startdatoen.', 'reginor-lite'));
        }
    }

    public function includes(string $date): bool
    {
        return $this->from <= $date && $date <= $this->until;
    }
}
