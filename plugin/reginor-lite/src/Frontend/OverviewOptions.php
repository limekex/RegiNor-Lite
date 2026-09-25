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
        'show_header' => true, 'show_filters' => true, 'show_view_switch' => true,
    ];

    public static function normalize(array $attributes): array
    {
        $options = array_intersect_key($attributes, self::DEFAULTS) + self::DEFAULTS;
        foreach (['show_header', 'show_filters', 'show_view_switch'] as $key) {
            $options[$key] = !in_array(strtolower(trim(is_scalar($options[$key]) ? (string) $options[$key] : '')), ['', '0', 'false', 'no', 'off'], true);
        }
        foreach (['levels', 'exclude_levels', 'allowed_views'] as $key) {
            $values = is_array($options[$key]) ? $options[$key] : explode(',', (string) $options[$key]);
            $options[$key] = array_values(array_unique(array_filter(array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $values), static fn ($v) => $v !== '')));
        }
        $options['allowed_views'] = array_values(array_intersect(['list', 'week'], $options['allowed_views'])) ?: ['list'];
        if (!in_array($options['default_view'], ['site', 'period', 'list', 'week'], true)) { $options['default_view'] = 'site'; }
        if ($options['default_view'] === 'site') { $options['default_view'] = Appearance::settings()['view']; }
        $options['featured'] = in_array($options['featured'], ['only', '1', 1, true], true) ? 'only' : ($options['featured'] === 'exclude' ? 'exclude' : 'all');
        return $options;
    }

    public static function restrict(array $catalog, array $options): array
    {
        $levelKeys = [];
        $catalog['groups'] = array_filter($catalog['groups'], static function (array $group) use ($options, &$levelKeys): bool {
            $level = (int) ($group['level_id'] ?? 0);
            if (!isset($levelKeys[$level])) {
                // Match the common source name, not a translated display label. IDs remain stable after renaming.
                $state = $level ? get_post_meta($level, ContentTypes::META, true) : [];
                $levelKeys[$level] = $level ? [(string) $level, sanitize_title((string) ($state['data']['title'] ?? ''))] : [];
            }
            $matches = static fn (array $values): bool => (bool) array_intersect($levelKeys[$level], array_map('sanitize_title', $values));
            if ($options['levels'] && !$matches($options['levels'])) { return false; }
            if ($options['exclude_levels'] && $matches($options['exclude_levels'])) { return false; }
            if ($options['featured'] !== 'all') {
                $featured = Appearance::course((int) $group['id'])['featured'];
                if (($options['featured'] === 'only') !== $featured) { return false; }
            }
            return true;
        });
        return $catalog;
    }
}
