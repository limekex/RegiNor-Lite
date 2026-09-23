<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain\Capacity;

use DateTimeImmutable;
use RegiNor\Lite\Domain\Publication\LetsRegAvailability;

/** Per-category observations, never a sum or a claim about independent capacity pools. */
final class LetsRegCategoryCapacity
{
    /** One pair offer, measured in complete pairs; alternatives are never summed. */
    public static function displayRows(array $rows): array
    {
        $singles = array_values(array_filter($rows, static fn ($row) => $row['registration'] !== 'pair'));
        $pairs = array_filter($rows, static fn ($row) => $row['registration'] === 'pair');
        if (!$pairs) { return $singles; }
        $limits = [];
        foreach (['leader', 'follower'] as $role) {
            $side = array_filter($pairs, static fn ($row) => $row['role'] === $role);
            if (!$side || array_filter($side, static fn ($row) => $row['available'] === null && $row['status'] !== 'unlimited')) { $limits[] = null; continue; }
            $limits[] = max(array_map(static fn ($row) => $row['status'] === 'unlimited' ? PHP_INT_MAX : $row['available'], $side));
        }
        $count = in_array(null, $limits, true) ? null : min($limits);
        $status = $count === null ? 'unknown' : ($count === 0 ? 'full' : ($count === PHP_INT_MAX ? 'unlimited' : 'available'));
        $statuses = array_unique(array_column($pairs, 'status'));
        if (count($statuses) === 1 && in_array(reset($statuses), ['closed', 'cancelled', 'later'], true)) { $status = reset($statuses); }
        $singles[] = ['name' => '', 'role' => 'pair', 'registration' => 'pair', 'available' => $count === PHP_INT_MAX ? null : $count,
            'status' => $status, 'reported_registered' => null, 'reported_available' => null];
        return $singles;
    }
    public static function project(?array $observation, array $mapping, string $timezone, DateTimeImmutable $now): array
    {
        $event = LetsRegAvailability::project($observation, $mapping, $timezone, $now);
        $rows = [];
        foreach ($mapping['categories'] as $choice) {
            $source = $observation['categories'][$choice['id']] ?? null;
            $row = ['name' => $choice['name'] ?? '', 'role' => $choice['role'], 'registration' => $choice['registration'],
                'reported_registered' => null, 'reported_available' => null, 'available' => null, 'status' => 'unknown'];
            if ($observation === null || !($observation['valid'] ?? false) || !$source) { $rows[] = $row; continue; }
            $row['reported_available'] = $source['available'];
            $row['reported_registered'] = $source['registered'] ?? null;
            $bounds = LetsRegAvailability::bounds($source['window'], $timezone);
            if (in_array($event['status'], ['cancelled', 'closed', 'later'], true)) { $row['status'] = $event['status']; }
            elseif (!$source['active']) { $row['status'] = 'closed'; }
            elseif ($bounds === null || $event['status'] === 'unknown') { $row['status'] = 'unknown'; }
            elseif ($bounds[1] && $now >= $bounds[1]) { $row['status'] = 'closed'; }
            elseif ($bounds[0] && $now < $bounds[0]) { $row['status'] = 'later'; }
            else {
                $total = LetsRegAvailability::unlimited($observation) ? PHP_INT_MAX : LetsRegAvailability::remaining($observation);
                $count = LetsRegAvailability::categoryUnlimited($source) ? PHP_INT_MAX : LetsRegAvailability::categoryRemaining($source);
                // Unknown category availability cannot inherit the event's inventory.
                $row['available'] = $total === 0 || $count === 0 ? 0 : ($total !== null && $count !== null ? min($total, $count) : null);
                $row['status'] = $row['available'] === null ? 'unknown' : ($row['available'] === 0 ? 'full' : 'available');
                if ($row['available'] === PHP_INT_MAX) { $row['available'] = null; $row['status'] = 'unlimited'; }
            }
            $rows[] = $row;
        }
        // Pair quantities describe participant places in each category, never a count of pairs.
        $base = $rows;
        foreach ($rows as &$row) {
            if ($row['registration'] !== 'pair' || !in_array($row['status'], ['available', 'unlimited'], true)) { continue; }
            $role = match ($row['role']) { 'leader' => 'follower', 'follower' => 'leader', default => null };
            $partners = array_filter($base, static fn ($other) => $other['registration'] === 'pair' && $other['role'] === $role);
            $limits = array_map(static fn ($other) => $other['status'] === 'unlimited' ? PHP_INT_MAX : $other['available'], array_filter($partners, static fn ($other) => $other['available'] !== null || $other['status'] === 'unlimited'));
            // Several categories can share a pool; choose an upper bound, never sum them.
            if (!$role || !$partners || count($limits) !== count($partners)) { $row['available'] = null; $row['status'] = 'unknown'; continue; }
            $row['available'] = min($row['status'] === 'unlimited' ? PHP_INT_MAX : $row['available'], max($limits));
            if (!LetsRegAvailability::unlimited($observation)) {
                $row['available'] = min($row['available'], intdiv(LetsRegAvailability::remaining($observation), 2));
            }
            $row['status'] = $row['available'] === 0 ? 'full' : 'available';
            if ($row['available'] === PHP_INT_MAX) { $row['available'] = null; $row['status'] = 'unlimited'; }
        }
        unset($row);
        return $rows;
    }
}
