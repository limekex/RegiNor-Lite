<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Domain\Analytics\JourneyEvent;
use RegiNor\Lite\Infrastructure\JourneyStore;
use function RegiNor\Lite\translate as __;

final class JourneyTracking
{
    public static function boot(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('rest_api_init', static function (): void {
            register_rest_route('reginor/v1', '/journey', ['methods' => 'POST', 'permission_callback' => [self::class, 'permission'], 'callback' => [self::class, 'receive']]);
        });
        add_action('rnl_journey_cleanup', [JourneyStore::class, 'purge']);
        add_action('init', static function (): void {
            if (get_option('rnl_journey_schema') === '1' && !wp_next_scheduled('rnl_journey_cleanup')) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'rnl_journey_cleanup'); }
        });
    }
    public static function assets(): void
    {
        if (!get_option('rnl_journey_enabled', false) || is_user_logged_in() || is_preview() || is_404()
            || (is_singular() && (get_post_status() !== 'publish' || post_password_required()))) { return; }
        wp_enqueue_script('rnl-journey', plugins_url('assets/journey.js', dirname(__DIR__, 2) . '/reginor-lite.php'), [], (string) filemtime(dirname(__DIR__, 2) . '/assets/journey.js'), true);
        wp_add_inline_script('rnl-journey', 'window.rnlJourneyConfig=' . wp_json_encode(['endpoint' => rest_url('reginor/v1/journey'),
            'page_id' => is_singular() ? get_queried_object_id() : 0]) . ';', 'before');
    }
    private static function origin(string $url): string
    {
        $parts = wp_parse_url($url);
        return is_array($parts) && isset($parts['scheme'], $parts['host']) ? strtolower($parts['scheme'] . '://' . $parts['host']) . ':' . ($parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80)) : '';
    }
    private static function consent(string $category): bool
    {
        // Keep the existing Complianz requirement; a permissive API fallback is not consent.
        return function_exists('cmplz_has_consent') && cmplz_has_consent($category) === true
            && (!function_exists('wp_has_consent') || wp_has_consent($category) === true);
    }
    public static function permission(\WP_REST_Request $request): bool|\WP_Error
    {
        if (get_option('rnl_journey_schema') !== '1' || strlen($request->get_body()) > 8192
            || $request->get_header('x-rnl-journey') !== '1' || self::origin($request->get_header('origin')) !== self::origin(home_url())
            || !str_starts_with(strtolower($request->get_header('content-type')), 'application/json')) { return new \WP_Error('rnl_journey_forbidden', __('Du har ikke tilgang til denne målingen.', 'reginor-lite'), ['status' => 403]); }
        $data = $request->get_json_params();
        if (is_array($data) && ($data['operation'] ?? '') === 'forget' && JourneyEvent::uuid($data['journey_id'] ?? null)) { return true; }
        if (!get_option('rnl_journey_enabled', false) || is_user_logged_in() || !self::consent('statistics')) { return new \WP_Error('rnl_journey_no_consent', __('Målingen krever samtykke til statistikk.', 'reginor-lite'), ['status' => 403]); }
        return true;
    }
    public static function receive(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $permission = self::permission($request);
        if (is_wp_error($permission)) { return $permission; }
        $data = $request->get_json_params();
        if (is_array($data) && ($data['operation'] ?? '') === 'forget') {
            try { JourneyStore::forget($data['journey_id']); }
            catch (\RuntimeException) { return new \WP_Error('rnl_journey_busy', __('Målingen er midlertidig utilgjengelig.', 'reginor-lite'), ['status' => 503]); }
        } else {
            try { $event = JourneyEvent::validate(is_array($data) ? $data : [], self::consent('marketing')); }
            catch (\InvalidArgumentException) { return new \WP_Error('rnl_journey_invalid', __('Målehendelsen har ugyldig format.', 'reginor-lite'), ['status' => 400]); }
            if (in_array($event['stage'], ['course_view', 'letsreg_click'], true)) {
                $group = (new Catalog())->read()['groups'][$event['course_id']] ?? null;
                if (!$group || ($event['stage'] === 'letsreg_click' && $group['registration_url'] === '')) { return new \WP_Error('rnl_journey_course', __('Kurset eller påmeldingen er ikke offentlig tilgjengelig.', 'reginor-lite'), ['status' => 400]); }
            } else { $event['course_id'] = 0; }
            if ($event['page_id'] && (get_post_status($event['page_id']) !== 'publish' || get_post_field('post_password', $event['page_id']) !== '' || !is_post_type_viewable(get_post_type($event['page_id'])))) { $event['page_id'] = 0; }
            if (!JourneyStore::record($event)) { return new \WP_Error('rnl_journey_limit', __('Målingen er midlertidig utilgjengelig.', 'reginor-lite'), ['status' => 429]); }
        }
        $response = new \WP_REST_Response(null, 204); $response->header('Cache-Control', 'no-store'); return $response;
    }
}
