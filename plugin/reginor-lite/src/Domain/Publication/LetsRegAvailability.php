<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Publication;

use DateTimeImmutable;
use DateTimeZone;

/** Minimal provider observation and conservative status; never sum category inventories. */
final class LetsRegAvailability
{
    public static function observation(array $event, array $prices): array
    {
        $result = array_intersect_key($event, array_flip(['active', 'published', 'isCancelled', 'isArchived', 'hasWaitinglist']));
        $result['valid'] = is_bool($event['isArchived'] ?? null);
        $result['available'] = self::count($event['availableRegistrations'] ?? null);
        $result['limit'] = self::count($event['maxAllowedRegistrations'] ?? null);
        $result['registered'] = self::count($event['registeredParticipants'] ?? null);
        $result['window'] = self::window($event, 'registrationStartDate', 'registrationEndDate');
        $result['categories'] = [];
        foreach ($prices as $price) {
            $result['categories'][$price['id']] = ['active' => $price['active'], 'available' => self::count($price['available'] ?? null), 'registered' => self::count($price['registered'] ?? null),
                'window' => self::window($price, 'availableFrom', 'availableTill')];
        }
        return $result;
    }

    private static function count(mixed $value): ?int { return is_int($value) && $value >= 0 && $value <= 2147483647 ? $value : null; }
    public static function remaining(array $observation): ?int
    {
        $limit = self::count($observation['limit'] ?? null);
        $registered = self::count($observation['registered'] ?? null);
        // A zero place limit means unlimited, not sold out (including 0 registered).
        if ($limit === 0) { return null; }
        if ($limit !== null && $registered !== null) { return max(0, $limit - $registered); }
        $count = self::count($observation['available'] ?? null);
        // Legacy/incomplete observations cannot prove full from an ambiguous zero.
        return $count === 0 ? null : $count;
    }
    public static function unlimited(array $observation): bool { return self::count($observation['limit'] ?? null) === 0; }
    public static function categoryUnlimited(array $category): bool { return self::count($category['available'] ?? null) === 0; }
    public static function categoryRemaining(array $category): ?int
    {
        $count = self::count($category['available'] ?? null);
        // Provider zero means no category limit, as confirmed by the project owner.
        return $count === 0 ? null : $count;
    }
    private static function window(array $data, string $from, string $until): array
    {
        $window = [];
        foreach ([$from, $until] as $key) {
            $value = $data[$key] ?? null;
            if (!array_key_exists($key, $data)) { return ['invalid']; }
            if ($value !== null && (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,7})?(?:Z|[+-]\d{2}:\d{2})?$/D', $value))) { return ['invalid']; }
            $window[] = $value;
        }
        return $window;
    }
    public static function bounds(array $window, string $timezone): ?array
    {
        if (count($window) !== 2) { return null; }
        try {
            $dates = array_map(static function ($value) use ($timezone) {
                if ($value === null) { return null; }
                $date = new DateTimeImmutable($value, new DateTimeZone($timezone));
                $errors = DateTimeImmutable::getLastErrors();
                if ($errors && ($errors['warning_count'] || $errors['error_count'])) { throw new \InvalidArgumentException(); }
                return $date;
            }, $window);
            if ($dates[0] && $dates[1] && $dates[0] >= $dates[1]) { return null; }
            return $dates;
        } catch (\Throwable) { return null; }
    }

    public static function project(?array $observation, array $mapping, string $timezone, DateTimeImmutable $now): array
    {
        $out = ['status' => 'unknown', 'capacity_known' => false, 'from' => null, 'until' => null, 'boundaries' => []];
        if ($observation === null || !($observation['valid'] ?? false)) { return $out; }
        if (($observation['isCancelled'] ?? false) === true) { return array_replace($out, ['status' => 'cancelled']); }
        if (($observation['active'] ?? false) !== true || ($observation['published'] ?? false) !== true || ($observation['isArchived'] ?? false) === true) { return array_replace($out, ['status' => 'closed']); }
        $window = self::bounds($observation['window'], $timezone);
        if ($window === null) { return $out; }
        [$out['from'], $out['until']] = $window;
        $out['boundaries'] = array_values(array_filter($window));
        if ($out['from'] && $now < $out['from']) { return array_replace($out, ['status' => 'later']); }
        if ($out['until'] && $now >= $out['until']) { return array_replace($out, ['status' => 'closed']); }
        $open = []; $future = false; $uncertain = false;
        foreach ($mapping['categories'] as $choice) {
            $category = $observation['categories'][$choice['id']] ?? null;
            if (!$category) { $uncertain = true; continue; }
            if (!$category['active']) { continue; }
            $bounds = self::bounds($category['window'], $timezone);
            if ($bounds === null) { $uncertain = true; continue; }
            [$from, $until] = $bounds;
            foreach ($bounds as $date) { if ($date) { $out['boundaries'][] = $date; } }
            if ($until && $now >= $until) { continue; }
            if ($from && $now < $from) { $future = true; continue; }
            $open[] = $choice + ['available' => self::categoryRemaining($category), 'unlimited' => self::categoryUnlimited($category)];
        }
        if (!$open) { return array_replace($out, ['status' => $uncertain ? 'unknown' : ($future ? 'later' : 'closed')]); }
        $remaining = self::remaining($observation);
        $full = ['status' => ($observation['hasWaitinglist'] ?? false) === true ? 'waiting' : 'full', 'capacity_known' => true];
        if ($remaining === 0) { return array_replace($out, $full); }
        $possible = false; $knownOpen = false;
        foreach ($open as $choice) {
            if ($choice['available'] === 0) { continue; }
            if ($choice['registration'] === 'single') {
                $possible = true; $knownOpen = $knownOpen || (($remaining !== null || self::unlimited($observation)) && ($choice['available'] !== null || $choice['unlimited'])); continue;
            }
            // A pair consumes two participants and requires a mapped offer for the partner.
            if ($remaining === 1) { continue; }
            if (!in_array($choice['role'], ['leader', 'follower'], true)) { $uncertain = true; continue; }
            $uncertain = true; // Missing or unavailable partner categories never prove a shared pool is full.
            $otherRole = $choice['role'] === 'leader' ? 'follower' : 'leader';
            foreach ($open as $partner) {
                if ($partner['registration'] !== 'pair' || $partner['role'] !== $otherRole || $partner['available'] === 0) { continue; }
                $possible = true;
                // Shared pool semantics are not proven by two positive category counts.
                $uncertain = true;
            }
        }
        if (!$possible && !$uncertain) { return array_replace($out, $full); }
        return array_replace($out, ['status' => 'available', 'capacity_known' => $knownOpen]);
    }
}
