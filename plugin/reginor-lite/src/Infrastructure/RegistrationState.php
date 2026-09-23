<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use DateTimeImmutable;
use RegiNor\Lite\Domain\Publication\SalesStatus;
use RegiNor\Lite\Domain\Publication\SalesWindow;
use RegiNor\Lite\Domain\Capacity\CapacityView;
use function RegiNor\Lite\translate as __;

/** Shared by the editor and public views. Automatic courses follow provider dates; manual courses inherit period dates. */
final class RegistrationState
{
    public static function resolve(array $period, array $course, DateTimeImmutable $now, bool $visible = true): array
    {
        [$from, $until] = SalesWindow::bounds($period, $course);
        $automatic = $course['registration_status'] === 'automatic';
        $api = $automatic ? LetsRegAvailabilityStore::view($course, $now) : null;
        $capacity = CapacityView::project(null, $now->getTimestamp());
        $boundaries = array_values(array_filter([$from, $until]));
        $editorial = $course['registration_status'];
        if ($api) {
            $from = $api['from']; $until = $api['until'];
            // Period dates are defaults for local courses, not provider overrides.
            if (!empty($course['registration_from'])) { $local = new DateTimeImmutable($course['registration_from']); $from = $from ? max($from, $local) : $local; }
            if (!empty($course['registration_until'])) { $local = new DateTimeImmutable($course['registration_until']); $until = $until ? min($until, $local) : $local; }
            $editorial = $api['status'];
            $boundaries = array_merge(array_values(array_filter([$from, $until])), $api['boundaries']);
            // Status and last known counts survive refresh deadlines and API outages.
            if ($api['capacity_known']) {
                $capacity = ['state' => $editorial === 'full' ? 'full' : 'fresh', 'label' => __('Påmelding tilgjengelig hos LetsReg', 'reginor-lite'),
                    'confirmed' => true, 'expires_at' => $api['expires_at'], 'roles' => []];
            }
            if ($api['checked_at'] && $editorial === 'available') { $capacity['label'] = __('Påmelding tilgjengelig hos LetsReg', 'reginor-lite'); }
        }
        if ($course['registration_status'] === 'dropin') { $from = $until = null; $boundaries = []; }
        if ($automatic) {
            $status = $editorial;
            // Missing provider data must never be disguised as a known local opening date.
            if (!in_array($status, ['unknown', 'cancelled'], true)) {
                if (($from && $until && $until <= $from) || ($until && $now >= $until)) { $status = 'closed'; }
                elseif ($from && $now < $from) { $status = 'later'; }
            }
            if ($period['cancelled']) { $status = 'cancelled'; }
            if (!$visible) { $status = 'hidden'; }
        } else {
            $status = (new SalesStatus())->effective($visible, $period['cancelled'], $editorial, $from, $until, $now);
        }
        $ends = array_column(array_filter($course['sessions'], static fn ($s) => $s['status'] !== 'cancelled'), 'ends_at');
        $last = $ends ? new DateTimeImmutable(max($ends)) : null;
        if ($status !== 'hidden' && $status !== 'cancelled' && $last && $last <= $now) { $status = 'ended'; }
        if ($last) { $boundaries[] = $last; }
        if ($api && in_array($status, ['available', 'full', 'waiting'], true) && $api['checked_at']) {
            // Automatic mode retains last known quantities with their real observation time.
            $capacity['checked_at'] = $api['checked_at']; $capacity['expires_at'] = $api['expires_at'];
            $capacity['categories'] = array_map(static function ($row) { unset($row['reported_available'], $row['reported_registered']); return $row; }, $api['categories']);
        }
        return ['status' => $status, 'capacity' => $capacity, 'from' => $from?->format(DATE_ATOM), 'until' => $until?->format(DATE_ATOM), 'boundaries' => $boundaries];
    }
}
