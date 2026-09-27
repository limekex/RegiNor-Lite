<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\{LevelArchiveStore, WebIdentity, RichText};
use function RegiNor\Lite\translate as __;

final class LevelArchive
{
    public const RULE = '^kursrekke/niva/([^/]+)/?$';
    public static ?array $selection = null;
    public static function boot(): void
    {
        add_action('template_redirect', [self::class, 'route'], -2);
    }
    public static function prettyEnabled(): bool
    {
        if (!PublicRoutes::enabled() || get_page_by_path('kursrekke/niva')) { return false; }
        // Do not steal an older period address/alias. Its owner must resolve the conflict explicitly.
        foreach (get_posts(['post_type' => 'rnl_period', 'post_status' => ['draft', 'publish', 'private', 'pending', 'trash'], 'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            foreach (WebIdentity::read((int) $id)['languages'] as $entry) {
                if (in_array('niva', [$entry['slug'], ...$entry['aliases']], true)) { return false; }
            }
        }
        foreach (array_merge(get_post_types(['public' => true], 'objects'), get_taxonomies(['public' => true], 'objects')) as $object) {
            if (is_array($object->rewrite) && str_starts_with(trim($object->rewrite['slug'] ?? '', '/'), 'kursrekke/niva')) { return false; }
        }
        return true;
    }
    public static function url(int $id, ?string $language = null): string
    {
        if (self::prettyEnabled()) {
            $url = home_url(user_trailingslashit('/kursrekke/niva/' . LevelArchiveStore::entry($id, $language)['slug']));
        } else { $url = add_query_arg('rnl_level_archive', $id, get_permalink(PublicSite::pageId())); }
        return (string) apply_filters('wpml_permalink', $url, $language ?? apply_filters('wpml_current_language', null), true);
    }
    public static function resolve(string $slug, ?string $language = null): ?array
    {
        foreach (LevelArchiveStore::ids() as $id) {
            $entry = LevelArchiveStore::publicData($id, $language);
            if ($entry && in_array($slug, [$entry['slug'], ...$entry['aliases']], true)) { return $entry; }
        }
        return null;
    }
    public static function route(): void
    {
        $slug = get_query_var('rnl_route_level'); $query = $_GET['rnl_level_archive'] ?? null;
        if (!$slug && $query === null) { return; }
        PublicSite::noCache(); add_filter('redirect_canonical', '__return_false');
        self::$selection = $slug && is_string($slug) && self::prettyEnabled() ? self::resolve(sanitize_title($slug))
            : (is_scalar($query) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $query) ? LevelArchiveStore::publicData((int) $query) : null);
        if (!PublicSite::onPage() || !self::$selection) { self::$selection = null; global $wp_query; $wp_query->set_404(); status_header(404); return; }
        $url = self::url(self::$selection['id']);
        $path = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (rawurldecode($path) !== rawurldecode((string) wp_parse_url($url, PHP_URL_PATH)) || isset($_GET['rnl_course'])) {
            $retained = array_diff_key(PublicRoutes::retainedQuery(), array_flip(['rnl_level_archive', 'rnl_route_level']));
            wp_safe_redirect(add_query_arg($retained, $url), 301); exit;
        }
        add_filter('the_content', static fn ($content) => in_the_loop() && is_main_query() && !is_404() ? self::render(self::$selection) : $content, 11);
    }
    public static function render(array $entry): string
    {
        PublicSite::noCache();
        $options = OverviewOptions::normalize(['levels' => (string) $entry['id'], 'show_header' => false]);
        $catalog = OverviewOptions::restrict((new Catalog())->read(), $options);
        // Only ongoing/upcoming offers belong on a level landing page; empty archives keep their editorial content.
        $periods = array_values(array_unique([...$catalog['current'], ...$catalog['upcoming']]));
        $catalog['groups'] = array_filter($catalog['groups'], static fn ($g) => in_array($g['period_id'], $periods, true));
        $used = array_unique(array_column($catalog['groups'], 'period_id'));
        $catalog['periods'] = array_intersect_key($catalog['periods'], array_flip($used));
        $catalog['current'] = array_values(array_intersect($catalog['current'], $used));
        $catalog['upcoming'] = array_values(array_intersect($catalog['upcoming'], $used));
        $catalog['default'] = $catalog['current'][0] ?? $catalog['upcoming'][0] ?? null;
        $query = array_intersect_key($_GET, array_flip(['rnl_period', 'rnl_day', 'rnl_view']));
        if (!isset($catalog['periods'][(int) (is_scalar($query['rnl_period'] ?? null) ? $query['rnl_period'] : 0)])) { unset($query['rnl_period']); }
        $options += ['show_level_filter' => false, 'base_url' => self::url($entry['id']), 'page_query' => array_diff_key(wp_unslash($_GET), array_flip(['rnl_level', 'rnl_course']))];
        ob_start();
        echo '<article class="rnl-ui rnl-level-archive"><header><h1>' . esc_html($entry['title']) . '</h1><div class="rnl-rich-content">' . RichText::html($entry['intro']) . '</div></header>';
        echo (new Renderer())->render($catalog, $query, $options['default_view'], $options['allowed_views'], $options);
        if ($entry['extra'] !== '') { echo '<section class="rnl-panel rnl-rich-content">' . RichText::html($entry['extra']) . '</section>'; }
        $related = ['articles' => LevelArchiveStore::related($entry['settings'], 'articles'), 'faq' => LevelArchiveStore::related($entry['settings'], 'faq')];
        if (array_filter($related)) { echo '<div class="rnl-level-related-layout">'; }
        foreach (['articles' => __('Relevante artikler', 'reginor-lite'), 'faq' => __('Ofte stilte spørsmål', 'reginor-lite')] as $kind => $heading) {
            $posts = $related[$kind]; if (!$posts) { continue; }
            $carousel = $kind === 'articles';
            $listId = 'rnl-level-' . $entry['id'] . '-' . $kind;
            echo '<section class="rnl-level-related"' . ($carousel ? ' data-rnl-carousel' : '') . ' aria-labelledby="' . esc_attr($listId . '-heading') . '"><div class="rnl-level-section-heading"><h2 id="' . esc_attr($listId . '-heading') . '">' . esc_html($heading) . '</h2>';
            if ($carousel && count($posts) > 1) {
                echo '<nav hidden data-rnl-carousel-controls aria-label="' . esc_attr(__('Bla i artikler', 'reginor-lite')) . '">';
                foreach (['previous' => __('Forrige artikler', 'reginor-lite'), 'next' => __('Neste artikler', 'reginor-lite')] as $direction => $label) {
                    echo '<button type="button" class="rnl-button rnl-button-secondary" data-rnl-carousel-' . esc_attr($direction) . ' aria-controls="' . esc_attr($listId) . '" aria-label="' . esc_attr($label) . '"><span aria-hidden="true">' . ($direction === 'previous' ? '←' : '→') . '</span></button>';
                }
                echo '<button type="button" class="rnl-button rnl-button-secondary" data-rnl-carousel-play data-play-label="' . esc_attr(__('Start avspilling', 'reginor-lite')) . '" data-pause-label="' . esc_attr(__('Pause avspilling', 'reginor-lite')) . '" aria-controls="' . esc_attr($listId) . '">' . esc_html(__('Pause avspilling', 'reginor-lite')) . '</button></nav>';
            }
            echo '</div><ul id="' . esc_attr($listId) . '" class="' . ($carousel ? 'rnl-article-track' : 'rnl-faq-list') . '"' . ($carousel ? ' data-rnl-carousel-track tabindex="0" aria-label="' . esc_attr($heading) . '"' : '') . '>';
            foreach ($posts as $post) {
                $title = get_the_title($post); $url = get_permalink($post);
                echo '<li><article class="' . ($carousel ? 'rnl-article-card' : 'rnl-faq-preview') . '">';
                if ($carousel && has_post_thumbnail($post)) { echo '<a class="rnl-article-image" tabindex="-1" aria-hidden="true" href="' . esc_url($url) . '">' . get_the_post_thumbnail($post, 'medium_large', ['loading' => 'lazy', 'alt' => '']) . '</a>'; }
                echo '<div class="rnl-related-body"><h3><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></h3><p>' . esc_html(SharingMetadata::plain(strip_shortcodes($post->post_excerpt ?: $post->post_content), $carousel ? 240 : 170)) . '</p>';
                if ($carousel) { echo '<time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('', $post)) . '</time>'; }
                echo '<a class="rnl-related-more" href="' . esc_url($url) . '">' . esc_html($carousel ? __('Les artikkelen', 'reginor-lite') : __('Les hele svaret', 'reginor-lite')) . '<span class="screen-reader-text">: ' . esc_html($title) . '</span> →</a></div></article></li>';
            }
            echo '</ul></section>';
        }
        if (array_filter($related)) { echo '</div>'; }
        echo '</article>'; return (string) ob_get_clean();
    }
}
