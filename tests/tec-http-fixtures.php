<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\TecBridge;
use RegiNor\Lite\Frontend\PublicSite;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || !TecBridge::available()) throw new RuntimeException('Kun lokalt TEC.');
$key = 'rnl_tec_http_test'; $old = get_option($key);
wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);
if (($args[0] ?? '') === 'cleanup') {
    if (!$old) return;
    foreach (TecBridge::owned() as $id => $owner) if ($owner === $old['period']) wp_delete_post($id, true);
    foreach (array_reverse($old['created']) as $id) wp_delete_post($id, true);
    if ($old['old_page'] === null) delete_option('rnl_course_page_id'); else update_option('rnl_course_page_id', $old['old_page']);
    delete_option($key); return;
}
if (($args[0] ?? '') === 'expire') {
    if (!$old) throw new RuntimeException('Fixture missing');
    $state = get_post_meta($old['period'], ContentTypes::META, true);
    $state['data']['visible_until'] = gmdate('Y-m-d\TH:i:s\Z', time() - 1); update_post_meta($old['period'], ContentTypes::META, $state); return;
}
if ($old) throw new RuntimeException('Testfixture already exists; cleanup required.');
$state = ['created' => [], 'old_page' => get_option('rnl_course_page_id', null), 'period' => 0]; update_option($key, $state, false);
$track = static function ($id) use (&$state, $key) { $state['created'][] = $id; update_option($key, $state, false); return $id; };
$repo = new CourseRepository();
$venue = $track($repo->create('venue', ['title' => 'TEC HTTP teststed', 'address' => 'Testgate']));
$room = $track($repo->create('room', ['title' => 'TEC HTTP sal', 'venue_id' => $venue]));
$course = $track($repo->create('course', ['title' => 'TEC HTTP kurs', 'description' => 'Test', 'level_description' => 'Test', 'dance_style' => 'Test', 'partner_info' => 'Test']));
$date = new DateTimeImmutable('+7 days', new DateTimeZone('Europe/Oslo')); $end = $date->modify('+20 days')->format('Y-m-d');
$period = $track($repo->create('period', ['title' => 'RNLTECHTTP-' . wp_generate_uuid4(), 'timezone' => 'Europe/Oslo', 'start_date' => $date->format('Y-m-d'), 'end_date' => $end,
    'default_session_count' => 2, 'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person',
    'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'visible_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 50 * DAY_IN_SECONDS),
    'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 40 * DAY_IN_SECONDS),
    'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => [], 'calendar_enabled' => true]));
$state['period'] = $period; update_option($key, $state, false);
$group = $track($repo->createGroup($period, $course, ['weekday' => (int) $date->format('N'), 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'later']));
$repo->confirm($repo->previewGroup($group, 1, []));
$page = $track(wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TEC HTTP testside', 'post_content' => '[reginor_courses]'])); update_option('rnl_course_page_id', $page);
// Force the stage failure mode during initial publish; public REST/calendar/ICS must
// work after the direct TEC API repairs this same draft.
$failedOrm = static function ($callback) use ($period) {
    return static function ($postarr) use ($callback, $period) {
        return TecBridge::owner((int) ($postarr['ID'] ?? 0)) === $period ? null : $callback($postarr);
    };
};
add_filter('tribe_repository_events_update_callback', $failedOrm);
try { $repo->publish($repo->previewPublication($period, 1, true)); TecBridge::sync(); }
finally { remove_filter('tribe_repository_events_update_callback', $failedOrm); }
$event = (int) get_post_meta($period, TecBridge::EVENT, true); if (!$event) throw new RuntimeException('TEC projection failed');
if (get_post_status($event) !== 'publish' || get_post_meta($period, TecBridge::ERROR, true)) throw new RuntimeException('TEC worker did not finish; HTTP must not repair it synchronously.');
WP_CLI::line(wp_json_encode(['period' => $period, 'event' => $event, 'title' => $repo->get($period)['data']['title'], 'url' => PublicSite::url(null, ['rnl_period' => $period]),
    'calendar' => add_query_arg(['eventDisplay' => 'month', 'eventDate' => $date->format('Y-m')], tribe_get_events_link()),
    'start' => $date->format('Y-m-d'), 'end' => $end]));
