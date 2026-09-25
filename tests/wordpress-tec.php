<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\TecBridge;
use RegiNor\Lite\Infrastructure\TecDefaults;
use RegiNor\Lite\Infrastructure\TecSyncQueue;
use RegiNor\Lite\Admin\CalendarSettings;
use RegiNor\Lite\Frontend\PublicSite;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local' || !TecBridge::available()) throw new RuntimeException('Krever lokalt TEC-miljø.');
$oldUser = get_current_user_id(); $oldPage = get_option('rnl_course_page_id', null); $created = []; $checks = 0; $repo = new CourseRepository();
$oldDefaults = get_option(TecDefaults::OPTION, null); $terms = []; $uploads = []; $normalizeOccurrence = null; $denyLock = null; $ormCallback = null; $rejectWrite = null; $countUpdate = null; $emptyQuery = null;
$oldDateFormat = tribe_get_option('datepickerFormat'); $writeMode = 'no_write'; $oldSyncStatus = get_option(TecSyncQueue::STATUS, null);
$assert = static function ($ok, $message) use (&$checks) { $checks++; if (!$ok) throw new RuntimeException($message); };
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$savePeriod = static function (int $id, array $changes) use ($repo): void { $state = $repo->get($id); $repo->update($id, $state['version'], array_replace($state['data'], $changes)); };
$draft = static function (int $id) use ($repo): void { $repo->lifecycle($id, $repo->get($id)['version'], array_map(static fn ($g) => $g['version'], $repo->groups($id)), 'draft'); TecBridge::sync(); };
$publish = static function (int $id) use ($repo): void { foreach ($repo->groups($id) as $gid => $g) $repo->confirm($repo->previewReview($gid, $g['version'])); $repo->publish($repo->previewPublication($id, $repo->get($id)['version'], true)); TecBridge::sync(); };
try {
    wp_set_current_user($admin);
    foreach (['Kursperioder', 'Ny kurskategori'] as $name) {
        $term = wp_insert_term('TEC test ' . $name . ' ' . wp_generate_uuid4(), TecDefaults::TAXONOMY);
        if (is_wp_error($term)) throw new RuntimeException($term->get_error_message());
        $terms[] = (int) $term['term_id'];
    }
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $images = [];
    foreach ([1, 2] as $number) {
        $upload = wp_upload_bits('rnl-tec-test-' . wp_generate_uuid4() . '.png', null, file_get_contents(WP_PLUGIN_DIR . '/reginor-lite/assets/vendor/leaflet/images/marker-icon.png'));
        if ($upload['error']) throw new RuntimeException($upload['error']);
        $uploads[] = $upload['file'];
        $image = $created[] = $images[] = wp_insert_attachment(['post_title' => 'TEC testbilde', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], $upload['file']);
        wp_update_attachment_metadata($image, wp_generate_attachment_metadata($image, $upload['file']));
    }
    TecDefaults::update(['category_id' => $terms[0], 'image_id' => $images[0]]);
    $assert(TecDefaults::read() === ['category_id' => $terms[0], 'image_id' => $images[0]], 'Kalendervalg lagres ikke.');
    foreach ([['category_id' => -1, 'image_id' => 0], ['category_id' => 'not-an-id', 'image_id' => 0], ['category_id' => [], 'image_id' => 0], ['category_id' => PHP_INT_MAX, 'image_id' => 0], ['category_id' => 0, 'image_id' => PHP_INT_MAX]] as $invalid) {
        $rejected = false; try { TecDefaults::update($invalid); } catch (InvalidArgumentException) { $rejected = true; }
        $assert($rejected && TecDefaults::read()['image_id'] === $images[0], 'Ugyldige valg endret lagrede innstillinger.');
    }
    wp_set_current_user(0); $rejected = false;
    try { TecDefaults::update(['category_id' => 0, 'image_id' => 0]); } catch (InvalidArgumentException) { $rejected = true; }
    $assert($rejected && TecDefaults::read()['category_id'] === $terms[0], 'Uautorisert bruker kan endre kalenderoppsettet.');
    wp_set_current_user($admin);
    ob_start(); CalendarSettings::render(); $settingsHtml = ob_get_clean();
    $assert(str_contains($settingsHtml, 'rnl-calendar-category') && str_contains($settingsHtml, 'rnl-calendar-image-preview') && str_contains($settingsHtml, '_wpnonce'), 'Kalenderfelter, bildeforhåndsvisning eller nonce mangler.');
    $venue = $created[] = $repo->create('venue', ['title' => 'TEC teststed', 'address' => 'Testgate']);
    $room = $created[] = $repo->create('room', ['title' => 'TEC testsal', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'TEC testkurs', 'description' => 'Test', 'level_description' => 'Test', 'dance_style' => 'Test', 'partner_info' => 'Test']);
    $date = new DateTimeImmutable('+7 days', new DateTimeZone('Europe/Oslo')); $end = $date->modify('+20 days')->format('Y-m-d');
    $period = $created[] = $repo->create('period', ['title' => 'TEC testperiode', 'timezone' => 'Europe/Oslo', 'start_date' => $date->format('Y-m-d'), 'end_date' => $end,
        'default_session_count' => 2, 'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person',
        'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'visible_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 50 * DAY_IN_SECONDS),
        'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - DAY_IN_SECONDS), 'sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 40 * DAY_IN_SECONDS),
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => [], 'calendar_enabled' => true]);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => (int) $date->format('N'), 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'later']);
    $repo->confirm($repo->previewGroup($group, 1, []));
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'TEC testside', 'post_content' => '[reginor_courses]']); update_option('rnl_course_page_id', $page);
    $other = tribe_events()->set_args(['title' => 'Uavhengig TEC test', 'start_date' => $date->format('Y-m-d') . ' 12:00:00', 'end_date' => $date->format('Y-m-d') . ' 13:00:00', 'status' => 'publish'])->create();
    $created[] = $other->ID;
    TecBridge::sync(); $assert(!(int) get_post_meta($period, TecBridge::EVENT, true), 'Kladd opprettet kalenderoppføring.');
    $assert(str_contains(TecBridge::status($period), 'ikke er publisert'), 'Kladdstatus forklarer ikke hvorfor kalenderdeling venter.');
    // Reproduce a failed ORM update against actual TEC, then recover the same draft using its API.
    $ormCallback = static function ($callback) use (&$period, &$writeMode) {
        return static function ($postarr) use ($callback, &$period, &$writeMode) {
            if (TecBridge::owner((int) ($postarr['ID'] ?? 0)) !== $period) return $callback($postarr);
            if ($writeMode === 'write_null') { $callback($postarr); return null; }
            if ($writeMode === 'false_success') return (int) $postarr['ID'];
            if ($writeMode === 'throw') throw new RuntimeException('Synthetic ORM failure');
            return null;
        };
    };
    add_filter('tribe_repository_events_update_callback', $ormCallback);
    tribe_update_option('datepickerFormat', 3);
    $publish($period);
    $event = (int) get_post_meta($period, TecBridge::EVENT, true); if ($event) $created[] = $event;
    $assert($event > 0 && get_post_status($event) === 'publish', 'Publisering opprettet ikke TEC-oppføring: ' . get_post_meta($period, TecBridge::ERROR, true));
    $assert(has_term($terms[0], TecDefaults::TAXONOMY, $event) && (int) get_post_thumbnail_id($event) === $images[0], 'Ny kalenderoppføring mangler kategori eller bilde.');
    $assert(tribe_event_featured_image($event, 'medium', false) !== '', 'Bildet vises ikke gjennom TECs bildefunksjon.');
    TecDefaults::update(['category_id' => $terms[1], 'image_id' => $images[1]]); TecBridge::sync();
    $assert((int) get_post_meta($period, TecBridge::EVENT, true) === $event && has_term($terms[1], TecDefaults::TAXONOMY, $event) && !has_term($terms[0], TecDefaults::TAXONOMY, $event) && (int) get_post_thumbnail_id($event) === $images[1], 'Endrede standardvalg oppdaterer ikke samme arrangement.');
    $assert(!has_term($terms, TecDefaults::TAXONOMY, $other->ID) && !get_post_thumbnail_id($other->ID), 'Kalendervalg endrer uavhengige TEC-arrangementer.');
    TecDefaults::update(['category_id' => 0, 'image_id' => 0]); TecBridge::sync();
    $assert(!wp_get_object_terms($event, TecDefaults::TAXONOMY) && !get_post_thumbnail_id($event), 'Fravalg fjerner ikke kategori og bilde.');
    TecDefaults::update(['category_id' => $terms[1], 'image_id' => $images[1]]); TecBridge::sync();
    wp_delete_term($terms[1], TecDefaults::TAXONOMY); wp_delete_attachment($images[1], true); TecBridge::sync();
    $assert(TecDefaults::read() === ['category_id' => 0, 'image_id' => 0] && !get_post_thumbnail_id($event), 'Slettet kategori/bilde håndteres ikke.');
    $assert(tribe_event_is_all_day($event), 'Perioden er ikke heldag.');
    $assert(substr(get_post_meta($event, '_EventStartDate', true), 0, 10) === $date->format('Y-m-d') && substr(get_post_meta($event, '_EventEndDate', true), 0, 10) === $end, 'Kalenderperioden bruker feil datoer.');
    $url = PublicSite::url(null, ['rnl_period' => $period]);
    $assert(tribe_get_event_link($event) === $url && get_permalink($event) === $url, 'Kalenderlenken viser ikke alle kursene i perioden.');
    $assert($date > new DateTimeImmutable('now') && get_post_status($event) === 'publish', 'Fremtidig kursperiode vises ikke før kursstart.');
    $actions = apply_filters('post_row_actions', [], get_post($event));
    $assert(str_contains($actions['rnl_view_period'] ?? '', esc_url($url)) && str_contains($actions['rnl_manage_period'] ?? '', 'period=' . $period), 'TEC-administrasjonen mangler lenker til riktig kursrekke og periodeoppsett.');
    $otherActions = apply_filters('post_row_actions', ['edit' => 'existing'], get_post($other->ID));
    $assert(!isset($otherActions['rnl_manage_period']) && !isset($otherActions['rnl_view_period']) && CalendarSettings::eventActions(['edit' => 'existing'], get_post($other->ID)) === ['edit' => 'existing'], 'Uavhengige TEC-arrangementer får RegiNor-lenker.');
    ob_start(); CalendarSettings::render(); $settingsHtml = ob_get_clean();
    $assert(str_contains($settingsHtml, esc_url($url)) && str_contains($settingsHtml, 'TEC testperiode'), 'Kalenderoppsettet mangler fremtidig kursrekke og riktig offentlig lenke.');
    // More than the default/nearest period must be projected, regardless of the overview selector.
    $nextDate = $date->modify('+28 days');
    $nextData = array_replace($repo->get($period)['data'], ['title' => 'Neste TEC testperiode', 'start_date' => $nextDate->format('Y-m-d'), 'end_date' => $nextDate->modify('+7 days')->format('Y-m-d'), 'show_as_upcoming' => false]);
    $nextPeriod = $created[] = $repo->create('period', $nextData);
    $nextGroup = $created[] = $repo->createGroup($nextPeriod, $course, ['weekday' => (int) $nextDate->format('N'), 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'later']);
    $repo->confirm($repo->previewGroup($nextGroup, 1, [])); $publish($nextPeriod);
    $nextEvent = (int) get_post_meta($nextPeriod, TecBridge::EVENT, true); if ($nextEvent) $created[] = $nextEvent;
    $assert($nextEvent && $nextEvent !== $event && get_post_status($nextEvent) === 'publish' && get_post_status($event) === 'publish', 'Kalenderen viser ikke flere fremtidige perioder samtidig.');
    $nextUrl = PublicSite::url(null, ['rnl_period' => $nextPeriod]);
    $assert($nextUrl !== $url && tribe_get_event_link($nextEvent) === $nextUrl, 'Neste periode lenker til standardperioden i stedet for sin egen kursrekke.');
    // Exercise TEC Pro's documented ID-normalization contract without installing/licensing Pro.
    $occurrenceId = 900000000 + $event;
    $normalizeOccurrence = static fn ($id) => (int) $id === $occurrenceId ? $event : $id;
    add_filter('tec_events_custom_tables_v1_normalize_occurrence_id', $normalizeOccurrence, 99);
    $assert(TecBridge::owner($occurrenceId) === $period && TecBridge::periodUrl($occurrenceId) === $url, 'Forekomst-ID finner ikke periode og offentlig lenke.');
    $occurrencePost = clone get_post($event); $occurrencePost->ID = $occurrenceId;
    $assert(str_contains(CalendarSettings::eventActions([], $occurrencePost)['rnl_view_period'] ?? '', esc_url($url)), 'TEC Pro-forekomst mangler administrasjonslenke.');
    $expectedTitle = get_post_field('post_title', $event);
    wp_update_post(['ID' => $event, 'post_title' => 'Outdated title']); delete_post_meta($event, '_rnl_tec_hash');
    $updateCount = 0;
    $countUpdate = static function ($id) use ($event, &$updateCount) { if ((int) $id === $event) $updateCount++; };
    add_action('pre_post_update', $countUpdate);
    $writeMode = 'write_null'; TecBridge::sync(); remove_action('pre_post_update', $countUpdate);
    $assert(get_post_field('post_title', $event) === $expectedTitle && $updateCount === 1 && !get_post_meta($period, TecBridge::ERROR, true), 'Vellykket ORM-skriving med nullsvar fører til feil eller unødvendig reserveskriving.');
    foreach (['false_success', 'throw'] as $mode) {
        wp_update_post(['ID' => $event, 'post_title' => 'Still outdated']); delete_post_meta($event, '_rnl_tec_hash');
        $writeMode = $mode; TecBridge::sync();
        $assert(get_post_field('post_title', $event) === $expectedTitle && !get_post_meta($period, TecBridge::ERROR, true), 'Falsk suksess eller unntak fra ORM blir ikke reparert gjennom TEC API.');
    }
    foreach (['', null] as $missingFormat) {
        tribe_update_option('datepickerFormat', $missingFormat);
        wp_update_post(['ID' => $event, 'post_title' => 'Missing date format']); delete_post_meta($event, '_rnl_tec_hash');
        $writeMode = 'no_write'; TecBridge::sync();
        $assert(get_post_field('post_title', $event) === $expectedTitle && !get_post_meta($period, TecBridge::ERROR, true), 'Manglende datovelgerformat stopper reserveoppdateringen.');
    }
    tribe_update_option('datepickerFormat', 4);
    wp_update_post(['ID' => $event, 'post_title' => 'Missing from ORM query']); delete_post_meta($event, '_rnl_tec_hash');
    $emptyQuery = static function ($query) { $query->set('p', 0); $query->set('post__in', [-1]); };
    add_action('tribe_repository_events_pre_get_ids_for_posts', $emptyQuery);
    TecBridge::sync(); remove_action('tribe_repository_events_pre_get_ids_for_posts', $emptyQuery);
    $assert(get_post_field('post_title', $event) === $expectedTitle && !get_post_meta($period, TecBridge::ERROR, true), 'Tomt ORM-oppslag hindrer reparasjon gjennom stabil arrangements-ID.');
    wp_update_post(['ID' => $event, 'post_title' => 'Rejected update']); delete_post_meta($event, '_rnl_tec_hash');
    $rejectWrite = static function ($data, $postarr) use ($event) {
        if ((int) ($postarr['ID'] ?? 0) === $event) $data['post_title'] = 'Rejected update';
        return $data;
    };
    add_filter('wp_insert_post_data', $rejectWrite, 99, 2);
    $writeMode = 'no_write'; TecBridge::sync(); remove_filter('wp_insert_post_data', $rejectWrite, 99);
    $error = TecBridge::status($period);
    $assert(str_contains($error, '#' . $event) && str_contains($error, 'tittel') && str_contains($error, 'Kontrollkode:'), 'Begge feilede metoder gir ikke konkret felt, arrangements-ID og kontrollkode.');
    $assert(!get_post_meta($event, '_rnl_tec_hash', true), 'Feilet lagring ble merket som synkronisert.');
    TecBridge::sync();
    $assert(get_post_field('post_title', $event) === $expectedTitle && !get_post_meta($period, TecBridge::ERROR, true) && count(array_filter(TecBridge::owned(), static fn ($owner) => $owner === $period)) === 1, 'Gjenoppretting rydder ikke feil eller oppretter duplikat.');
    update_post_meta($period, TecBridge::EVENT, $occurrenceId);
    $assert(str_contains(TecBridge::status($period), 'Perioden vises'), 'Eldre kobling lagret med forekomst-ID skjuler publisert periode.');
    TecBridge::sync();
    $assert((int) get_post_meta($period, TecBridge::EVENT, true) === $event && get_post_status($event) === 'publish', 'Synkronisering reparerer ikke eldre forekomst-ID til stabil post-ID.');
    $assert(apply_filters('tribe_ical_template_event_ids', [$occurrenceId]) === [$occurrenceId], 'Synlig forekomst forsvant fra ICS.');
    $denyLock = static fn ($sql) => str_contains($sql, 'SELECT GET_LOCK(') ? 'SELECT 0' : $sql;
    add_filter('query', $denyLock); TecBridge::sync(); remove_filter('query', $denyLock);
    $assert(str_contains(TecBridge::status($period), 'kalenderoppdatering pågår'), 'Opptatt kalenderlås forveksles med feil i kursoppsettet.');
    $nullLock = static fn ($sql) => str_contains($sql, 'SELECT GET_LOCK(') ? 'SELECT NULL' : $sql;
    add_filter('query', $nullLock);
    try { TecBridge::sync(); }
    finally { remove_filter('query', $nullLock); }
    $assert(str_contains(TecBridge::status($period), 'RNL-DB-LOCK'), 'Databasefeil feilrapporteres som en opptatt kalenderlås.');
    TecBridge::sync();
    $assert(str_contains(TecBridge::status($period), 'Perioden vises'), 'Vellykket forsøk rydder ikke låsefeilen.');
    $assert(!has_action('wp_loaded', [TecBridge::class, 'syncBeforeRead']) && !has_action('wp_loaded', [TecBridge::class, 'sync']), 'Kalenderskriving er fortsatt koblet til sidelasting.');
    wp_clear_scheduled_hook(TecSyncQueue::SOON);
    wp_update_post(['ID' => $event, 'post_title' => 'Waiting for worker']); delete_post_meta($event, '_rnl_tec_hash');
    delete_option(TecSyncQueue::STATUS);
    TecSyncQueue::request(); $queuedAt = wp_next_scheduled(TecSyncQueue::SOON);
    TecSyncQueue::request();
    $assert($queuedAt && wp_next_scheduled(TecSyncQueue::SOON) === $queuedAt && get_post_field('post_title', $event) === 'Waiting for worker', 'Kølegging skriver synkront eller legger til duplikatjobb.');
    $assert(str_contains(TecSyncQueue::message() ?? '', 'ligger i kø'), 'Ventende jobb mangler forklaring.');
    update_option(TecSyncQueue::STATUS, ['state' => 'queued', 'queued_at' => time() - 601]);
    $assert(str_contains(TecSyncQueue::message() ?? '', 'RNL-TEC-DELAYED'), 'En kø som ikke blir kjørt mangler driftsvarsel.');
    wp_unschedule_event($queuedAt, TecSyncQueue::SOON);
    wp_set_current_user(0);
    try { do_action(TecSyncQueue::SOON); } finally { wp_set_current_user($admin); }
    $assert(get_post_field('post_title', $event) === $expectedTitle && get_option(TecSyncQueue::STATUS)['state'] === 'done', 'Bakgrunnskroken oppdaterer ikke kalenderen.');
    update_option(TecSyncQueue::STATUS, ['state' => 'running', 'started_at' => time() - 200]);
    $assert(str_contains(TecBridge::status($period), 'RNL-TEC-INCOMPLETE'), 'Avbrutt jobb forklares som evig pågående synkronisering.');
    TecBridge::sync();
    TecSyncQueue::ensure(); $periodicAt = wp_next_scheduled(TecSyncQueue::PERIODIC);
    TecSyncQueue::ensure();
    $assert($periodicAt && wp_next_scheduled(TecSyncQueue::PERIODIC) === $periodicAt && wp_get_schedule(TecSyncQueue::PERIODIC) === 'rnl_tec_five_minutes', 'Periodisk kontroll mangler eller dupliseres.');
    wp_clear_scheduled_hook(TecSyncQueue::SOON);
    $rejectSchedule = static fn ($result, $cron) => $cron->hook === TecSyncQueue::SOON ? new WP_Error('test', 'Synthetic schedule failure') : $result;
    add_filter('pre_schedule_event', $rejectSchedule, 10, 2);
    try { TecSyncQueue::request(); } finally { remove_filter('pre_schedule_event', $rejectSchedule, 10); }
    $assert(str_contains(TecSyncQueue::message() ?? '', 'RNL-TEC-SCHEDULE'), 'Avvist kølegging vises som vellykket.');
    foreach (['could_not_set', 'pre_schedule_event_false', 'schedule_event_false', 'duplicate_event', 'invalid_schedule', 'private-provider-secret'] as $code) {
        $rejectSchedule = static fn ($result, $cron) => $cron->hook === TecSyncQueue::SOON ? new WP_Error($code, 'PRIVATE MESSAGE', ['secret' => 'PRIVATE DATA']) : $result;
        add_filter('pre_schedule_event', $rejectSchedule, 10, 2);
        try { TecSyncQueue::request(); } finally { remove_filter('pre_schedule_event', $rejectSchedule, 10); }
        $message = TecSyncQueue::message() ?? '';
        $expected = $code === 'private-provider-secret' ? 'provider_error' : $code;
        $assert(str_contains($message, 'RNL-TEC-SCHEDULE/' . $expected) && str_contains($message, TecSyncQueue::SOON) && str_contains($message, 'ikke Action Scheduler') && !str_contains($message, 'PRIVATE') && !str_contains($message, 'private-provider-secret'), 'Køfeil mangler konkret diagnose eller lekker leverandørdata.');
    }
    // WordPress may reject the cron option write without a SQL exception.
    $rejectCronWrite = static fn ($value, $old) => $old;
    add_filter('pre_update_option_cron', $rejectCronWrite, 10, 2);
    try { TecSyncQueue::request(); } finally { remove_filter('pre_update_option_cron', $rejectCronWrite, 10); }
    $assert(str_contains(TecSyncQueue::message() ?? '', 'RNL-TEC-SCHEDULE/could_not_set') && !wp_get_scheduled_event(TecSyncQueue::SOON), 'Mislykket cron-lagring blir ikke rapportert korrekt.');
    // Simulate another request installing the exact job between lookup and schedule.
    $competingSchedule = null;
    $competingSchedule = static function ($result, $cron) use (&$competingSchedule) {
        if ($cron->hook !== TecSyncQueue::SOON) { return $result; }
        remove_filter('pre_schedule_event', $competingSchedule, 10);
        wp_schedule_single_event($cron->timestamp, $cron->hook, [], true);
        return new WP_Error('duplicate_event', 'Already scheduled concurrently');
    };
    add_filter('pre_schedule_event', $competingSchedule, 10, 2);
    try { TecSyncQueue::request(); } finally { remove_filter('pre_schedule_event', $competingSchedule, 10); }
    $assert(wp_get_scheduled_event(TecSyncQueue::SOON) && !str_contains(TecSyncQueue::message() ?? '', 'RNL-TEC-SCHEDULE'), 'Bekreftet konkurrerende kølegging vises som feil.');
    update_option(TecSyncQueue::STATUS, ['state' => 'error', 'schedule_hook' => TecSyncQueue::SOON, 'schedule_code' => 'could_not_set', 'message' => 'Old scheduling failure']);
    TecSyncQueue::request();
    $assert(str_contains(TecSyncQueue::message() ?? '', 'ligger i kø'), 'Gammel køfeil henger igjen etter at jobben finnes.');
    TecSyncQueue::finished('Keep real worker error'); TecSyncQueue::request();
    $assert(TecSyncQueue::message() === 'Keep real worker error', 'Eksisterende jobb skjuler feil fra selve arbeidet.');
    ob_start(); CalendarSettings::render(); $queueHtml = ob_get_clean();
    $assert(str_contains($queueHtml, 'Teknisk status for kalenderkøen') && str_contains($queueHtml, TecSyncQueue::SOON) && str_contains($queueHtml, TecSyncQueue::PERIODIC), 'Administrasjonen mangler lesbar køstatus.');
    TecBridge::sync();
    // During actual TEC saving only the projection lock is held, not the source lock.
    global $wpdb;
    $sourceWriter = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $sourceName = \RegiNor\Lite\Infrastructure\DatabaseLock::name();
    $assert((int) $sourceWriter->get_var($sourceWriter->prepare('SELECT GET_LOCK(%s, 0)', $sourceName)) === 1, 'Kunne ikke holde kurslåsen.');
    try {
        TecBridge::sync();
        $assert(str_contains(TecBridge::status($period), 'venter til en kursendring'), 'Kalenderen leser midt i en annen kursendring.');
        $assert((int) $wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', \RegiNor\Lite\Infrastructure\DatabaseLock::name(true))) === 1, 'Ventende kildeoppslag etterlot kalenderlåsen.');
    } finally { $sourceWriter->get_var($sourceWriter->prepare('SELECT RELEASE_LOCK(%s)', $sourceName)); $sourceWriter->close(); }
    $probe = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST); $probed = false;
    $probeSave = static function ($id) use ($event, $probe, &$probed, $assert) {
        if ($id !== $event) return;
        $source = \RegiNor\Lite\Infrastructure\DatabaseLock::name();
        $calendar = \RegiNor\Lite\Infrastructure\DatabaseLock::name(true);
        $sourceHeld = (int) $probe->get_var($probe->prepare('SELECT GET_LOCK(%s, 0)', $source));
        try {
            $assert($sourceHeld === 1, 'TEC-lagring holder fortsatt kurslåsen.');
            $assert((int) $probe->get_var($probe->prepare('SELECT IS_FREE_LOCK(%s)', $calendar)) === 0, 'TEC-lagring mangler dublettvern.');
            $probed = true;
        } finally { if ($sourceHeld === 1) $probe->get_var($probe->prepare('SELECT RELEASE_LOCK(%s)', $source)); }
    };
    wp_update_post(['ID' => $event, 'post_title' => 'Probe write']); delete_post_meta($event, '_rnl_tec_hash');
    add_action('pre_post_update', $probeSave);
    try { TecBridge::sync(); } finally { remove_action('pre_post_update', $probeSave); $probe->close(); }
    $assert($probed, 'Kontrollen kjørte ikke under en faktisk TEC-lagring.');

    // Withdrawal after the snapshot but before TEC's write must win in public reads.
    $withdrew = false;
    $withdrawDuringSave = static function ($id) use ($event, $period, $repo, &$withdrew) {
        if ($id !== $event || $withdrew) return;
        $withdrew = true;
        $repo->lifecycle($period, $repo->get($period)['version'], array_map(static fn ($g) => $g['version'], $repo->groups($period)), 'draft');
    };
    wp_update_post(['ID' => $event, 'post_title' => 'Withdraw during write']); delete_post_meta($event, '_rnl_tec_hash');
    add_action('pre_post_update', $withdrawDuringSave);
    try { TecBridge::sync(); } finally { remove_action('pre_post_update', $withdrawDuringSave); }
    wp_set_current_user(0);
    $assert($withdrew && get_post_status($period) === 'draft' && rest_do_request('/tribe/events/v1/events/' . $event)->get_status() === 404, 'Kalendersnapshot overstyrer en nyere kladdstatus.');
    wp_set_current_user($admin); $publish($period);

    // Failures in language hooks must release the projection lock and reset reentrancy.
    foreach (['start', 'restore'] as $failurePoint) {
        $currentLanguage = static fn () => 'en'; $defaultLanguage = static fn () => 'nb';
        $throwLanguage = static function ($language) use ($failurePoint) {
            if ($language === ($failurePoint === 'start' ? 'nb' : 'en')) throw new RuntimeException('Synthetic language hook failure');
        };
        add_filter('wpml_current_language', $currentLanguage); add_filter('wpml_default_language', $defaultLanguage);
        add_action('wpml_switch_language', $throwLanguage);
        try { TecBridge::sync(); }
        finally { remove_filter('wpml_current_language', $currentLanguage); remove_filter('wpml_default_language', $defaultLanguage); remove_action('wpml_switch_language', $throwLanguage); }
        $assert((int) $wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', \RegiNor\Lite\Infrastructure\DatabaseLock::name(true))) === 1, 'Språkfeil etterlot kalenderlåsen.');
        TecBridge::sync();
        $assert(str_contains(TecBridge::status($period), 'Perioden vises'), 'Språkfeil blokkerte senere synkronisering.');
    }
    delete_option('rnl_course_page_id');
    $assert(str_contains(TecBridge::status($period), 'offentlig kursside'), 'Manglende kursside forklares ikke.');
    update_option('rnl_course_page_id', $page);
    $assert(str_contains(get_post_field('post_content', $event), 'faste kursdager'), 'Periodens betydning mangler i beskrivelsen.');
    $assert(!current_user_can('edit_post', $event) && !current_user_can('delete_post', $event), 'Koblede arrangementer kan endres utenfor RegiNor.');
    TecBridge::sync(); TecBridge::sync();
    $assert((int) get_post_meta($period, TecBridge::EVENT, true) === $event && count(array_filter(TecBridge::owned(), static fn ($id) => $id === $period)) === 1, 'Gjentatt synkronisering oppretter duplikater.');
    wp_set_current_user(0); $rest = rest_do_request('/tribe/events/v1/events/' . $event);
    $assert($rest->get_status() === 200 && ($rest->get_data()['url'] ?? '') === $url, 'Synlig arrangement mangler fra TEC REST.');
    $assert(CalendarSettings::periodActions($period) === [], 'Anonym bruker får administrasjonslenker.');
    wp_set_current_user($admin);
    // A slow TEC worker must not keep the source course period published.
    global $wpdb;
    $calendarLock = 'rnl:tec:' . md5(DB_NAME . ':' . $wpdb->prefix);
    $competitor = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $assert((int) $competitor->get_var($competitor->prepare('SELECT GET_LOCK(%s, 0)', $calendarLock)) === 1, 'Kunne ikke holde kalenderlåsen.');
    try {
        $draft($period);
        $assert(get_post_status($period) === 'draft' && get_post_status($group) === 'draft', 'Kalenderlåsen blokkerer kladd.');
        $assert(get_post_status($event) === 'publish', 'Testen skal etterlate en gammel kalenderkopi mens låsen holdes.');
        wp_set_current_user(0);
        $assert(rest_do_request('/tribe/events/v1/events/' . $event)->get_status() === 404, 'Kladd lekker via gammel kalenderkopi mens synkronisering venter.');
        $assert(apply_filters('tribe_ical_template_event_ids', [$event]) === [], 'Kladd lekker til ICS mens synkronisering venter.');
    } finally {
        $competitor->get_var($competitor->prepare('SELECT RELEASE_LOCK(%s)', $calendarLock)); $competitor->close(); wp_set_current_user($admin);
    }
    TecBridge::sync();
    $assert(get_post_status($event) === 'draft', 'Avpublisering skjuler ikke TEC-oppføring.');
    $assert(apply_filters('tribe_ical_template_event_ids', [$occurrenceId, $other->ID]) === [$other->ID], 'Skjult forekomst lekker gjennom ICS-filteret.');
    wp_set_current_user(0);
    $assert(rest_do_request('/tribe/events/v1/events/' . $occurrenceId)->get_status() === 404, 'Skjult forekomst lekker gjennom REST-filteret.');
    wp_set_current_user($admin);
    $actions = apply_filters('post_row_actions', [], get_post($event));
    $assert(isset($actions['rnl_manage_period']) && !isset($actions['rnl_view_period']), 'Kladd skal ha administrasjonslenke, men ingen offentlig kurslenke.');
    $assert(get_post_status($other->ID) === 'publish' && current_user_can('edit_post', $other->ID) && !str_contains(get_permalink($other->ID), 'rnl_period'), 'Uavhengig TEC-arrangement ble påvirket.');
    wp_set_current_user(0); $assert(rest_do_request('/tribe/events/v1/events/' . $event)->get_status() === 404, 'Skjult periode kan leses via TEC REST.');
    wp_set_current_user($admin); $savePeriod($period, ['title' => 'Endret TEC testperiode', 'end_date' => $date->modify('+25 days')->format('Y-m-d')]); $publish($period);
    $assert((int) get_post_meta($period, TecBridge::EVENT, true) === $event && str_contains(get_the_title($event), 'Endret TEC'), 'Endring gjenbruker ikke samme arrangement.');
    $assert(substr(get_post_meta($event, '_EventEndDate', true), 0, 10) === $date->modify('+25 days')->format('Y-m-d'), 'Endret sluttdato er ikke synkronisert.');
    $draft($period); $savePeriod($period, ['calendar_enabled' => false]); $publish($period);
    $assert(get_post_status($event) === 'draft', 'Fravalgt kalenderdeling vises fortsatt.');
    $draft($period); $savePeriod($period, ['calendar_enabled' => true, 'end_date' => null]); $publish($period);
    $assert(substr(get_post_meta($event, '_EventEndDate', true), 0, 10) === $date->modify('+7 days')->format('Y-m-d'), 'Manglende sluttdato bruker ikke siste kurskveld.');
    // Simulate a time-boundary crossing without a request-time sync and leave a stale published copy.
    $state = get_post_meta($period, ContentTypes::META, true); $saved = $state;
    $state['data']['visible_until'] = gmdate('Y-m-d\TH:i:s\Z', time() - 1); update_post_meta($period, ContentTypes::META, $state);
    wp_set_current_user(0);
    $assert(rest_do_request('/tribe/events/v1/events/' . $event)->get_status() === 404, 'Stale TEC-kopi omgår tidsvindu uten synkronisering.');
    $assert(!in_array($event, get_posts(['post_type' => 'tribe_events', 'fields' => 'ids', 'posts_per_page' => -1, 'suppress_filters' => false]), true), 'Skjult, foreldet kalenderoppføring eksponeres i offentlig søk.');
    TecBridge::sync(); $assert(get_post_status($event) === 'draft', 'Besøk avpubliserer ikke ved utløpt synlighet.');
    $assert(str_contains(TecBridge::status($period), 'Synlig til er passert'), 'Utløpt synlighet har upresis kalenderstatus.');
    update_post_meta($period, ContentTypes::META, $saved); TecBridge::sync(); $assert(get_post_status($event) === 'publish', 'Synlighet gjenopprettes ikke.');
    $state = $saved; $state['data']['visible_from'] = gmdate('Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS);
    update_post_meta($period, ContentTypes::META, $state); TecBridge::sync();
    $assert(get_post_status($event) === 'draft' && !isset(CalendarSettings::periodActions($period)['rnl_view_period']), 'Fremtidig synlighetsstart omgås av kalenderdeling eller lenke.');
    $assert(str_contains(TecBridge::status($period), 'Synlig fra:') && str_contains(TecBridge::status($period), 'kursstart og salgsdatoer'), 'Fremtidig synlighetsstart forklares ikke med dato og forskjell fra kursstart.');
    update_post_meta($period, ContentTypes::META, $saved); TecBridge::sync();
    wp_set_current_user($admin); $draft($period); $copy = $created[] = $repo->copyPeriod($period, $repo->get($period)['version'], 'TEC testkopi', $date->modify('+2 months')->format('Y-m-d'));
    foreach ($repo->groups($copy) as $gid => $_) $created[] = $gid;
    $assert(!(int) get_post_meta($copy, TecBridge::EVENT, true), 'Kopiering gjenbruker gammel TEC-ID.');
} finally {
    if ($emptyQuery) remove_action('tribe_repository_events_pre_get_ids_for_posts', $emptyQuery);
    if ($ormCallback) remove_filter('tribe_repository_events_update_callback', $ormCallback);
    if ($rejectWrite) remove_filter('wp_insert_post_data', $rejectWrite, 99);
    if ($countUpdate) remove_action('pre_post_update', $countUpdate);
    tribe_update_option('datepickerFormat', $oldDateFormat);
    if ($normalizeOccurrence) remove_filter('tec_events_custom_tables_v1_normalize_occurrence_id', $normalizeOccurrence, 99);
    if ($denyLock) remove_filter('query', $denyLock);
    wp_set_current_user($admin);
    foreach (array_reverse($created) as $id) wp_delete_post($id, true);
    // Clean only projections of this test's period, including recovery from failed assertions.
    foreach (TecBridge::owned() as $event => $owner) if (in_array($owner, $created, true)) wp_delete_post($event, true);
    if ($oldPage === null) delete_option('rnl_course_page_id'); else update_option('rnl_course_page_id', $oldPage);
    foreach ($terms as $term) wp_delete_term($term, TecDefaults::TAXONOMY);
    foreach ($uploads as $file) if (file_exists($file)) wp_delete_file($file);
    if ($oldDefaults === null) delete_option(TecDefaults::OPTION); else update_option(TecDefaults::OPTION, $oldDefaults);
    TecBridge::sync();
    if ($oldSyncStatus === null) delete_option(TecSyncQueue::STATUS); else update_option(TecSyncQueue::STATUS, $oldSyncStatus, false);
    wp_set_current_user($oldUser);
}
WP_CLI::success("$checks TEC integration checks passed; synthetic fixtures removed.");
