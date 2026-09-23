<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Presentation-only identity. Private metadata, separate from the teaching/publication revision. */
final class WebIdentity
{
    public const META = '_rnl_web';
    public static function boot(): void
    {
        foreach (['added_post_meta', 'updated_post_meta'] as $hook) {
            add_action($hook, static function ($mid, $id, $key): void {
                if ($key === ContentTypes::META && in_array(get_post_type($id), ['rnl_period', 'rnl_group'], true)) { self::ensure((int) $id); }
            }, 10, 3);
        }
        // Run after CPT registration, before TEC or any public links are rendered.
        // Updating plugin files must not require an administrator visit to finish installation.
        add_action('wp_loaded', [self::class, 'upgrade'], 20);
    }
    public static function upgrade(): void
    {
        if (get_option('rnl_web_version') === '2') { return; }
        Mutation::run(static function (): void {
            if (get_option('rnl_web_version') === '2') { return; }
            foreach (self::objects() as $id) { self::ensure($id, true); }
            update_option('rnl_web_version', '2', false);
            \RegiNor\Lite\Frontend\PublicRoutes::rules(); flush_rewrite_rules(false);
        });
    }
    private static function objects(): array
    {
        return array_map('intval', get_posts(['post_type' => ['rnl_period', 'rnl_group'], 'post_status' => ['draft', 'publish', 'private', 'pending', 'trash'], 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true]));
    }
    public static function language(): string { return (string) (apply_filters('wpml_current_language', null) ?: 'default'); }
    public static function read(int $id): array
    {
        $v = get_post_meta($id, self::META, true);
        return is_array($v) ? $v : ['version' => 0, 'languages' => [], 'history' => []];
    }
    public static function entry(int $id, ?string $language = null): array
    {
        $v = self::read($id); $language ??= self::language();
        return $v['languages'][$language] ?? $v['languages']['default'] ?? ['slug' => 'kurs-' . $id, 'aliases' => [], 'title' => '', 'description' => '', 'image_id' => 0];
    }
    private static function scope(int $id): string
    {
        $v = get_post_meta($id, ContentTypes::META, true);
        return get_post_type($id) === 'rnl_period' ? 'period' : 'group-' . ($v['data']['period_id'] ?? 0);
    }
    private static function free(string $slug, int $id): bool
    {
        foreach (self::objects() as $other) {
            if ($other === $id || self::scope($id) !== self::scope($other)) { continue; }
            $identity = self::read($other);
            // Reserve earlier fallback addresses until every legacy object is initialized.
            if (!$identity['version'] && $slug === 'kurs-' . $other) { return false; }
            foreach ($identity['languages'] as $entry) {
                if (in_array($slug, [$entry['slug'], ...$entry['aliases']], true)) { return false; }
            }
        }
        return true;
    }
    public static function ensure(int $id, bool $preserveFallback = false): void
    {
        if (!in_array(get_post_type($id), ['rnl_period', 'rnl_group'], true) || self::read($id)['version']) { return; }
        Mutation::run(static function () use ($id, $preserveFallback): void {
            if (self::read($id)['version']) { return; }
            $slug = sanitize_title((string) get_post_field('post_title', $id)) ?: 'kurs-' . $id;
            $base = $slug; $n = 2;
            while (!self::free($slug, $id)) { $slug = $base . '-' . $n++; }
            $fallback = 'kurs-' . $id;
            $aliases = $preserveFallback && $slug !== $fallback && self::free($fallback, $id) ? [$fallback] : [];
            $entry = ['slug' => $slug, 'aliases' => $aliases, 'title' => '', 'description' => '', 'image_id' => 0];
            if (!add_post_meta($id, self::META, ['version' => 1, 'languages' => ['default' => $entry], 'history' => []], true)) {
                throw new \RuntimeException(__('Kunne ikke lagre kursadressen. Prøv igjen.', 'reginor-lite'));
            }
            Mutation::touch($id);
        });
    }
    public static function save(int $id, int $version, string $language, array $input): void
    {
        Mutation::run(static function () use ($id, $version, $language, $input): void {
            // Repository performs object capability and valid-state checks, including published objects.
            (new CourseRepository())->get($id);
            if (!in_array(get_post_type($id), ['rnl_period', 'rnl_group'], true)) { throw new \InvalidArgumentException(__('Velg et kurs eller en kursperiode.', 'reginor-lite')); }
            if ($language !== 'default' && !isset(self::languages()[$language])) { throw new \InvalidArgumentException(__('Velg et tilgjengelig nettsidespråk.', 'reginor-lite')); }
            $old = self::read($id);
            if ($old['version'] !== $version) { throw new \RuntimeException(__('Delingsvalgene er endret av en annen. Last siden på nytt og sammenlign før du lagrer.', 'reginor-lite'), 409); }
            foreach (['slug', 'title', 'description', 'image_id'] as $key) { if (!isset($input[$key]) || !is_scalar($input[$key])) { throw new \InvalidArgumentException(__('Et delingsfelt har ugyldig format.', 'reginor-lite')); } }
            $slug = sanitize_title((string) $input['slug']);
            if (!$slug || strlen($slug) > 180 || !self::free($slug, $id)) { throw new \InvalidArgumentException(__('Adressen er tom, for lang eller allerede brukt. Velg en kort, unik adresse, for eksempel salsa-nybegynner-mandag.', 'reginor-lite')); }
            $image = filter_var($input['image_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($image === false || ($image && !self::image($image))) { throw new \InvalidArgumentException(__('Velg et tilgjengelig bilde fra mediebiblioteket.', 'reginor-lite')); }
            $previous = self::entry($id, $language);
            $aliases = array_values(array_diff(array_unique([...$previous['aliases'], $previous['slug']]), [$slug]));
            $next = $old; $next['version']++;
            $next['languages'][$language] = ['slug' => $slug, 'aliases' => $aliases, 'title' => sanitize_text_field((string) $input['title']), 'description' => sanitize_textarea_field((string) $input['description']), 'image_id' => $image];
            $next['history'][] = ['at' => gmdate(DATE_ATOM), 'actor' => get_current_user_id(), 'version' => $old['version'], 'language' => $language, 'previous' => $previous];
            $next['history'] = array_slice($next['history'], -50);
            if (!update_post_meta($id, self::META, wp_slash($next), $old)) { throw new \RuntimeException(__('Kunne ikke lagre delingsvalgene. Prøv igjen.', 'reginor-lite')); }
            Mutation::touch($id);
        });
    }
    public static function image(int $id): ?array
    {
        if (!$id || get_post_type($id) !== 'attachment' || get_post_status($id) === 'trash' || get_post_field('post_password', $id) !== '' || !wp_attachment_is_image($id)) { return null; }
        $image = wp_get_attachment_image_src($id, 'full');
        return $image ? ['url' => $image[0], 'width' => $image[1], 'height' => $image[2], 'alt' => sanitize_text_field((string) get_post_meta($id, '_wp_attachment_image_alt', true))] : null;
    }
    /** Only languages with a real published translation of the configured page. */
    public static function languages(): array
    {
        $result = []; $page = (int) get_option('rnl_course_page_id', 0);
        $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        foreach (is_array($languages) ? $languages : [] as $code => $language) {
            $id = (int) apply_filters('wpml_object_id', $page, 'page', false, $code);
            if ($id && get_post_status($id) === 'publish' && get_post_field('post_password', $id) === '') { $result[$code] = $language; }
        }
        return $result;
    }
}
