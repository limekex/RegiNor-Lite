<?php
use RegiNor\Lite\Infrastructure\JourneyStore;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Frontend\JourneyTracking;
use RegiNor\Lite\Admin\AnalyticsPage;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
if (function_exists('wp_has_consent')) { throw new RuntimeException('Kjør i isolert testmiljø uten ekte WP Consent API; denne testen bruker samtykkestub.'); }
if (function_exists('cmplz_has_consent')) { throw new RuntimeException('Kjør i isolert testmiljø uten ekte Complianz; denne testen bruker samtykkestub.'); }
if (!function_exists('cmplz_has_consent')) { function cmplz_has_consent($category) { return $GLOBALS['rnl_test_consent'][$category] ?? false; } }
$GLOBALS['rnl_test_consent'] = ['statistics' => false, 'marketing' => false];
$checks = 0; $created = []; $journeys = []; $oldEnabled = get_option('rnl_journey_enabled', null); $original = get_current_user_id();
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$assert = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) { throw new RuntimeException($message); } };
$clock = new class implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('-1 day'); } };
$repo = new CourseRepository($clock);
$call = static function (array $data, array $headers = []): WP_REST_Response {
    $r = new WP_REST_Request('POST', '/reginor/v1/journey');
    foreach (array_replace(['Origin' => home_url(), 'Content-Type' => 'application/json', 'X-RNL-Journey' => '1'], $headers) as $key => $value) { $r->set_header($key, $value); }
    $r->set_body(wp_json_encode($data)); return rest_do_request($r);
};
try {
    wp_set_current_user($admin); JourneyStore::install(); update_option('rnl_journey_enabled', true, false);
    $roomVenue = $created[] = $repo->create('venue', ['title' => 'Analytics teststed', 'address' => 'Kun syntetiske testdata']);
    $room = $created[] = $repo->create('room', ['title' => 'Analytics testsal', 'venue_id' => $roomVenue]);
    $course = $created[] = $repo->create('course', ['title' => 'Analytics testkurs', 'description' => 'Test', 'audience' => 'beginner', 'level_description' => 'Test', 'dance_style' => 'Salsa', 'partner_info' => 'Test']);
    $date = new DateTimeImmutable('+2 days', new DateTimeZone('Europe/Oslo'));
    $period = $created[] = $repo->create('period', ['title' => 'Analytics testperiode', 'timezone' => 'Europe/Oslo', 'start_date' => $date->format('Y-m-d'),
        'default_session_count' => 2, 'default_room_id' => $room, 'default_price_minor' => 120000, 'default_price_basis' => 'person',
        'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'visible_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * DAY_IN_SECONDS),
        'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * DAY_IN_SECONDS),
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []]);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => (int) $date->format('N'), 'start_time' => '18:15', 'end_time' => '19:15', 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/synthetic-only']);
    $repo->confirm($repo->previewGroup($group, 1, [])); $repo->publish($repo->previewPublication($period, 1, true));
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Privat måletest']);
    $journey = $journeys[] = wp_generate_uuid4();
    $data = ['event_id' => wp_generate_uuid4(), 'journey_id' => $journey, 'stage' => 'landing', 'course_id' => $group, 'page_id' => $page, 'statistics' => true, 'marketing' => true,
        'campaign' => ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'Syntetisk måletest'], 'click_ids' => ['gclid' => 'synthetic-click'], 'email' => 'ignored@example.com'];
    wp_set_current_user(0);
    $assert($call($data)->get_status() === 403, 'Måling uten samtykke tillates.');
    $GLOBALS['rnl_test_consent']['statistics'] = true;
    $assert($call($data, ['Origin' => 'https://foreign.example'])->get_status() === 403, 'Ekstern origin tillates.');
    $assert($call($data, ['X-RNL-Journey' => ''])->get_status() === 403, 'Manglende header tillates.');
    $assert($call($data, ['Content-Type' => 'text/plain'])->get_status() === 403, 'Enkel kryssdomeneforespørsel tillates.');
    update_option('rnl_journey_enabled', false); $assert($call($data)->get_status() === 403, 'Avslått innsamling tillates.'); update_option('rnl_journey_enabled', true);
    $assert($call($data)->get_status() === 204, 'Samtykket måling feilet.');
    $assert($call($data)->get_status() === 204, 'Idempotent gjentakelse feilet.');
    global $wpdb; $table = JourneyStore::table(); $hash = JourneyStore::hash($journey);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE journey=%s", $hash), ARRAY_A);
    $assert(count($rows) === 1 && $rows[0]['journey'] !== $journey, 'Duplikat eller rå besøks-ID lagres.');
    $assert((int) $rows[0]['course_id'] === 0 && (int) $rows[0]['page_id'] === 0, 'Privat side eller påstått kurs på landing lagres.');
    $assert(json_decode($rows[0]['detail'], true)['click_ids'] === [], 'Annonse-ID uten serverkontrollert markedsføringssamtykke lagres.');
    $assert(!str_contains($rows[0]['detail'], 'ignored@example.com'), 'Ukontrollerte felt lagres.');
    $assert($call(array_replace($data, ['event_id' => wp_generate_uuid4(), 'stage' => 'purchase']))->get_status() === 400, 'Nettleser kan påstå kjøp.');
    $assert($call(array_replace($data, ['event_id' => wp_generate_uuid4(), 'stage' => 'course_view', 'course_id' => $page]))->get_status() === 400, 'Ikke-offentlig kurs måles.');
    $GLOBALS['rnl_test_consent']['marketing'] = true;
    foreach (['course_view', 'letsreg_click'] as $stage) {
        $event = array_replace($data, ['event_id' => wp_generate_uuid4(), 'stage' => $stage]);
        $assert($call($event)->get_status() === 204, 'Offentlig kurshendelse feilet.');
        $detail = $wpdb->get_var($wpdb->prepare("SELECT detail FROM $table WHERE event_id=%s", $event['event_id']));
        $assert(json_decode($detail, true)['click_ids']['gclid'] === 'synthetic-click', 'Annonse-ID med begge samtykker mangler.');
    }
    try { JourneyStore::report(); $assert(false, 'Anonym kan lese rapport.'); } catch (RuntimeException $e) { $assert($e->getCode() === 403, 'Feil tilgangsfeil.'); }
    wp_set_current_user($admin); $report = JourneyStore::report();
    $courseRows = array_values(array_filter($report['courses'], static fn ($r) => (int) $r['course_id'] === $group));
    $assert(count($courseRows) === 1 && (int) $courseRows[0]['views'] === 1 && (int) $courseRows[0]['clicks'] === 1, 'Kursrapport teller feil.');
    ob_start(); AnalyticsPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Kan ikke knyttes til besøk med dagens LetsReg-støtte') && str_contains($html, 'Syntetisk måletest'), 'Rapport mangler kampanje/avgrensning.');
    $req = new WP_REST_Request('POST', '/reginor/v1/journey'); foreach (['Origin' => home_url(), 'Content-Type' => 'application/json', 'X-RNL-Journey' => '1'] as $k => $v) $req->set_header($k, $v); $req->set_body(wp_json_encode($data));
    $assert(is_wp_error(JourneyTracking::permission($req)), 'Innlogget admin tillates av målepolicy.');
    wp_set_current_user(0); $GLOBALS['rnl_test_consent'] = ['statistics' => false, 'marketing' => false]; update_option('rnl_journey_enabled', false);
    $assert($call(['operation' => 'forget', 'journey_id' => $journey])->get_status() === 204, 'Tilbaketrekking krever fortsatt samtykke eller påslått måling.');
    $assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE journey=%s", $hash)) === 0, 'Tilbaketrekking slettet ikke egne data.');
    update_option('rnl_journey_enabled', true); $GLOBALS['rnl_test_consent']['statistics'] = true;
    $assert($call(array_replace($data, ['event_id' => wp_generate_uuid4()]))->get_status() === 429, 'Forsinket hendelse gjenoppretter trukket besøk.');
    $oldJourney = $journeys[] = wp_generate_uuid4(); $old = array_replace($data, ['event_id' => wp_generate_uuid4(), 'journey_id' => $oldJourney]);
    $assert($call($old)->get_status() === 204, 'Nytt uavhengig besøk ble blokkert.');
    $wpdb->update($table, ['created_at' => gmdate('Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS)], ['event_id' => $old['event_id']]); JourneyStore::purge();
    $assert(!$wpdb->get_var($wpdb->prepare("SELECT event_id FROM $table WHERE event_id=%s", $old['event_id'])), 'Gamle måledata ble ikke slettet.');
    // Existing checks above exercise Complianz without WP Consent API installed.
    if (!function_exists('wp_has_consent')) {
        function wp_has_consent($category) { return $GLOBALS['rnl_test_wp_consent'][$category] ?? false; }
    }
    $GLOBALS['rnl_test_consent'] = ['statistics' => true, 'marketing' => true];
    $GLOBALS['rnl_test_wp_consent'] = ['statistics' => false, 'marketing' => true];
    $apiJourney = $journeys[] = wp_generate_uuid4();
    $apiEvent = array_replace($data, ['event_id' => wp_generate_uuid4(), 'journey_id' => $apiJourney, 'stage' => 'course_view']);
    $assert($call($apiEvent)->get_status() === 403, 'Consent API-avslag overstyres av Complianz på server.');
    $assert(!$wpdb->get_var($wpdb->prepare("SELECT event_id FROM $table WHERE event_id=%s", $apiEvent['event_id'])), 'Avvist Consent API-hendelse ble lagret.');
    $GLOBALS['rnl_test_wp_consent'] = ['statistics' => true, 'marketing' => false];
    $assert($call($apiEvent)->get_status() === 204, 'Felles statistikksamtykke blir blokkert.');
    $apiDetail = json_decode($wpdb->get_var($wpdb->prepare("SELECT detail FROM $table WHERE event_id=%s", $apiEvent['event_id'])), true);
    $assert($apiDetail['click_ids'] === [], 'Consent API-avslag for markedsføring tillater annonse-ID.');
    $GLOBALS['rnl_test_wp_consent']['marketing'] = true;
    $apiEvent['event_id'] = wp_generate_uuid4();
    $assert($call($apiEvent)->get_status() === 204, 'Felles markedsføringssamtykke blir blokkert.');
    $apiDetail = json_decode($wpdb->get_var($wpdb->prepare("SELECT detail FROM $table WHERE event_id=%s", $apiEvent['event_id'])), true);
    $assert($apiDetail['click_ids']['gclid'] === 'synthetic-click', 'Annonse-ID mangler når begge API-er tillater den.');
    $GLOBALS['rnl_test_consent']['statistics'] = false;
    $assert($call($apiEvent)->get_status() === 403, 'Consent API alene overstyrer manglende Complianz-samtykke.');
    $GLOBALS['rnl_test_wp_consent']['statistics'] = false;
    $assert($call(['operation' => 'forget', 'journey_id' => $apiJourney])->get_status() === 204, 'Consent API-avslag blokkerer sletting ved tilbaketrekking.');
    $assert((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE journey=%s", JourneyStore::hash($apiJourney))) === 0, 'Consent API-tilbaketrekking beholder egne måledata.');
    $tooLarge = array_replace($data, ['ignored' => str_repeat('x', 8192)]); $assert($call($tooLarge)->get_status() === 403, 'For stor nyttelast ble tillatt.');
} finally {
    wp_set_current_user($admin); foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    foreach ($journeys as $id) { $wpdb->delete(JourneyStore::table(), ['journey' => JourneyStore::hash($id)]); delete_transient('rnl_revoked_' . JourneyStore::hash($id)); }
    if ($oldEnabled === null) delete_option('rnl_journey_enabled'); else update_option('rnl_journey_enabled', $oldEnabled);
    wp_set_current_user($original); unset($GLOBALS['rnl_test_consent'], $GLOBALS['rnl_test_wp_consent']);
}
WP_CLI::success("$checks analytics checks passed (Complianz and WP Consent API stubs).");
