<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Optional public venue coordinates; no visitor location or marketing identifiers. */
final class VenueMap
{
    public static function boot(): void
    {
        add_action('admin_enqueue_scripts', static function (): void { if (($_GET['page'] ?? '') === 'rnl-resources') { self::assets(); } });
        add_action('wp_enqueue_scripts', static function (): void {
            // Course identity also comes from pretty URLs, not only query parameters.
            if (!is_404() && \RegiNor\Lite\Frontend\PublicSite::onPage() && \RegiNor\Lite\Frontend\PublicSite::integer('rnl_course')) { self::assets(); }
        });
        add_action('wp_ajax_rnl_map_search', [self::class, 'search']);
    }
    public static function assets(): void
    {
        $file = dirname(__DIR__, 2) . '/reginor-lite.php';
        wp_enqueue_style('rnl-leaflet', plugins_url('assets/vendor/leaflet/leaflet.css', $file), [], '1.9.4');
        wp_enqueue_script('rnl-leaflet', plugins_url('assets/vendor/leaflet/leaflet.js', $file), [], '1.9.4', true);
        wp_enqueue_script('rnl-map', plugins_url('assets/map.js', $file), ['rnl-leaflet', 'wp-i18n'], (string) filemtime(dirname(__DIR__, 2) . '/assets/map.js'), true);
        wp_set_script_translations('rnl-map', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
    }
    public static function search(): void
    {
        if (!current_user_can('manage_options')) { wp_send_json_error(__('Du har ikke tilgang.', 'reginor-lite'), 403); }
        check_ajax_referer('rnl_map_search', 'nonce');
        $query = $_POST['query'] ?? '';
        if (!is_string($query)) { wp_send_json_error(__('Skriv inn en adresse.', 'reginor-lite'), 400); }
        $query = sanitize_text_field(wp_unslash($query));
        if (strlen($query) < 3 || strlen($query) > 250) { wp_send_json_error(__('Skriv en adresse med mellom 3 og 250 tegn.', 'reginor-lite'), 400); }
        try { wp_send_json_success(self::lookup($query)); }
        catch (\RuntimeException $e) { wp_send_json_error($e->getMessage(), $e->getCode() ?: 503); }
    }
    public static function lookup(string $query): array
    {
        $key = 'rnl_map_' . md5($query); $cached = get_transient($key);
        if (is_array($cached)) { return $cached; }
        // Atomic, site-wide two-second reservation: no autocomplete or concurrent bursts.
        global $wpdb;
        $lock = 'rnl_map_search_lock';
        $reservation = (int) ceil(microtime(true)) + 2;
        $reserved = add_option($lock, $reservation, '', false);
        if (!$reserved) {
            $reserved = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) <= %d", (string) $reservation, $lock, time())) === 1;
            wp_cache_delete($lock, 'options');
        }
        if (!$reserved) { throw new \RuntimeException(__('Vent et par sekunder før du søker igjen.', 'reginor-lite'), 429); }
        $endpoint = apply_filters('rnl_map_search_endpoint', 'https://nominatim.openstreetmap.org/search');
        $response = wp_safe_remote_get(add_query_arg(['q' => $query, 'format' => 'jsonv2', 'limit' => 5], $endpoint), ['timeout' => 10, 'user-agent' => 'RegiNor-Lite/0.1.0 (' . home_url('/') . ')', 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { throw new \RuntimeException(__('Adressesøket svarer ikke nå. Prøv igjen, eller velg stedet direkte i kartet.', 'reginor-lite')); }
        $rows = json_decode(wp_remote_retrieve_body($response), true); $results = [];
        if (!is_array($rows)) { throw new \RuntimeException(__('Adressesøket ga et ugyldig svar. Velg stedet i kartet.', 'reginor-lite')); }
        foreach (array_slice($rows, 0, 5) as $row) {
            if (!is_array($row) || !is_string($row['display_name'] ?? null) || !is_numeric($row['lat'] ?? null) || !is_numeric($row['lon'] ?? null)) { continue; }
            $lat = (float) $row['lat']; $lon = (float) $row['lon'];
            if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) { continue; }
            $results[] = ['label' => sanitize_text_field($row['display_name']), 'latitude' => $lat, 'longitude' => $lon];
        }
        set_transient($key, $results, DAY_IN_SECONDS);
        return $results;
    }
    public static function picker(array $data): void
    {
        echo '<section class="rnl-field-section" data-rnl-map-picker data-search-url="' . esc_url(admin_url('admin-ajax.php')) . '" data-nonce="' . esc_attr(wp_create_nonce('rnl_map_search')) . '"><h3>' . esc_html(__('Plasser kursstedet på kartet', 'reginor-lite')) . '</h3><p>' . esc_html(__('Søk etter gateadresse og by. Velg riktig treff, og flytt markøren til inngangen ved behov. Kartpunktet er valgfritt og lagres sammen med oppføringen.', 'reginor-lite')) . '</p><label>' . esc_html(__('Finn adresse', 'reginor-lite')) . '<input type="search" data-map-query value="' . esc_attr($data['address'] ?? '') . '" placeholder="' . esc_attr(__('Eksempel: Storgata 10, Oslo', 'reginor-lite')) . '"></label><button type="button" class="button" data-map-search hidden>' . esc_html(__('Søk i OpenStreetMap', 'reginor-lite')) . '</button><div data-map-results></div><p class="rnl-help">&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a></p><p data-map-status role="status" aria-live="polite"></p><button type="button" class="button" data-map-open hidden>' . esc_html(__('Åpne kartvelger', 'reginor-lite')) . '</button><div class="rnl-map" data-map-canvas hidden role="region" aria-label="' . esc_attr(__('Kart for valg av kurssted', 'reginor-lite')) . '"></div><button type="button" class="button" data-map-center hidden>' . esc_html(__('Bruk midten av kartet', 'reginor-lite')) . '</button><p class="rnl-help">' . esc_html(__('Med tastatur: flytt kartet med piltastene og velg «Bruk midten av kartet». Du kan også fylle inn koordinatene nedenfor. Kart og søk hentes fra OpenStreetMap når du åpner dem.', 'reginor-lite')) . '</p><details><summary>' . esc_html(__('Koordinater og fjerning av kartpunkt', 'reginor-lite')) . '</summary><div class="rnl-field-grid">';
        foreach (['latitude' => __('Breddegrad', 'reginor-lite'), 'longitude' => __('Lengdegrad', 'reginor-lite')] as $key => $label) {
            $limit = $key === 'latitude' ? 90 : 180;
            echo '<label>' . esc_html($label) . '<input type="number" step="any" min="-' . $limit . '" max="' . $limit . '" name="data[' . $key . ']" data-map-' . $key . ' value="' . esc_attr((string) ($data[$key] ?? '')) . '"></label>';
        }
        echo '</div><button type="button" class="button" data-map-clear hidden>' . esc_html(__('Fjern kartpunkt', 'reginor-lite')) . '</button><p class="rnl-help">' . esc_html(__('Tøm begge koordinatfeltene for å fjerne kartet. Adressen beholdes.', 'reginor-lite')) . '</p></details></section>';
    }
    public static function display(array $venue): void
    {
        if (($venue['latitude'] ?? null) === null || ($venue['longitude'] ?? null) === null) { return; }
        $lat = (float) $venue['latitude']; $lon = (float) $venue['longitude'];
        if (!is_finite($lat) || !is_finite($lon) || abs($lat) > 90 || abs($lon) > 180) { return; }
        $url = 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=17/' . $lat . '/' . $lon;
        echo '<div class="rnl-venue-map" data-rnl-venue-map data-latitude="' . esc_attr((string) $lat) . '" data-longitude="' . esc_attr((string) $lon) . '"><div class="rnl-map" data-map-canvas hidden role="region" aria-label="' . esc_attr(__('Kart til kursstedet', 'reginor-lite')) . '"></div><p class="rnl-help" data-map-fallback>' . esc_html(__('Hvis kartet ikke vises, kan du åpne kursstedet i OpenStreetMap nedenfor.', 'reginor-lite')) . '</p><p><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(__('Åpne kursstedet i OpenStreetMap', 'reginor-lite')) . '<span class="screen-reader-text"> ' . esc_html(__('(åpnes i ny fane)', 'reginor-lite')) . '</span></a></p></div>';
    }
}
