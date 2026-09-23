<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Frontend\PublicSite;

/** One cache policy for course HTML, embeds, AJAX and REST (including errors). */
final class CachePolicy
{
    private static bool $active = false;

    public static function headers(): array
    {
        return ['Cache-Control' => 'no-store, no-cache, private, max-age=0, must-revalidate',
            'CDN-Cache-Control' => 'no-store', 'Cloudflare-CDN-Cache-Control' => 'no-store',
            'Surrogate-Control' => 'no-store', 'X-LiteSpeed-Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache', 'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT'];
    }

    public static function disable(): void
    {
        self::$active = true;
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        do_action('litespeed_control_set_nocache', 'RegiNor dynamic course response');
        if (!headers_sent()) {
            nocache_headers();
            header_remove('ETag'); header_remove('Last-Modified');
            foreach (self::headers() as $name => $value) { header($name . ': ' . $value); }
        }
    }

    private static function courseRequest(): bool
    {
        if (self::$active || get_query_var('rnl_route_index') || get_query_var('rnl_route_period') || isset($_GET['rnl_calendar']) || PublicSite::onPage()) { return true; }
        global $wp_query;
        // Includes archives/search/feed and secondary pages containing a shortcode or synced block.
        foreach ($wp_query->posts ?? [] as $post) {
            if ($post instanceof \WP_Post && PublicSite::containsCourses($post->post_content)) { return true; }
        }
        return (bool) apply_filters('rnl_no_cache_page', false);
    }

    public static function response(\WP_REST_Response $response): \WP_REST_Response
    {
        self::disable();
        $headers = array_filter($response->get_headers(), static fn ($name) => !in_array(strtolower($name), ['etag', 'last-modified'], true), ARRAY_FILTER_USE_KEY);
        $response->set_headers(array_merge($headers, self::headers()));
        return $response;
    }

    public static function boot(): void
    {
        add_action('init', static function (): void {
            $action = $_REQUEST['action'] ?? ''; $page = $_GET['page'] ?? '';
            if ((is_string($action) && str_starts_with($action, 'rnl_')) || (is_admin() && is_string($page) && (str_starts_with($page, 'rnl-') || str_starts_with($page, 'reginor-lite')))) { self::disable(); }
        }, 0);
        add_filter('wp_headers', static function (array $headers): array {
            if (!self::courseRequest()) { return $headers; }
            self::disable();
            unset($headers['ETag'], $headers['Last-Modified']);
            return array_merge($headers, self::headers());
        }, PHP_INT_MAX);
        add_action('template_redirect', static function (): void { if (self::courseRequest()) { self::disable(); } }, -100);
        // Avada templates/widgets can execute shortcodes absent from post_content.
        // Delay theme output so their render callback can still send the same headers.
        add_filter('template_include', static function ($template) {
            if (PublicSite::pageId() && !headers_sent()) { ob_start(); }
            return $template;
        }, PHP_INT_MAX);
        add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
            if (str_starts_with($request->get_route(), '/reginor/')) { self::disable(); }
            return $result;
        }, -100, 3);
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if ((self::$active || str_starts_with($request->get_route(), '/reginor/')) && $response instanceof \WP_REST_Response) { return self::response($response); }
            return $response;
        }, PHP_INT_MAX, 3);
        add_filter('rest_pre_serve_request', static function ($served) { if (self::$active) { self::disable(); } return $served; }, PHP_INT_MAX);
    }
}
