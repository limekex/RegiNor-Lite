<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\WebIdentity;
use function RegiNor\Lite\translate as __;

final class SharingMetadata
{
    public static function boot(): void
    {
        add_filter('pre_get_document_title', static fn ($v) => self::data()['title'] ?? $v, 30);
        add_filter('wpseo_title', static fn ($v) => self::data()['title'] ?? $v, 30);
        add_filter('rank_math/frontend/title', static fn ($v) => self::data()['title'] ?? $v, 30);
        // These routes are virtual views of one WP page. Its stored SEO graph/OG image must not leak into every course.
        add_filter('wpseo_frontend_presenters', static fn ($v) => self::data() ? [] : $v, 99);
        add_action('template_redirect', static function (): void {
            if (!self::data()) { return; }
            remove_action('wp_head', 'rel_canonical'); remove_action('wp_head', 'wp_robots');
            remove_all_actions('rank_math/head');
        }, 20);
        add_action('wp_head', [self::class, 'head'], 5);
        add_filter('wpml_hreflangs', static fn ($v) => self::data() ? [] : $v);
        add_filter('wpseo_sitemap_index', [self::class, 'sitemapIndex']);
        add_filter('rank_math/sitemap/index', [self::class, 'sitemapIndex']);
        add_filter('robots_txt', static fn ($v) => PublicSite::pageId() ? $v . "\nSitemap: " . add_query_arg('rnl_sitemap', 'index', home_url('/')) . "\n" : $v);
        add_action('template_redirect', [self::class, 'sitemap'], -1);
    }
    public static function data(): ?array
    {
        if (is_404() || !PublicSite::onPage()) { return null; }
        $catalog = (new Catalog())->read(); $gid = PublicSite::integer('rnl_course'); $pid = PublicSite::integer('rnl_period');
        $g = $gid ? ($catalog['groups'][$gid] ?? null) : null;
        $pid = $g['period_id'] ?? $pid; $p = $pid ? ($catalog['periods'][$pid] ?? null) : null;
        if (!$p || ($gid && !$g)) { return null; }
        $entry = WebIdentity::entry($gid ?: $pid); $parent = WebIdentity::entry($pid);
        $title = $entry['title'] ?: ($g ? $g['title'] . ' · ' . $p['title'] : $p['title']);
        $description = $entry['description'] ?: ($g ? implode(' ', array_filter([$g['description'], $g['venue'], $g['address']])) : implode(' · ', array_column(array_filter($catalog['groups'], static fn ($v) => $v['period_id'] === $pid), 'title')));
        $image = null;
        foreach ([$entry['image_id'], $parent['image_id'], (int) get_option('rnl_sharing_image', 0)] as $id) { $image = WebIdentity::image($id); if ($image) { break; } }
        return ['title' => self::plain($title . ' – ' . get_bloginfo('name'), 220), 'description' => self::plain($description, 180),
            'url' => PublicSite::url($gid, $gid ? [] : ['rnl_period' => $pid]), 'image' => $image, 'period' => $pid, 'group' => $gid,
            'locale' => get_locale(), 'filtered' => (bool) array_intersect(['rnl_day', 'rnl_level', 'rnl_view'], array_keys($_GET))];
    }
    public static function plain(string $text, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', \RegiNor\Lite\Infrastructure\RichText::plain($text)));
        return wp_html_excerpt($text, $length, '…');
    }
    public static function head(): void
    {
        $v = self::data(); if (!$v) { return; }
        $robots = apply_filters('wp_robots', []);
        if ($v['filtered'] || !get_option('blog_public')) { $robots['noindex'] = true; unset($robots['index']); }
        $robotText = []; foreach ($robots as $key => $value) { if ($value) { $robotText[] = is_string($value) ? $key . ':' . $value : $key; } }
        if (!$robotText) { $robotText = ['index', 'follow']; }
        echo '<link rel="canonical" href="' . esc_url($v['url']) . '">' . "\n";
        foreach (['description' => $v['description'], 'robots' => implode(', ', $robotText), 'twitter:card' => $v['image'] ? 'summary_large_image' : 'summary', 'twitter:title' => $v['title'], 'twitter:description' => $v['description']] as $name => $text) { self::tag('name', $name, $text); }
        foreach (['og:type' => 'website', 'og:title' => $v['title'], 'og:description' => $v['description'], 'og:url' => $v['url'], 'og:locale' => $v['locale'], 'og:site_name' => get_bloginfo('name')] as $name => $text) { self::tag('property', $name, $text); }
        if ($v['image']) {
            foreach (['url' => '', 'width' => ':width', 'height' => ':height', 'alt' => ':alt'] as $key => $suffix) { self::tag('property', 'og:image' . $suffix, (string) $v['image'][$key]); }
            self::tag('name', 'twitter:image', $v['image']['url']); self::tag('name', 'twitter:image:alt', $v['image']['alt']);
        }
        if (PublicRoutes::enabled()) {
            foreach (WebIdentity::languages() as $code => $language) {
                echo '<link rel="alternate" hreflang="' . esc_attr(str_replace('_', '-', $language['default_locale'] ?? $code)) . '" href="' . esc_url(PublicRoutes::url($v['period'], $v['group'], $code)) . '">' . "\n";
            }
        }
    }
    private static function tag(string $attribute, string $name, string $value): void
    {
        if ($value !== '') { echo '<meta ' . $attribute . '="' . esc_attr($name) . '" content="' . esc_attr($value) . '">' . "\n"; }
    }
    public static function sitemapIndex(string $xml): string
    {
        if (PublicSite::pageId()) {
            for ($i = 1, $pages = (new CourseSitemap())->get_max_num_pages(); $i <= $pages; $i++) { $xml .= '<sitemap><loc>' . esc_xml(add_query_arg('rnl_sitemap', $i, home_url('/'))) . '</loc></sitemap>'; }
        }
        return $xml;
    }
    public static function sitemap(): void
    {
        if (!isset($_GET['rnl_sitemap'])) { return; }
        PublicSite::noCache(); $value = $_GET['rnl_sitemap'];
        if (!is_string($value) || ($value !== 'index' && !preg_match('/^[1-9][0-9]{0,5}$/D', $value))) { status_header(404); exit; }
        $provider = new CourseSitemap(); $pages = $provider->get_max_num_pages();
        if ($value !== 'index' && (int) $value > $pages) { status_header(404); exit; }
        header('Content-Type: application/xml; charset=UTF-8'); header('X-Robots-Tag: noindex');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        if ($value === 'index') {
            echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            for ($i = 1; $i <= $pages; $i++) { echo '<sitemap><loc>' . esc_xml(add_query_arg('rnl_sitemap', $i, home_url('/'))) . '</loc></sitemap>'; }
            echo '</sitemapindex>';
        } else {
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($provider->get_url_list((int) $value) as $entry) { echo '<url><loc>' . esc_xml($entry['loc']) . '</loc></url>'; }
            echo '</urlset>';
        }
        exit;
    }
}
