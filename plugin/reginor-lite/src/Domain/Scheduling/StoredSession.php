<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Scheduling;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RegiNor\Lite\Domain\LocalDateTime;

final class StoredSession
{
    public static function create(string $id, ScheduledTime $time, int $roomId, array $instructors): array
    {
        return [
            'id' => $id, 'original_date' => $time->startsAt->format('Y-m-d'),
            'date' => $time->startsAt->format('Y-m-d'),
            'start_time' => $time->startsAt->format('H:i'), 'end_time' => $time->endsAt->format('H:i'),
            'timezone' => $time->startsAt->getTimezone()->getName(),
            'starts_at' => $time->startsAtUtc()->format('Y-m-d\TH:i:s\Z'),
            'ends_at' => $time->endsAtUtc()->format('Y-m-d\TH:i:s\Z'),
            'status' => 'scheduled', 'reason' => '', 'room_id' => $roomId, 'instructor_ids' => $instructors,
        ];
    }

    public static function time(array $session): ScheduledTime
    {
        LocalDateTime::date($session['original_date']);
        if ($session['status'] === 'scheduled' && $session['original_date'] !== $session['date']) {
            throw new InvalidArgumentException(__('En flyttet dato må ha flyttestatus og forklaring.', 'reginor-lite'));
        }
        $time = new ScheduledTime(
            LocalDateTime::at($session['date'], $session['start_time'], $session['timezone']),
            LocalDateTime::at($session['date'], $session['end_time'], $session['timezone']),
        );
        if ($time->startsAtUtc()->format('Y-m-d\TH:i:s\Z') !== $session['starts_at']
            || $time->endsAtUtc()->format('Y-m-d\TH:i:s\Z') !== $session['ends_at']) {
            throw new InvalidArgumentException(__('Øktens UTC-tider stemmer ikke med lokal dato og klokkeslett.', 'reginor-lite'));
        }
        if ($session['status'] !== 'scheduled' && trim($session['reason']) === '') {
            throw new InvalidArgumentException(__('Flytting og avlysning krever en forklaring.', 'reginor-lite'));
        }
        return $time;
    }
}
