<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

/** Translate presentation text, never operational aggregates, IDs, prices or time windows. */
final class Wpml
{
    private static array $pending = [];
    public const CONTEXT = 'RegiNor Lite courses';

    public static function boot(): void
    {
        foreach (['added_post_meta', 'updated_post_meta'] as $hook) {
            add_action($hook, static function ($metaId, $id, $key): void {
                if ($key === ContentTypes::META) { self::$pending[(int) $id] = true; }
            }, 10, 3);
        }
        // Read committed state at shutdown, rather than sending intermediate transaction data to WPML.
        add_action('shutdown', [self::class, 'flush']);
        add_action('admin_init', static function (): void {
            if (!current_user_can('manage_options') || !has_action('wpml_register_single_string') || get_option('rnl_wpml_strings_version') === '1') { return; }
            foreach (get_posts(['post_type' => array_values(ContentTypes::TYPES), 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) { self::$pending[$id] = true; }
            self::flush(); update_option('rnl_wpml_strings_version', '1', false);
        });
    }

    private static function fields(string $kind): array
    {
        return match ($kind) {
            'course' => ['title', 'description', 'level_description', 'dance_style', 'partner_info'],
            'level' => ['title', 'description'],
            'group' => ['title', 'price_terms'], 'venue' => ['title', 'address'],
            'period', 'room' => ['title'], default => [],
        };
    }

    private static function texts(string $kind, array $data): array
    {
        $texts = array_intersect_key($data, array_flip(self::fields($kind)));
        foreach (['sessions', 'breaks'] as $list) {
            foreach ($data[$list] ?? [] as $entry) { $texts[$list . '.' . $entry['id'] . '.reason'] = $entry['reason']; }
        }
        return $texts;
    }

    public static function flush(): void
    {
        $ids = array_keys(self::$pending); self::$pending = [];
        if (!has_action('wpml_register_single_string')) { return; }
        $language = apply_filters('wpml_default_language', null);
        foreach ($ids as $id) {
            $kind = array_search(get_post_type($id), ContentTypes::TYPES, true);
            if ($kind === false || !in_array(get_post_status($id), ['draft', 'publish'], true)) { continue; }
            wp_cache_delete($id, 'post_meta');
            $rows = get_post_meta($id, ContentTypes::META, false);
            if (count($rows) !== 1 || !isset($rows[0]['data'])) { continue; }
            try { $data = StateSchema::validate($kind, $rows[0]['data']); } catch (\Throwable) { continue; }
            foreach (self::texts($kind, $data) as $field => $value) {
                do_action('wpml_register_single_string', self::CONTEXT, $kind . '.' . $id . '.' . $field, $value, true, $language);
            }
        }
    }

    public static function data(int $id, string $kind, array $data): array
    {
        $translate = static function (string $field, string $value) use ($id, $kind): string {
            $translated = apply_filters('wpml_translate_single_string', $value, self::CONTEXT, $kind . '.' . $id . '.' . $field);
            if (!is_string($translated) || trim($translated) === '') { return $value; }
            $rich = ($kind === 'course' && in_array($field, ['description', 'level_description', 'partner_info'], true)) || ($kind === 'group' && $field === 'price_terms');
            return $rich ? RichText::clean($translated) : sanitize_textarea_field($translated);
        };
        foreach (self::fields($kind) as $key) { if (isset($data[$key])) { $data[$key] = $translate($key, $data[$key]); } }
        foreach (['sessions', 'breaks'] as $list) {
            if (!isset($data[$list])) { continue; }
            foreach ($data[$list] as &$entry) { $entry['reason'] = $translate($list . '.' . $entry['id'] . '.reason', $entry['reason']); } unset($entry);
        }
        return $data;
    }

    public static function page(int $id): int
    {
        return (int) apply_filters('wpml_object_id', $id, 'page', true);
    }
}
