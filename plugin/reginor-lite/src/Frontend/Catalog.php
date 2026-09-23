<?php

declare(strict_types=1);

namespace RegiNor\Lite\Frontend;

use DateTimeImmutable;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Publication\SystemClock;
use RegiNor\Lite\Domain\Publication\Period;
use RegiNor\Lite\Domain\Publication\PublicationService;
use RegiNor\Lite\Domain\Publication\SalesStatus;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\StateSchema;

/** Public projection only. Never returns aggregate metadata, versions, actors or history. */
final class Catalog
{
    public function __construct(private readonly Clock $clock = new SystemClock()) {}

    private function data(int $id, string $kind): ?array
    {
        if (get_post_field('post_password', $id) !== '' || get_post_type($id) !== ContentTypes::TYPES[$kind] || !in_array(get_post_status($id), ['draft', 'publish'], true)) { return null; }
        $rows = get_post_meta($id, ContentTypes::META, false);
        if (count($rows) !== 1 || !is_array($rows[0]) || !isset($rows[0]['data'])) { return null; }
        try { return \RegiNor\Lite\Infrastructure\Wpml::data($id, $kind, StateSchema::validate($kind, $rows[0]['data'])); } catch (\Throwable) { return null; }
    }

    public function read(): array
    {
        $now = $this->clock->now(); $periods = []; $groups = []; $models = []; $registrationCourses = [];
        foreach (get_posts(['post_type' => 'rnl_period', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            $data = $this->data($id, 'period');
            if ($data) { $periods[$id] = $data; }
        }
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            $g = $this->data($id, 'group');
            if (!$g || !isset($periods[$g['period_id']])) { continue; }
            $course = $this->data($g['course_id'], 'course'); $room = $this->data($g['room_id'], 'room');
            $venue = $room ? $this->data($room['venue_id'], 'venue') : null;
            if (!$course || !$room || !$venue || !$g['sessions']) { continue; }
            $level = !empty($g['level_id']) ? $this->data($g['level_id'], 'level') : null;
            $sessions = []; $valid = true;
            foreach ($g['sessions'] as $s) {
                $sessionRoom = $this->data($s['room_id'], 'room'); $sessionVenue = $sessionRoom ? $this->data($sessionRoom['venue_id'], 'venue') : null;
                if (!$sessionRoom || !$sessionVenue) { $valid = false; break; }
                $teachers = [];
                foreach ($s['instructor_ids'] as $teacherId) {
                    if (get_post_field('post_password', $teacherId) !== '' || get_post_status($teacherId) !== 'publish' || !in_array(get_post_type($teacherId), (array) apply_filters('rnl_instructor_post_types', get_option('rnl_instructor_types', [])), true)) { $valid = false; break; }
                    $translatedId = (int) apply_filters('wpml_object_id', $teacherId, get_post_type($teacherId), true);
                    if (get_post_type($translatedId) !== get_post_type($teacherId) || get_post_status($translatedId) !== 'publish' || get_post_field('post_password', $translatedId) !== '') { $translatedId = $teacherId; }
                    $teachers[] = sanitize_text_field(get_post_field('post_title', $translatedId));
                }
                $sessions[] = ['id' => $s['id'], 'latitude' => $sessionVenue['latitude'] ?? null, 'longitude' => $sessionVenue['longitude'] ?? null, 'date' => $s['date'], 'original_date' => $s['original_date'], 'start_time' => $s['start_time'], 'end_time' => $s['end_time'],
                    'starts_at' => $s['starts_at'], 'ends_at' => $s['ends_at'], 'timezone' => $s['timezone'], 'status' => $s['status'], 'reason' => $s['reason'],
                    'room' => $sessionRoom['title'], 'venue' => $sessionVenue['title'], 'address' => $sessionVenue['address'], 'instructors' => $teachers];
            }
            if (!$valid) { continue; }
            usort($sessions, static fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
            $active = array_values(array_filter($sessions, static fn ($s) => $s['status'] !== 'cancelled'));
            $url = $g['registration_url'];
            if ($url && (($g['registration_status'] !== 'external' && !in_array(strtolower((string) wp_parse_url($url, PHP_URL_HOST)), \RegiNor\Lite\Infrastructure\RegistrationDomains::allowed(), true))
                || (wp_parse_url($url, PHP_URL_PORT) !== null && wp_parse_url($url, PHP_URL_PORT) !== 443))) { $url = ''; }
            $periodStart = new DateTimeImmutable($periods[$g['period_id']]['start_date'], new \DateTimeZone($g['timezone']));
            $expectedFirst = $periodStart->modify('+' . (($g['weekday'] - (int) $periodStart->format('N') + 7) % 7) . ' days')->format('Y-m-d');
            $registrationCourses[$id] = $g;
            $groups[$id] = ['id' => $id, 'period_id' => $g['period_id'], 'title' => $g['title'], 'description' => $course['description'], 'level' => $course['level_description'],
                'level_id' => $level ? $g['level_id'] : 0, 'level_name' => $level['title'] ?? '',
                'level_help' => $level['description'] ?? '', 'level_order' => $level['sort_order'] ?? 0,
                'dance_style' => $course['dance_style'], 'partner_info' => $course['partner_info'],
                'weekday' => $g['weekday'], 'start_time' => $g['start_time'], 'end_time' => $g['end_time'], 'timezone' => $g['timezone'],
                'latitude' => $venue['latitude'] ?? null, 'longitude' => $venue['longitude'] ?? null,
                'room_id' => $g['room_id'], 'room' => $room['title'], 'venue' => $venue['title'], 'address' => $venue['address'],
                'instructors' => array_values(array_unique(array_merge(...array_column($sessions, 'instructors')))),
                'dropin_only' => $g['registration_status'] === 'dropin', 'dropin_enabled' => $g['dropin_enabled'] ?? false, 'dropin_price_minor' => $g['dropin_price_minor'] ?? null,
                'price_from' => $g['price_from'] ?? false, 'price_minor' => $g['price_minor'], 'price_basis' => $g['price_basis'], 'price_terms' => $g['price_terms'],
                'registration_from' => $g['registration_from'] ?? null, 'registration_until' => $g['registration_until'] ?? null,
                'registration_url' => $url, 'registration_scope' => $g['registration_scope'], 'editorial_status' => $g['registration_status'],
                'sessions' => $sessions, 'first' => $active ? min(array_column($active, 'starts_at')) : null, 'last' => $active ? max(array_column($active, 'ends_at')) : null,
                'delayed_start' => $active && min(array_column($active, 'date')) > $expectedFirst,
                'count' => count($active), 'breaks' => array_map(static fn ($b) => array_intersect_key($b, array_flip(['from', 'until', 'reason'])), array_merge($periods[$g['period_id']]['breaks'], $g['breaks'])),
                'changed' => (bool) array_filter($sessions, static fn ($s) => $s['status'] !== 'scheduled')];
        }
        foreach ($periods as $id => $p) {
            $members = array_filter($groups, static fn ($g) => $g['period_id'] === $id);
            $active = array_filter($members, static fn ($g) => $g['editorial_status'] !== 'cancelled' && $g['first'] && $g['last']);
            $models[$id] = new Period((string) $id, 'publish', $p['visible_from'] ? new DateTimeImmutable($p['visible_from']) : null,
                $p['visible_until'] ? new DateTimeImmutable($p['visible_until']) : null,
                $active ? new DateTimeImmutable(min(array_column($active, 'first'))) : null,
                $active ? new DateTimeImmutable(max(array_column($active, 'last'))) : null, (bool) $members, $p['show_as_upcoming'], $p['cancelled']);
        }
        $policy = new PublicationService($this->clock); $publicPeriods = []; $expiry = null;
        foreach ($models as $id => $model) {
            if (!$policy->isPublic($model)) { unset($models[$id]); continue; }
            $p = $periods[$id];
            $publicPeriods[$id] = ['id' => $id, 'title' => $p['title'], 'timezone' => $p['timezone'], 'cancelled' => $p['cancelled'],
                'default_view' => $p['default_view'] ?? 'list', 'first' => $model->firstSessionStartsAt?->format(DATE_ATOM), 'last' => $model->lastSessionEndsAt?->format(DATE_ATOM),
                'visible_until' => $p['visible_until'], 'sales_from' => $p['sales_from'], 'sales_until' => $p['sales_until']];
            foreach ([$p['visible_until'], $p['sales_from'], $p['sales_until'], $publicPeriods[$id]['first'], $publicPeriods[$id]['last']] as $boundary) {
                if ($boundary && new DateTimeImmutable($boundary) > $now) { $stamp = (new DateTimeImmutable($boundary))->getTimestamp(); $expiry = $expiry === null ? $stamp : min($expiry, $stamp); }
            }
        }
        foreach ($groups as $id => &$g) {
            if (!isset($publicPeriods[$g['period_id']])) { unset($groups[$id]); continue; }
            $p = $publicPeriods[$g['period_id']];
            $registration = \RegiNor\Lite\Infrastructure\RegistrationState::resolve($p, $registrationCourses[$id], $now);
            foreach ($registration['boundaries'] as $boundary) {
                if ($boundary > $now) { $expiry = $expiry === null ? $boundary->getTimestamp() : min($expiry, $boundary->getTimestamp()); }
            }
            $g['status'] = $registration['status']; $g['capacity'] = $registration['capacity'];
            $g['registration_source'] = $registrationCourses[$id]['registration_status'] === 'automatic' ? 'letsreg' : 'local';
            $g['effective_registration_from'] = $registration['from'];
            $g['effective_registration_until'] = $registration['until'];
            if (!in_array($g['status'], ['available', 'external', 'waiting'], true)) { $g['registration_url'] = ''; }
            unset($g['editorial_status']);
        }
        unset($g);
        $choices = $policy->choices(array_values($models));
        return ['periods' => $publicPeriods, 'groups' => $groups,
            'current' => array_map(static fn ($p) => (int) $p->id, $choices['current']), 'upcoming' => array_map(static fn ($p) => (int) $p->id, $choices['upcoming']),
            'default' => $choices['default'] ? (int) $choices['default']->id : null, 'expires_at' => $expiry, 'now' => $now->getTimestamp()];
    }
}
