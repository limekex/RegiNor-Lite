<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;

/** Actual session resources; IDs are normalized opaque strings, with globally unique room IDs. */
final readonly class SessionAllocation
{
    /** @param list<string> $instructorIds */
    public function __construct(
        public string $id,
        public ScheduledTime $time,
        public ?string $roomId,
        public array $instructorIds,
        public bool $cancelled = false,
    ) {
        if (trim($id) === '' || ($roomId !== null && trim($roomId) === '')) {
            throw new InvalidArgumentException(__('Økt og eventuell sal må ha en entydig ID.', 'reginor-lite'));
        }
        foreach ($instructorIds as $instructorId) {
            if (!is_string($instructorId) || trim($instructorId) === '') {
                throw new InvalidArgumentException(__('Instruktører må ha entydige ID-er.', 'reginor-lite'));
            }
        }
    }
}
