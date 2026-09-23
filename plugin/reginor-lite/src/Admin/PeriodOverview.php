<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Publication\SystemClock;

/** Administrative dates describe teaching, independently of sales and visibility windows. */
final class PeriodOverview
{
    public function __construct(private readonly CourseRepository $repo = new CourseRepository(), private readonly Clock $clock = new SystemClock()) {}

    public function rows(): array
    {
        $rows = []; $nextStart = null;
        foreach ($this->repo->listing('period') as $id => $state) {
            $p = $state['data']; $dates = $rooms = [];
            foreach ($this->repo->groups($id) as $group) {
                $g = $group['data'];
                if ($g['registration_status'] === 'cancelled') { continue; }
                if ($g['room_id']) { $rooms[] = $g['room_id']; }
                foreach ($g['sessions'] as $s) {
                    if ($s['status'] === 'cancelled') { continue; }
                    $dates[] = $s['date']; if ($s['room_id']) { $rooms[] = $s['room_id']; }
                }
            }
            if (!$rooms && $p['default_room_id']) { $rooms[] = $p['default_room_id']; }
            $places = [];
            foreach (array_unique($rooms) as $roomId) {
                $room = $this->repo->resource($roomId, 'room');
                $places[] = $this->repo->resource($room['venue_id'], 'venue')['title'];
            }
            $timezone = new \DateTimeZone($p['timezone']);
            $today = $this->clock->now()->setTimezone($timezone)->setTime(0, 0);
            $from = $dates ? min($dates) : $p['start_date']; $until = $dates ? max($dates) : null;
            $first = new \DateTimeImmutable($from, $timezone);
            $last = $until ? new \DateTimeImmutable($until, $timezone) : null;
            $published = get_post_status($id) === 'publish';
            $active = !$p['cancelled'] && $published && $first <= $today && $last && $last >= $today;
            $upcoming = !$p['cancelled'] && $first > $today;
            if ($upcoming && ($nextStart === null || $first < $nextStart)) { $nextStart = $first; }
            $rows[$id] = ['id' => $id, 'title' => $p['title'], 'places' => array_values(array_unique($places)), 'from' => $from, 'until' => $until,
                'published' => $published, 'cancelled' => $p['cancelled'], 'active' => (bool) $active, 'upcoming' => $upcoming, 'next' => false,
                'days' => $active ? (int) $today->diff($last)->format('%a') : ($upcoming ? (int) $today->diff($first)->format('%a') : null),
                'start_stamp' => $first->getTimestamp()];
        }
        foreach ($rows as &$row) { $row['next'] = $row['upcoming'] && $row['start_stamp'] === $nextStart?->getTimestamp(); } unset($row);
        krsort($rows, SORT_NUMERIC);
        return $rows;
    }
}
