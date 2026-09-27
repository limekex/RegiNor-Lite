<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\Appearance;
use RegiNor\Lite\Infrastructure\ContentTypes;

/** Editorial restrictions apply before visitor filters and never widen through URL parameters. */
final class OverviewOptions
{
    public const DEFAULTS = [
        'default_view' => 'site', 'allowed_views' => 'list,week',
        'levels' => '', 'exclude_levels' => '', 'featured' => 'all',
        'styles' => '', 'exclude_styles' => '', 'days' => '', 'exclude_days' => '',
        'rooms' => '', 'exclude_rooms' => '', 'venues' => '', 'exclude_venues' => '',
        'instructors' => '', 'exclude_instructors' => '', 'courses' => '', 'exclude_courses' => '',
        'periods' => '', 'exclude_periods' => '', 'statuses' => '', 'exclude_statuses' => '', 'dropin' => 'all',
        'price_min' => '', 'price_max' => '', 'time_from' => '', 'time_until' => '', 'sessions_min' => '', 'sessions_max' => '',
        'price_bases' => '', 'exclude_price_bases' => '',
        'show_header' => true, 'show_filters' => true, 'show_view_switch' => true,
    ];

    public const LIST_FILTERS = ['levels', 'styles', 'days', 'rooms', 'venues', 'instructors', 'courses', 'periods', 'statuses', 'price_bases'];

    public const BOUNDS = ['price_min', 'price_max', 'time_from', 'time_until', 'sessions_min', 'sessions_max'];

    public static function normalize(array $attributes): array
    {
        $options = array_intersect_key($attributes, self::DEFAULTS) + self::DEFAULTS;
        foreach (['show_header', 'show_filters', 'show_view_switch'] as $key) {
            $options[$key] = !in_array(strtolower(trim(is_scalar($options[$key]) ? (string) $options[$key] : '')), ['', '0', 'false', 'no', 'off'], true);
        }
        foreach (array_merge(self::LIST_FILTERS, array_map(static fn ($key) => 'exclude_' . $key, self::LIST_FILTERS), ['allowed_views']) as $key) {
            $values = is_array($options[$key]) ? $options[$key] : explode(',', (string) $options[$key]);
            $options[$key] = array_values(array_unique(array_filter(array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $values), static fn ($v) => $v !== '')));
        }
        $options['allowed_views'] = array_values(array_intersect(['list', 'week'], $options['allowed_views'])) ?: ['list'];
        if (!in_array($options['default_view'], ['site', 'period', 'list', 'week'], true)) { $options['default_view'] = 'site'; }
        if ($options['default_view'] === 'site') { $options['default_view'] = Appearance::settings()['view']; }
        $options['featured'] = in_array($options['featured'], ['only', '1', 1, true], true) ? 'only' : ($options['featured'] === 'exclude' ? 'exclude' : 'all');
        $options['dropin'] = in_array($options['dropin'], ['only', 'exclude'], true) ? $options['dropin'] : 'all';
        $options['invalid'] = false;
        foreach (self::BOUNDS as $key) {
            $raw = is_scalar($options[$key]) ? trim((string) $options[$key]) : '!';
            $options[$key] = null;
            if ($raw === '') { continue; }
            $valid = str_starts_with($key, 'price_') ? preg_match('/^\d{1,7}(?:[.,]\d{1,2})?$/D', $raw)
                : (str_starts_with($key, 'time_') ? preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $raw) : preg_match('/^\d{1,4}$/D', $raw));
            if (!$valid) { $options['invalid'] = true; continue; }
            $options[$key] = str_starts_with($key, 'price_') ? (int) round((float) str_replace(',', '.', $raw) * 100) : (str_starts_with($key, 'time_') ? $raw : (int) $raw);
        }
        foreach ([['price_min', 'price_max'], ['time_from', 'time_until'], ['sessions_min', 'sessions_max']] as [$min, $max]) {
            if ($options[$min] !== null && $options[$max] !== null && $options[$min] > $options[$max]) { $options['invalid'] = true; }
        }
        return $options;
    }

    public static function restrict(array $catalog, array $options): array
    {
        if ($options['invalid']) { $catalog['groups'] = []; return $catalog; }
        $resources = [];
        $keys = static function (int $id) use (&$resources): array {
            if (!$id) { return []; }
            if (!isset($resources[$id])) {
                $state = get_post_meta($id, ContentTypes::META, true);
                $resources[$id] = [(string) $id, self::key((string) ($state['data']['title'] ?? get_post_field('post_title', $id)))];
            }
            return $resources[$id];
        };
        $accepts = static function (string $field, array $values) use ($options): bool {
            $include = array_map([self::class, 'key'], $options[$field]);
            $exclude = array_map([self::class, 'key'], $options['exclude_' . $field]);
            return (!$include || (bool) array_intersect($include, $values)) && !array_intersect($exclude, $values);
        };
        if ($options['periods'] || $options['exclude_periods']) {
            $catalog['periods'] = array_filter($catalog['periods'], static fn ($p) => $accepts('periods', $keys((int) $p['id'])));
            foreach (['current', 'upcoming'] as $field) { $catalog[$field] = array_values(array_filter($catalog[$field], static fn ($id) => isset($catalog['periods'][$id]))); }
            if (!isset($catalog['periods'][$catalog['default'] ?? 0])) { $catalog['default'] = $catalog['current'][0] ?? $catalog['upcoming'][0] ?? array_key_first($catalog['periods']); }
        }
        $catalog['groups'] = array_filter($catalog['groups'], static function (array $group) use ($options, $keys, $accepts, $catalog): bool {
            if (!isset($catalog['periods'][$group['period_id']])) { return false; }
            if (!$accepts('levels', $keys((int) ($group['level_id'] ?? 0)))) { return false; }
            if (!$accepts('courses', $keys((int) $group['id']))) { return false; }
            if (!$accepts('rooms', $keys((int) $group['room_id']))) { return false; }
            if (!$accepts('days', self::dayKeys((int) $group['weekday']))) { return false; }
            if (!$accepts('statuses', [(string) $group['status']])) { return false; }
            if (!$accepts('price_bases', [(string) $group['price_basis']])) { return false; }
            foreach (['price_minor' => ['price_min','price_max'], 'start_time' => ['time_from','time_until'], 'count' => ['sessions_min','sessions_max']] as $field => [$min, $max]) {
                if (($options[$min] !== null && $group[$field] < $options[$min]) || ($options[$max] !== null && $group[$field] > $options[$max])) { return false; }
            }
            if ($options['styles'] || $options['exclude_styles']) {
                // Editorial selection uses the common source, independent of WPML display language.
                $state = get_post_meta($group['id'], ContentTypes::META, true);
                $course = get_post_meta((int) ($state['data']['course_id'] ?? 0), ContentTypes::META, true);
                if (!$accepts('styles', [self::key(\RegiNor\Lite\Infrastructure\RichText::plain((string) ($course['data']['dance_style'] ?? '')))])) { return false; }
            }
            if ($options['venues'] || $options['exclude_venues']) {
                $room = get_post_meta($group['room_id'], ContentTypes::META, true);
                if (!$accepts('venues', $keys((int) ($room['data']['venue_id'] ?? 0)))) { return false; }
            }
            if ($options['instructors'] || $options['exclude_instructors']) {
                $teachers = [];
                foreach (array_keys($group['instructor_profiles'] ?? []) as $id) {
                    $main = \RegiNor\Lite\Infrastructure\Wpml::defaultProfile((int) $id);
                    if ($main) { $teachers = array_merge($teachers, $keys($main)); }
                }
                if (!$accepts('instructors', $teachers)) { return false; }
            }
            if ($options['featured'] !== 'all') {
                $featured = Appearance::course((int) $group['id'])['featured'];
                if (($options['featured'] === 'only') !== $featured) { return false; }
            }
            if ($options['dropin'] !== 'all' && (($options['dropin'] === 'only') !== (bool) $group['dropin_enabled'])) { return false; }
            return true;
        });
        return $catalog;
    }

    private static function key(string $value): string { return sanitize_title(trim($value)); }

    private static function dayKeys(int $day): array
    {
        return [(string) $day, ...([1 => ['mandag', 'man'], 2 => ['tirsdag', 'tirs'], 3 => ['onsdag', 'ons'], 4 => ['torsdag', 'tors'], 5 => ['fredag', 'fre'], 6 => ['lordag', 'lor'], 7 => ['sondag', 'son']][$day] ?? [])];
    }
}
