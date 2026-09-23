<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Publication;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use InvalidArgumentException;

/** Read model. Bounds and group presence must be derived from public groups' actual sessions. */
final readonly class Period
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $visibleFrom,
        public ?DateTimeImmutable $visibleUntil,
        public ?DateTimeImmutable $firstSessionStartsAt,
        public ?DateTimeImmutable $lastSessionEndsAt,
        public bool $hasPublishedGroup,
        public bool $showAsUpcoming = false,
        public bool $cancelled = false,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException(__('Kursperioden må ha en entydig ID.', 'reginor-lite'));
        }
    }
}
