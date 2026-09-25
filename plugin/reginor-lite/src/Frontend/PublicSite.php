<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class PublicSite
{
    private static int $renderCount = 0;
    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);
        add_action('template_redirect', [self::class, 'route'], 1);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_filter('pre_get_document_title', [self::class, 'title']);
        add_filter('get_canonical_url', [self::class, 'canonical'], 10, 2);
        add_filter('wpseo_canonical', static fn ($url) => self::onPage() ? self::canonical((string) $url) : $url);
        add_filter('rank_math/frontend/canonical', static fn ($url) => self::onPage() ? self::canonical((string) $url) : $url);
        add_filter('wp_robots', static function (array $robots): array {
            if (self::onPage() && array_intersect(['rnl_day', 'rnl_level', 'rnl_view'], array_keys($_GET))) { $robots['noindex'] = true; unset($robots['index']); }
            return $robots;
        });
        add_action('wp_sitemaps_init', static function (): void { wp_register_sitemap_provider('reginor', new CourseSitemap()); });
    }

    public static function pageId(): int
    {
        $id = (int) get_option('rnl_course_page_id', 0);
        $id = $id ? \RegiNor\Lite\Infrastructure\Wpml::page($id) : 0;
        return get_post_type($id) === 'page' && get_post_status($id) === 'publish' && get_post_field('post_password', $id) === '' ? $id : 0;
    }
    public static function onPage(): bool { return self::pageId() > 0 && is_page(self::pageId()); }
    public static function url(?int $groupId = null, array $query = []): string
    {
        $base = self::pageId() ? get_permalink(self::pageId()) : '';
        if (!$base) { return ''; }
        if (PublicRoutes::enabled()) {
            $base = PublicRoutes::url(0);
            $catalog = (new Catalog())->read();
            $group = $groupId ? ($catalog['groups'][$groupId] ?? null) : null;
            $period = $group ? $group['period_id'] : (int) ($query['rnl_period'] ?? 0);
            if ($period && isset($catalog['periods'][$period]) && (!$groupId || $group)) {
                $base = PublicRoutes::url($period, $groupId); unset($query['rnl_period']);
            } elseif ($groupId !== null) { $query['rnl_course'] = $groupId; }
        } elseif ($groupId !== null) { $query['rnl_course'] = $groupId; }
        return $query ? add_query_arg($query, $base) : $base;
    }
    public static function integer(string $name): ?int
    {
        $value = PublicRoutes::query($_GET)[$name] ?? null;
        return is_scalar($value) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value) ? (int) $value : null;
    }
    public static function noCache(): void
    {
        \RegiNor\Lite\Infrastructure\CachePolicy::disable();
    }
    public static function register(): void
    {
        add_shortcode('reginor_courses', [self::class, 'render']);
        $url = plugins_url('assets/', dirname(__DIR__, 2) . '/reginor-lite.php');
        wp_register_script('rnl-block', $url . 'block.js', ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'], (string) filemtime(dirname(__DIR__, 2) . '/assets/block.js'), true);
        wp_set_script_translations('rnl-block', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
        register_block_type('reginor-lite/courses', ['api_version' => 3, 'editor_script' => 'rnl-block',
            'attributes' => ['default_view' => ['type' => 'string', 'default' => 'site'], 'allowed_views' => ['type' => 'array', 'default' => ['list', 'week']],
                'levels' => ['type' => 'string', 'default' => ''], 'exclude_levels' => ['type' => 'string', 'default' => ''],
                'featured' => ['type' => 'string', 'default' => 'all'], 'show_header' => ['type' => 'boolean', 'default' => true],
                'show_filters' => ['type' => 'boolean', 'default' => true], 'show_view_switch' => ['type' => 'boolean', 'default' => true]],
            'render_callback' => [self::class, 'render']]);
    }
    public static function route(): void
    {
        if (is_404()) { return; }
        if (!self::onPage()) {
            // Set headers before the theme renders an overview embedded on another page.
            // A late shortcode callback cannot reliably change headers after output has begun.
            if (is_singular() && self::containsCourses((string) get_post_field('post_content', get_queried_object_id()))) { self::noCache(); }
            return;
        }
        self::noCache();
        $catalog = (new Catalog())->read();
        $hidden = (isset($_GET['rnl_course']) && (!self::integer('rnl_course') || !isset($catalog['groups'][self::integer('rnl_course')])))
            || (isset($_GET['rnl_period']) && (!self::integer('rnl_period') || !isset($catalog['periods'][self::integer('rnl_period')])));
        if ($hidden) {
            global $wp_query; $wp_query->set_404(); status_header(404);
            add_filter('the_content', static fn () => ('<section class="rnl-ui rnl-public"><h1>' . esc_html(__('Dette kurset er ikke tilgjengelig nå', 'reginor-lite')) . '</h1><p>' . esc_html(__('Du kan se hvilke kurs som er tilgjengelige i kursoversikten.', 'reginor-lite')) . '</p><a class="rnl-button" href="') . esc_url(self::url()) . ('">' . esc_html(__('Se tilgjengelige kurs', 'reginor-lite')) . '</a></section>'), 99);
            add_filter('redirect_canonical', '__return_false');
        } elseif (!PublicRoutes::$selection && PublicRoutes::enabled() && (self::integer('rnl_course') || self::integer('rnl_period'))) {
            $target = self::url(self::integer('rnl_course'), self::integer('rnl_period') ? ['rnl_period' => self::integer('rnl_period')] : []);
            wp_safe_redirect(add_query_arg(PublicRoutes::retainedQuery(), $target), 301); exit;
        }
    }
    public static function containsCourses(string $content, array $visited = []): bool
    {
        if (has_shortcode($content, 'reginor_courses') || has_block('reginor-lite/courses', $content)) { return true; }
        $inspect = static function (array $blocks) use (&$inspect, $visited): bool {
            foreach ($blocks as $block) {
                if (($block['blockName'] ?? '') === 'core/block') {
                    $id = (int) ($block['attrs']['ref'] ?? 0);
                    if ($id > 0 && !in_array($id, $visited, true) && get_post_type($id) === 'wp_block') {
                        // Conservatively disable caching if a block chain is too deep to inspect.
                        if (count($visited) >= 20 || self::containsCourses((string) get_post_field('post_content', $id), [...$visited, $id])) { return true; }
                    }
                }
                if ($inspect($block['innerBlocks'] ?? [])) { return true; }
            }
            return false;
        };
        return $inspect(parse_blocks($content));
    }
    public static function assets(): void
    {
        // Scoped styles are harmless on embedding pages and must be available before shortcode rendering.
        if (is_admin()) { return; }
        $url = plugins_url('assets/', dirname(__DIR__, 2) . '/reginor-lite.php');
        wp_enqueue_style('rnl-interface', $url . 'interface.css', [], (string) filemtime(dirname(__DIR__, 2) . '/assets/interface.css'));
        wp_enqueue_script('rnl-interface', $url . 'interface.js', ['wp-i18n'], (string) filemtime(dirname(__DIR__, 2) . '/assets/interface.js'), true);
        wp_set_script_translations('rnl-interface', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
        wp_enqueue_script('rnl-course-tools', $url . 'course-tools.js', ['wp-i18n'], (string) filemtime(dirname(__DIR__, 2) . '/assets/course-tools.js'), true);
        wp_set_script_translations('rnl-course-tools', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
    }
    public static function render(array|string $attributes = []): string
    {
        self::noCache();
        if (!self::pageId()) { return current_user_can('manage_options') ? ('<p>' . esc_html(__('Velg siden for kursoversikten under RegiNor Lite → Nettsidevisning.', 'reginor-lite')) . '</p>') : ''; }
        $attributes = OverviewOptions::normalize(is_array($attributes) ? $attributes : []);
        $catalog = OverviewOptions::restrict((new Catalog())->read(), $attributes);
        $attributes['page_query'] = wp_unslash($_GET);
        $query = PublicRoutes::query($_GET);
        if (is_404()) { return ''; }
        $instance = ++self::$renderCount;
        if (!self::onPage() || $instance > 1) {
            $attributes['instance'] = (string) $instance;
            $attributes['base_url'] = is_singular() ? get_permalink(get_queried_object_id()) : self::url();
            $selections = is_array($_GET['rnl_embed'] ?? null) ? $_GET['rnl_embed'] : [];
            $selection = $selections[$instance] ?? [];
            $query = is_array($selection) ? array_intersect_key($selection, array_flip(['rnl_period', 'rnl_day', 'rnl_level', 'rnl_view'])) : [];
            // Preserve existing links selecting a period on an embedding page.
            if (!self::onPage() && !isset($query['rnl_period']) && isset($_GET['rnl_period'])) { $query['rnl_period'] = $_GET['rnl_period']; }
        }
        if (!$attributes['show_filters']) { unset($query['rnl_day'], $query['rnl_level']); }
        if (!$attributes['show_view_switch']) { unset($query['rnl_view']); }
        return (new Renderer())->render($catalog, $query, (string) $attributes['default_view'], $attributes['allowed_views'], $attributes);
    }
    public static function title(string $title): string
    {
        if (self::onPage() && self::integer('rnl_course')) { $g = (new Catalog())->read()['groups'][self::integer('rnl_course')] ?? null; if ($g) { return $g['title'] . ' – ' . get_bloginfo('name'); } }
        return $title;
    }
    public static function canonical(string $url, mixed $post = null): string
    {
        if (!self::onPage() || ($post instanceof \WP_Post && $post->ID !== self::pageId())) { return $url; }
        if (self::integer('rnl_course') && isset((new Catalog())->read()['groups'][self::integer('rnl_course')])) { return self::url(self::integer('rnl_course')); }
        return self::url(null, self::integer('rnl_period') ? ['rnl_period' => self::integer('rnl_period')] : []);
    }
}
