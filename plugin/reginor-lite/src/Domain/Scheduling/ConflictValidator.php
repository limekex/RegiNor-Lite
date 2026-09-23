<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;

final class ConflictValidator
{
    /** Caller supplies actual sessions from ALL relevant active periods.
     * @param list<SessionAllocation> $sessions
     * @return list<array{first: string, second: string, room: ?string, instructors: list<string>}>
     */
    public function find(array $sessions): array
    {
        $seen = [];
        foreach ($sessions as $session) {
            if (!$session instanceof SessionAllocation || isset($seen[$session->id])) {
                throw new InvalidArgumentException(__('Kollisjonskontrollen trenger gyldige økter med unike ID-er.', 'reginor-lite'));
            }
            $seen[$session->id] = true;
        }
        $sessions = array_values($sessions);
        $conflicts = [];
        for ($i = 0; $i < count($sessions); ++$i) {
            $a = $sessions[$i];
            if ($a->cancelled) {
                continue;
            }
            for ($j = $i + 1; $j < count($sessions); ++$j) {
                $b = $sessions[$j];
                if ($b->cancelled || $a->time->startsAt >= $b->time->endsAt || $b->time->startsAt >= $a->time->endsAt) {
                    continue;
                }
                $room = $a->roomId !== null && $a->roomId === $b->roomId ? $a->roomId : null;
                $instructors = array_values(array_unique(array_intersect($a->instructorIds, $b->instructorIds)));
                if ($room !== null || $instructors !== []) {
                    $conflicts[] = ['first' => $a->id, 'second' => $b->id, 'room' => $room, 'instructors' => $instructors];
                }
            }
        }
        return $conflicts;
    }
}
