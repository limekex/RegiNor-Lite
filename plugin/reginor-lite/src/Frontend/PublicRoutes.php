<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\WebIdentity;

/** Routes resolve exclusively against the public projection; hidden aliases never redirect. */
final class PublicRoutes
{
    public static ?array $selection = null;
    public static function boot(): void
    {
        add_action('init', [self::class, 'rules'], 99);
        add_action('wp_loaded', [self::class, 'upgrade'], 21);
        add_filter('option_rewrite_rules', static function ($rules) {
            if (is_array($rules) && !self::enabled()) { unset($rules['^kursrekke/?$'], $rules['^kursrekke/([^/]+)(?:/([^/]+))?/?$']); }
            return $rules;
        });
        add_filter('query_vars', static fn ($v) => [...$v, 'rnl_route_index', 'rnl_route_period', 'rnl_route_course']);
        add_filter('request', static function (array $v): array {
            if (empty($v['rnl_route_period']) && empty($v['rnl_route_index'])) { return $v; }
            $v['page_id'] = PublicSite::pageId();
            return $v;
        });
        add_action('template_redirect', [self::class, 'route'], 0);
        foreach (['update_option_rnl_course_page_id', 'update_option_rnl_pretty_urls'] as $hook) {
            add_action($hook, static function (): void { self::rules(); flush_rewrite_rules(false); });
        }
        add_filter('wpml_active_languages', static function ($languages) {
            if ((!self::$selection && !get_query_var('rnl_route_index')) || !is_array($languages)) { return $languages; }
            foreach ($languages as $code => &$language) {
                $page = (int) apply_filters('wpml_object_id', (int) get_option('rnl_course_page_id'), 'page', false, $code);
                if (!$page || get_post_status($page) !== 'publish' || get_post_field('post_password', $page) !== '') { unset($languages[$code]); continue; }
                $language['url'] = self::url(self::$selection['period'] ?? 0, self::$selection['group'] ?? null, $code);
            }
            unset($language); return $languages;
        }, 20);
    }
    public static function upgrade(): void
    {
        if (get_option('rnl_public_routes_version') === '1') { return; }
        self::rules();
        flush_rewrite_rules(false);
        update_option('rnl_public_routes_version', '1', false);
    }
    public static function enabled(): bool
    {
        // Never take over an existing WordPress section. Query URLs remain available as fallback.
        foreach (get_post_types(['public' => true], 'objects') as $type) { if (is_array($type->rewrite) && trim($type->rewrite['slug'] ?? '', '/') === 'kursrekke') { return false; } }
        foreach (get_taxonomies(['public' => true], 'objects') as $type) { if (is_array($type->rewrite) && trim($type->rewrite['slug'] ?? '', '/') === 'kursrekke') { return false; } }
        return (bool) get_option('permalink_structure') && (bool) get_option('rnl_pretty_urls', true) && !get_page_by_path('kursrekke');
    }
    public static function rules(): void
    {
        global $wp_rewrite;
        unset($wp_rewrite->extra_rules_top['^kursrekke/?$'], $wp_rewrite->extra_rules_top['^kursrekke/([^/]+)(?:/([^/]+))?/?$']);
        if (self::enabled()) {
            add_rewrite_rule('^kursrekke/?$', 'index.php?rnl_route_index=1', 'top');
            add_rewrite_rule('^kursrekke/([^/]+)(?:/([^/]+))?/?$', 'index.php?rnl_route_period=$matches[1]&rnl_route_course=$matches[2]', 'top'); }
    }
    public static function url(int $period, ?int $group = null, ?string $language = null): string
    {
        $path = 'kursrekke';
        if ($period) { $path .= '/' . WebIdentity::entry($period, $language)['slug']; }
        if ($group) { $path .= '/' . WebIdentity::entry($group, $language)['slug']; }
        $url = home_url(user_trailingslashit('/' . $path));
        return (string) apply_filters('wpml_permalink', $url, $language ?? apply_filters('wpml_current_language', null), true);
    }
    public static function resolve(array $catalog, string $periodSlug, string $groupSlug = '', ?string $language = null): ?array
    {
        foreach ($catalog['periods'] as $period) {
            $p = WebIdentity::entry($period['id'], $language);
            if (!in_array($periodSlug, [$p['slug'], ...$p['aliases']], true)) { continue; }
            if ($groupSlug === '') { return ['period' => $period['id'], 'group' => null]; }
            foreach ($catalog['groups'] as $group) {
                if ($group['period_id'] !== $period['id']) { continue; }
                $g = WebIdentity::entry($group['id'], $language);
                if (in_array($groupSlug, [$g['slug'], ...$g['aliases']], true)) { return ['period' => $period['id'], 'group' => $group['id']]; }
            }
            return null;
        }
        return null;
    }
    public static function query(array $query): array
    {
        if (self::$selection) {
            unset($query['rnl_course'], $query['rnl_period']);
            $query['rnl_period'] = (string) self::$selection['period'];
            if (self::$selection['group']) { $query['rnl_course'] = (string) self::$selection['group']; }
        }
        return $query;
    }
    public static function retainedQuery(): array
    {
        // Preserve arbitrary campaign/linker parameters; exclude only our routing identity.
        return array_diff_key(wp_unslash($_GET), array_flip(['rnl_course', 'rnl_period', 'rnl_route_index', 'rnl_route_period', 'rnl_route_course', 'page_id', 'pagename']));
    }
    public static function route(): void
    {
        $slug = get_query_var('rnl_route_period');
        $index = get_query_var('rnl_route_index');
        if (!$slug && !$index) { return; }
        PublicSite::noCache(); add_filter('redirect_canonical', '__return_false');
        if ($index && !$slug) {
            if (!self::enabled() || !PublicSite::pageId()) { global $wp_query; $wp_query->set_404(); status_header(404); return; }
            $url = self::url(0);
            $path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
            if (rawurldecode($path) !== rawurldecode((string) wp_parse_url($url, PHP_URL_PATH))) {
                wp_safe_redirect(add_query_arg(array_diff_key(wp_unslash($_GET), array_flip(['rnl_route_index', 'rnl_route_period', 'rnl_route_course', 'page_id', 'pagename'])), $url), 301); exit;
            }
            // The root always uses the shortcode's defaults, inside the configured page's theme template.
            add_filter('the_content', static fn ($content) => in_the_loop() && is_main_query() && !is_404() ? '[reginor_courses]' : $content, 9);
            return;
        }
        $course = get_query_var('rnl_route_course');
        self::$selection = is_string($slug) && is_string($course) && self::enabled() && PublicSite::pageId()
            ? self::resolve((new Catalog())->read(), sanitize_title($slug), $course === '' ? '' : sanitize_title($course)) : null;
        if (!self::$selection) { global $wp_query; $wp_query->set_404(); status_header(404); return; }
        $url = self::url(self::$selection['period'], self::$selection['group']);
        $requestPath = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (rawurldecode($requestPath) !== rawurldecode((string) wp_parse_url($url, PHP_URL_PATH))) {
            wp_safe_redirect(add_query_arg(self::retainedQuery(), $url), 301); exit;
        }
        // Query identifiers must not select another course while keeping this route's metadata.
        if (isset($_GET['rnl_course']) || isset($_GET['rnl_period'])) { wp_safe_redirect(add_query_arg(self::retainedQuery(), $url), 301); exit; }
        add_filter('the_content', static function ($content) {
            return in_the_loop() && is_main_query() && !PublicSite::containsCourses($content) ? PublicSite::render() : $content;
        }, 9);
    }
}
