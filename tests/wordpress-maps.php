<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\Appearance;
use RegiNor\Lite\Infrastructure\VenueMap;
use RegiNor\Lite\Admin\CourseActions;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Admin\AppearancePage;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') throw new RuntimeException('Kun lokalt WordPress.');
$oldUser = get_current_user_id(); $oldGet = $_GET; $ids = []; $checks = 0; $queries = [];
$oldLock = get_option('rnl_map_search_lock', null);
$assert = static function ($ok, $message) use (&$checks) { $checks++; if (!$ok) throw new RuntimeException($message); };
$repo = new CourseRepository(); $calls = 0;
$mock = static function ($response, $args, $url) use (&$calls) {
    if (!str_starts_with($url, 'https://nominatim.openstreetmap.org/search?')) throw new RuntimeException('Unexpected HTTP request');
    $calls++;
    if (!str_contains($args['user-agent'], 'RegiNor-Lite/')) throw new RuntimeException('Missing service identification');
    return ['headers' => [], 'response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode([['display_name' => '<b>Testgate</b>', 'lat' => '59.91', 'lon' => '10.75'], ['display_name' => 'Invalid', 'lat' => '200', 'lon' => '0']])];
};
try {
    wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);
    $parsed = CourseActions::parse('venue', ['title' => 'Karttest', 'address' => 'Testgate', 'latitude' => '59,9139', 'longitude' => '10.7522'], []);
    $venue = $ids[] = $repo->create('venue', $parsed);
    $assert($repo->get($venue)['data']['latitude'] === 59.9139, 'Koordinater lagres ikke riktig.');
    foreach ([['latitude' => '91', 'longitude' => '0'], ['latitude' => '0', 'longitude' => '-181'], ['latitude' => '', 'longitude' => '10'], ['latitude' => 'oops', 'longitude' => '0']] as $invalid) {
        try { CourseActions::parse('venue', ['title' => 'Test', 'address' => 'Test'] + $invalid, []); $assert(false, 'Ugyldig koordinat godtatt.'); }
        catch (InvalidArgumentException) { $assert(true, 'Ugyldig koordinat avvist.'); }
    }
    $blank = CourseActions::parse('venue', ['title' => 'Test', 'address' => 'Test', 'latitude' => '', 'longitude' => ''], []);
    $assert($blank['latitude'] === null && $blank['longitude'] === null, 'Kartpunkt kan ikke tømmes.');
    $zero = CourseActions::parse('venue', ['title' => 'Test', 'address' => 'Test', 'latitude' => '0', 'longitude' => '0'], []);
    ob_start(); VenueMap::display($zero); $html = ob_get_clean(); $assert(str_contains($html, 'data-latitude="0"'), 'Nullmeridian/ekvator regnes som manglende punkt.');
    $assert(!str_contains($html, 'data-map-open') && str_contains($html, 'data-map-canvas') && str_contains($html, 'data-map-fallback'), 'Offentlig kart krever fortsatt klikk eller mangler reservelenke.');
    ob_start(); VenueMap::display($blank); $assert(ob_get_clean() === '', 'Tomt kartpunkt får kart.');
    $room = $ids[] = $repo->create('room', CourseActions::parse('room', ['title' => 'Karttestsalen', 'venue_id' => (string) $venue, 'appearance_custom' => '1', 'appearance_color' => '#123456', 'appearance_source' => 'wp:primary', 'appearance_alpha' => '0', 'appearance_tone' => 'dark'], []));
    $assert(str_contains(Appearance::roomStyle($room), '0%,transparent)'), 'Salens alpha mistes.');
    $state = $repo->get($room); $data = $state['data']; $data['appearance_custom'] = false; $repo->update($room, $state['version'], $data);
    $assert(Appearance::roomStyle($room) === '', 'Sal kan ikke arve standard igjen.');
    foreach ([['days' => [8 => []]], ['days' => [1 => ['enabled' => 1, 'color' => '#112233', 'alpha' => 101]]], ['days' => [1 => ['enabled' => 1, 'source' => 'wp:bad;inject']]]] as $invalid) {
        try { Appearance::validate($invalid); $assert(false, 'Ugyldig dagvalg godtatt.'); } catch (InvalidArgumentException) { $assert(true, 'Ugyldig dagvalg avvist.'); }
    }
    $assert(Appearance::validate(['days' => [1 => ['enabled' => '0']]])['days'] === [], 'Fravalgt dag beholder overstyring.');
    $_GET = ['page' => 'rnl-resources']; ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'data-rnl-map-picker') && str_contains($html, 'data[latitude]') && str_contains($html, 'Salens farge i kalenderen'), 'Sted/sal-skjema mangler kart/farge.');
    $dom = new DOMDocument(); @$dom->loadHTML($html); $xp = new DOMXPath($dom); $seen = [];
    foreach ($xp->query('//*[starts-with(@id, "rnl-")]') as $node) { $id = $node->getAttribute('id'); $assert(!isset($seen[$id]), 'Duplikat felt-ID: ' . $id); $seen[$id] = true; }
    ob_start(); AppearancePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'appearance[days][1][color]') && str_contains($html, 'appearance[days][7][enabled]'), 'Dagfeltenes navn mangler eller er feil.');
    add_filter('pre_http_request', $mock, 10, 3); delete_option('rnl_map_search_lock');
    $queries[] = $q = 'Syntetisk karttest ' . wp_generate_uuid4();
    $results = VenueMap::lookup($q); $assert(count($results) === 1 && $results[0]['label'] === 'Testgate', 'Adressesvar blir ikke avgrenset/sanert.');
    $assert(VenueMap::lookup($q) === $results && $calls === 1, 'Identisk søk skal bruke cache.');
    $queries[] = $q2 = $q . ' neste';
    try { VenueMap::lookup($q2); $assert(false, 'Søk overskrider rategrensen.'); } catch (RuntimeException $e) { $assert($e->getCode() === 429 && $calls === 1, 'Rategrensen håndheves ikke.'); }
    update_option('rnl_map_search_lock', time() - 5);
    $assert(count(VenueMap::lookup($q2)) === 1 && $calls === 2, 'Utløpt rategrense blokkerer søk.');
    remove_filter('pre_http_request', $mock, 10);
} finally {
    remove_filter('pre_http_request', $mock, 10); foreach ($queries as $q) delete_transient('rnl_map_' . md5($q));
    if ($oldLock === null) delete_option('rnl_map_search_lock'); else update_option('rnl_map_search_lock', $oldLock);
    foreach (array_reverse($ids) as $id) wp_delete_post($id, true);
    wp_set_current_user($oldUser); $_GET = $oldGet;
}
WP_CLI::success("$checks map/resource checks passed; fixtures removed; external search mocked.");
