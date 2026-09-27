<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Editorial level pages, independent of operational course data and legacy taxonomies. */
final class LevelArchiveStore
{
    public const META = '_rnl_level_archive';
    public const TEXTS = ['slug' => '', 'aliases' => [], 'title' => '', 'intro' => '', 'extra' => '', 'description' => ''];
    public static function read(int $id): array
    {
        $value = get_post_meta($id, self::META, true);
        return (is_array($value) ? $value : []) + ['version' => 0, 'enabled' => false, 'faq' => '', 'articles' => '', 'limit' => 6, 'order' => 'date', 'languages' => []];
    }
    public static function entry(int $id, ?string $language = null): array
    {
        $state = self::read($id); $language ??= WebIdentity::language();
        return ($state['languages'][$language] ?? $state['languages']['default'] ?? []) + self::TEXTS;
    }
    public static function ids(): array
    {
        return array_map('intval', get_posts(['post_type' => 'rnl_level', 'post_status' => ['draft', 'publish'], 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true]));
    }
    public static function publicData(int $id, ?string $language = null): ?array
    {
        if (get_post_type($id) !== 'rnl_level' || !in_array(get_post_status($id), ['draft', 'publish'], true) || get_post_field('post_password', $id) !== '') { return null; }
        $state = self::read($id); $entry = self::entry($id, $language);
        if (!$state['enabled'] || !$entry['slug']) { return null; }
        $level = get_post_meta($id, ContentTypes::META, true);
        try { StateSchema::validate('level', $level['data'] ?? []); } catch (\Throwable) { return null; }
        return $entry + ['id' => $id, 'settings' => $state];
    }
    /** Explicit post type + taxonomy + term reference; never match visible tag words. */
    public static function source(string $reference): ?array
    {
        $parts = explode(':', $reference);
        if (count($parts) !== 3 || !ctype_digit($parts[2])) { return null; }
        [$type, $taxonomy, $id] = $parts;
        $object = get_post_type_object($type); $tax = get_taxonomy($taxonomy);
        if (!$object || !$object->public || $type === 'attachment' || str_starts_with($type, 'rnl_') || !$tax || !is_object_in_taxonomy($type, $taxonomy) || !$tax->show_ui) { return null; }
        $term = get_term((int) $id, $taxonomy);
        return $term instanceof \WP_Term ? ['type' => $type, 'taxonomy' => $taxonomy, 'term' => (int) $id] : null;
    }
    public static function save(int $id, int $version, string $language, array $input): void
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Bare administrator kan endre nivåarkiver.', 'reginor-lite'), 403); }
        Mutation::run(static function () use ($id, $version, $language, $input): void {
            (new CourseRepository())->get($id, 'level');
            if ($language !== 'default' && !isset(WebIdentity::languages()[$language])) { throw new \InvalidArgumentException(__('Velg et tilgjengelig nettsidespråk.', 'reginor-lite')); }
            $old = self::read($id);
            if ($old['version'] !== $version) { throw new VersionConflict(); }
            $entry = [];
            foreach (['slug', 'title', 'intro', 'extra', 'description'] as $key) {
                if (!isset($input[$key]) || !is_string($input[$key]) || strlen($input[$key]) > 50000) { throw new \InvalidArgumentException(__('Et arkivfelt mangler eller er for langt.', 'reginor-lite')); }
                $entry[$key] = in_array($key, ['intro', 'extra'], true) ? RichText::clean($input[$key]) : sanitize_text_field($input[$key]);
            }
            $entry['slug'] = sanitize_title($entry['slug']);
            if (!$entry['slug'] || strlen($entry['slug']) > 160 || !$entry['title']) { throw new \InvalidArgumentException(__('Fyll ut arkivtittel og en kort adresse.', 'reginor-lite')); }
            foreach (self::ids() as $other) {
                if ($other === $id) { continue; }
                foreach (self::read($other)['languages'] as $otherEntry) {
                    if (in_array($entry['slug'], [$otherEntry['slug'], ...$otherEntry['aliases']], true)) { throw new \InvalidArgumentException(__('Nivåadressen er allerede brukt. Velg en annen.', 'reginor-lite')); }
                }
            }
            $before = self::entry($id, $language);
            $entry['aliases'] = array_values(array_diff(array_unique(array_filter([$before['slug'], ...$before['aliases']])), [$entry['slug']]));
            $next = $old; $next['languages'][$language] = $entry; $next['version']++;
            if ($language === 'default') {
                foreach (['faq', 'articles'] as $key) {
                    if (!isset($input[$key]) || !is_string($input[$key]) || ($input[$key] !== '' && !self::source($input[$key]))) { throw new \InvalidArgumentException(__('Velg et tilgjengelig stikkord eller slå av innholdsutvalget.', 'reginor-lite')); }
                    $next[$key] = $input[$key];
                }
                $limit = filter_var($input['limit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
                if (!$limit || !in_array($input['order'] ?? '', ['date', 'title'], true)) { throw new \InvalidArgumentException(__('Velg antall fra 1 til 12 og en gyldig sortering.', 'reginor-lite')); }
                $next['limit'] = $limit; $next['order'] = $input['order'];
                $next['enabled'] = in_array($input['enabled'] ?? '', ['1', 1, true], true);
            }
            Mutation::touch($id);
            if (!update_post_meta($id, self::META, wp_slash($next))) { throw new \RuntimeException(__('Nivåarkivet kunne ikke lagres. Prøv igjen.', 'reginor-lite')); }
        });
    }
    public static function related(array $settings, string $kind): array
    {
        $source = self::source($settings[$kind] ?? ''); if (!$source) { return []; }
        $language = apply_filters('wpml_current_language', null);
        $term = $language ? (int) apply_filters('wpml_object_id', $source['term'], $source['taxonomy'], false, $language) : $source['term'];
        if (!$term) { return []; }
        $query = new \WP_Query(['post_type' => $source['type'], 'post_status' => 'publish', 'has_password' => false,
            'posts_per_page' => 100, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'suppress_filters' => false,
            'orderby' => [$settings['order'] => $settings['order'] === 'date' ? 'DESC' : 'ASC', 'ID' => 'ASC'],
            'tax_query' => [['taxonomy' => $source['taxonomy'], 'field' => 'term_id', 'terms' => [$term], 'include_children' => false]]]);
        $result = [];
        foreach ($query->posts as $post) {
            $translated = $language ? (int) apply_filters('wpml_object_id', $post->ID, $source['type'], false, $language) : $post->ID;
            $post = $translated ? get_post($translated) : null;
            if (!$post || $post->post_type !== $source['type'] || $post->post_status !== 'publish' || $post->post_password !== '' || !has_term($term, $source['taxonomy'], $post)) { continue; }
            $result[$post->ID] = $post;
            if (count($result) >= $settings['limit']) { break; }
        }
        return array_values($result);
    }
}
