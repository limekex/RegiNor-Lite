<?php
/** Local-only integration tests for sprint 3; no production data or external calls. */
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Admin\CourseActions;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Domain\Publication\Clock;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokale WP-CLI-tester.'); }
$created = $users = []; $checks = 0; $originalUser = get_current_user_id();
$assert = static function (bool $yes, string $message) use (&$checks): void { ++$checks; if (!$yes) { throw new RuntimeException($message); } };
$reject = static function (callable $operation, int $code = 0) use ($assert): void {
    try { $operation(); } catch (Throwable $e) { $assert(!$code || $e->getCode() === $code, 'Feil avvisning: ' . $e->getMessage()); return; }
    $assert(false, 'Ugyldig operasjon ble tillatt.');
};
$clock = new class implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-09-17T10:00:00Z'); } };
$repo = new CourseRepository($clock); $actions = new CourseActions($repo);
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$types = static fn () => ['rnl_fixture_profile'];
$failure = null;
try {
    wp_set_current_user($admin);
    register_post_type('rnl_fixture_profile', ['public' => true]); add_filter('rnl_instructor_post_types', $types);
    $venue = $created[] = $repo->create('venue', ['title' => 'Sprint 3 sted', 'address' => 'Testgata 3']);
    $room = $created[] = $repo->create('room', ['title' => 'Sprint 3 sal', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Sprint 3 salsa', 'description' => 'Testbeskrivelse', 'level_description' => 'Ingen forkunnskaper', 'dance_style' => 'Salsa', 'partner_info' => 'Valgfri partner']);
    $instructor = $created[] = wp_insert_post(['post_type' => 'rnl_fixture_profile', 'post_status' => 'publish', 'post_title' => 'Testinstruktør']);
    add_post_meta($instructor, 'private_salary', 'Må aldri vises i oppslag');
    $ordinary = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Uvedkommende side']);
    $before = get_post($ordinary)->to_array();
    foreach (['rnl_course_manager', 'rnl_course_manager', 'subscriber'] as $role) {
        $user = wp_insert_user(['user_login' => 'rnl-s3-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => $role]);
        if (is_wp_error($user)) { throw new RuntimeException($user->get_error_message()); } $users[] = $user;
    }
    wp_set_current_user($users[0]);
    foreach (['edit_posts', 'edit_pages', 'upload_files', 'manage_options', 'edit_users', 'activate_plugins', 'rnl_edit_courses', 'rnl_create_rooms'] as $cap) { $assert(!current_user_can($cap), 'Kursansvarlig har for vide rettigheter: ' . $cap); }
    $assert(current_user_can('rnl_create_periods') && current_user_can('rnl_publish_groups'), 'Kursrettigheter mangler.');
    $reject(fn () => $repo->get($course), 403);
    $assert($repo->resource($course, 'course')['title'] === 'Sprint 3 salsa', 'Avgrenset beskrivelsesoppslag feiler.');
    $assert(array_keys($repo->instructors()[0]) === ['id', 'title'], 'Instruktøroppslaget lekker metadata.');
    $reject(fn () => $repo->create('venue', ['title' => 'Ulovlig', 'address' => 'Gate 1']), 403);
    $reject(fn () => $repo->update($course, 1, []), 403);
    $reject(fn () => $actions->handle(['command' => 'instructor_types', '_wpnonce' => wp_create_nonce('rnl_course_command'), 'types' => ['page']]), 403);
    $reject(fn () => $actions->handle(['command' => 'save', '_wpnonce' => 'invalid']), 403);
    $reject(fn () => $actions->handle(['command' => 'save']), 403);

    $p = ['title' => 'Sprint 3 periode', 'timezone' => 'Europe/Oslo', 'start_date' => '2027-01-11', 'default_session_count' => 2,
        'default_room_id' => $room, 'default_price_minor' => 120000, 'default_price_basis' => 'person', 'visible_from' => '2027-01-01T00:00:00Z',
        'visible_until' => '2027-02-01T00:00:00Z', 'sales_from' => '2027-01-01T00:00:00Z', 'sales_until' => '2027-01-10T00:00:00Z',
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []];
    $period = $created[] = $repo->create('period', $p);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'instructor_ids' => [$instructor], 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/synthetic']);
    $assert($repo->previewPublication($period, 1, true)['errors'] !== [], 'Tom øktplan kunne publiseres.');
    $repo->confirm($repo->previewGroup($group, 1, []));
    $assert($repo->previewPublication($period, 1)['errors'] !== [], 'Vinduer krever eksplisitt bekreftelse.');
    $preview = $repo->previewPublication($period, 1, true);
    $assert($preview['errors'] === [], 'Gyldig periode avvist: ' . implode(' ', $preview['errors']));
    $tampered = $preview; $tampered['outside'] = true; $reject(fn () => $repo->publish($tampered), 403);
    wp_set_current_user($users[1]);
    $assert($repo->get($period)['version'] === 1, 'Andre kursansvarlige kan ikke samarbeide.');
    $reject(fn () => $repo->publish($preview), 403);
    $repo->update($period, 1, array_replace($p, ['title' => 'Samarbeid']));
    wp_set_current_user($users[0]);
    $reject(fn () => $repo->publish($preview), 409);
    $reject(fn () => $repo->update($period, 1, $p), 409);
    $assert($repo->previewPublication($period, 2, true)['errors'] !== [], 'Foreldet gruppeplan kunne publiseres.');
    $repo->confirm($repo->previewReview($group, 2));
    $preview = $repo->previewPublication($period, 2, true);
    $assert($preview['errors'] === [], 'Gjennomgang av planen feilet.');

    // Required fields and deliberate visibility/sales exceptions are checked before publishing.
    $beforeWindows = $repo->get($period);
    $windowData = array_replace($beforeWindows['data'], ['visible_until' => '2027-01-12T00:00:00Z', 'sales_until' => '2027-01-20T00:00:00Z']);
    $windowState = $repo->update($period, $beforeWindows['version'], $windowData);
    $g = $repo->get($group); $repo->confirm($repo->previewReview($group, $g['version']));
    $windowCheck = $repo->previewPublication($period, $windowState['version'], true);
    $assert(count($windowCheck['warnings']) >= 2 && $windowCheck['errors'] === [], 'Tidlig skjuling og sen påmelding skal være ikke-blokkerende informasjon.');
    $assert($repo->previewPublication($period, $windowState['version'], true, true, true)['errors'] === [], 'Eksplisitte vindusvalg ble ignorert.');
    // Restore ordinary windows. Later test cases read the new version instead of assuming version 2.
    $normal = $repo->update($period, $windowState['version'], $beforeWindows['data']);
    $g = $repo->get($group); $repo->confirm($repo->previewReview($group, $g['version']));
    $periodVersion = $normal['version'];
    $preview = $repo->previewPublication($period, $periodVersion, true);

    // Shared descriptions changed after preview must invalidate confirmation.
    wp_set_current_user($admin); $c = $repo->get($course); $repo->update($course, $c['version'], array_replace($c['data'], ['description' => 'Oppdatert beskrivelse']));
    wp_set_current_user($users[0]); $reject(fn () => $repo->publish($preview), 409);
    $preview = $repo->previewPublication($period, $periodVersion, true);
    // Simulate a status-write failure after earlier metadata updates: transaction must roll all back.
    $beforePeriod = $repo->get($period); $beforeGroup = $repo->get($group);
    $failure = static function ($empty, $post) use ($period) { return (int) ($post['ID'] ?? 0) === $period && ($post['post_status'] ?? '') === 'publish' ? true : $empty; };
    add_filter('wp_insert_post_empty_content', $failure, 20, 2);
    $reject(fn () => $repo->publish($preview));
    remove_filter('wp_insert_post_empty_content', $failure, 20); $failure = null;
    $assert(get_post_status($group) === 'draft' && $repo->get($group) === $beforeGroup && $repo->get($period) === $beforePeriod, 'Feilet publisering etterlot delvis tilstand.');
    $published = $repo->publish($preview);
    $assert(get_post_status($period) === 'publish' && get_post_status($group) === 'publish', 'Publiserte ikke hele perioden.');
    $assert($published['event'] === 'published' && $published['actor_id'] === $users[0] && $repo->get($group)['data']['period_version'] === $published['version'], 'Publiseringslogg eller gruppeversjon mangler.');
    $reject(fn () => $repo->publish($preview), 409);
    $reject(fn () => $repo->update($period, $published['version'], $p), 409);
    $reject(fn () => $repo->createGroup($period, $course, ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '19:00']), 409);
    $assert(!current_user_can('delete_post', $period), 'Generisk massesletting er åpen.');
    $raw = wp_update_post(['ID' => $period, 'post_title' => 'Ulovlig råskriving'], true);
    $assert(is_wp_error($raw), 'Generisk WordPress-skriving omgår arbeidsflyten.');

    $versions = array_map(static fn ($g) => $g['version'], $repo->groups($period));
    $reject(fn () => $repo->lifecycle($period, $published['version'], [], 'draft'), 409);
    try {
        $repo->lifecycle($period, $published['version'], [], 'draft');
        $assert(false, 'Endret kursliste ble godkjent.');
    } catch (\RegiNor\Lite\Infrastructure\VersionConflict $error) {
        $assert(str_contains($error->getMessage(), 'RNL-COURSE-LIST') && str_contains($error->getMessage(), $repo->get($group)['data']['title']), 'Kurslistekonflikt mangler konkret kurs og kontrollkode.');
    }
    try {
        $repo->lifecycle($period, $published['version'] - 1, $versions, 'draft');
        $assert(false, 'Gammel periodeversjon ble godkjent.');
    } catch (\RegiNor\Lite\Infrastructure\VersionConflict $error) {
        $assert(str_contains($error->getMessage(), 'RNL-VERSION') && str_contains($error->getMessage(), 'lagret versjon er ' . $published['version']), 'Periodekonflikt mangler konkrete versjoner.');
    }
    $draft = $repo->lifecycle($period, $published['version'], $versions, 'draft');
    $assert(get_post_status($group) === 'draft' && $draft['event'] === 'unpublished', 'Avpublisering mangler.');
    $versions = array_map(static fn ($g) => $g['version'], $repo->groups($period));
    $reject(fn () => $repo->lifecycle($period, $draft['version'], $versions, 'trash'), 403);

    // Explicit cancellation and replacement preserve identity and are publishable.
    $g = $repo->get($group); $session = $g['data']['sessions'][0];
    $cancelled = $repo->confirm($repo->previewSessionChange($group, $g['version'], $session['id'], ['status' => 'cancelled', 'reason' => 'Avtalt avlysning']))['state'];
    $replacement = $repo->previewReplacement($group, $cancelled['version'], $session['id'], '2027-01-25', 'Ekstra kveld');
    $new = $repo->confirm($replacement)['state'];
    $assert(count($new['data']['sessions']) === 3 && $new['data']['sessions'][0]['id'] === $session['id'] && $new['data']['sessions'][0]['status'] === 'cancelled', 'Erstatning mistet avlysningen.');
    $reject(fn () => $repo->previewReplacement($group, $new['version'], $session['id'], '2027-01-26', 'Duplikat'));
    $assert($repo->previewPublication($period, $draft['version'], true)['errors'] === [], 'Erstatningsplan kan ikke publiseres.');

    $copy = $created[] = $repo->copyPeriod($period, $draft['version'], 'Ny kursperiode', '2027-03-01');
    $copyGroups = $repo->groups($copy); array_push($created, ...array_keys($copyGroups));
    $copyP = $repo->get($copy)['data']; $copyG = reset($copyGroups)['data'];
    $assert(get_post_status($copy) === 'draft' && $copyP['visible_from'] === null && $copyP['sales_until'] === null && !$copyP['show_as_upcoming'] && $copyP['breaks'] === [], 'Kopi beholder gamle periodevalg.');
    $assert($copyG['registration_url'] === '' && $copyG['registration_status'] === 'unknown' && $copyG['breaks'] === [] && $copyG['first_date'] === null && $copyG['latest_date'] === null, 'Kopi beholder lenker og overstyringer.');
    $assert(array_intersect(array_column($copyG['sessions'], 'id'), array_column($new['data']['sessions'], 'id')) === [] && count($copyG['sessions']) === 2, 'Kopi må ha nye ordinære økter.');
    $assert($repo->previewPublication($copy, 1, true)['errors'] !== [], 'Uferdig kopi kan publiseres.');
    $copyVersions = array_map(static fn ($g) => $g['version'], $copyGroups);
    $trash = $repo->lifecycle($copy, 1, $copyVersions, 'trash');
    $assert(get_post_status($copy) === 'trash' && get_post_status(array_key_first($copyGroups)) === 'trash', 'Papirkurven tar ikke hele kladden.');
    $restoreVersions = [];
    foreach ($copyGroups as $gid => $g) { $restoreVersions[$gid] = $repo->get($gid, 'group', true)['version']; }
    $restored = $repo->lifecycle($copy, $trash['version'], $restoreVersions, 'restore');
    $assert(get_post_status($copy) === 'draft' && $restored['event'] === 'restored', 'Gjenoppretting feiler.');

    // Individual trash must stay separate from a later whole-period trash/restore.
    $singleId = array_key_first($copyGroups); $single = $repo->get($singleId);
    $singleTrash = $repo->groupLifecycle($singleId, $single['version'], $restored['version'], false);
    $assert($repo->groups($copy) === [] && $singleTrash['event'] === 'group_trashed', 'Enkeltgruppe blir ikke fjernet fra planlegging.');
    $copyTrash = $repo->lifecycle($copy, $restored['version'], [], 'trash');
    $copyRestore = $repo->lifecycle($copy, $copyTrash['version'], [], 'restore');
    $assert(get_post_status($singleId) === 'trash', 'Periodegjenoppretting gjenopplivet en tidligere slettet enkeltgruppe.');
    $repo->groupLifecycle($singleId, $singleTrash['version'], $copyRestore['version'], true);
    $assert(get_post_status($singleId) === 'draft', 'Enkeltgruppe kan ikke gjenopprettes eksplisitt.');
    $reject(fn () => $repo->groupLifecycle($group, $repo->get($group)['version'], $draft['version'], false), 403);
    $assert($repo->salesStatus($group) === 'hidden', 'Kladd skal aldri åpne påmeldingshandling.');

    // A genuinely separate database connection holds the same global writer lock.
    global $wpdb;
    $competitor = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $lockName = 'rnl:' . md5(DB_NAME . ':' . $wpdb->prefix);
    $assert((int) $competitor->get_var($competitor->prepare('SELECT GET_LOCK(%s, 0)', $lockName)) === 1, 'Kunne ikke sette opp konkurrerende lås.');
    $lockedBefore = $repo->get($period);
    try { $reject(fn () => $repo->update($period, $lockedBefore['version'], $lockedBefore['data']), 409); }
    finally { $competitor->get_var($competitor->prepare('SELECT RELEASE_LOCK(%s)', $lockName)); $competitor->close(); }
    $assert($repo->get($period) === $lockedBefore, 'Konkurrerende skriver omgår global lås.');

    // NULL means a database failure, not another editor. Never run the write anyway.
    $brokenLock = static fn ($sql) => str_contains($sql, 'SELECT GET_LOCK(') ? 'SELECT NULL' : $sql;
    add_filter('query', $brokenLock);
    try { $reject(fn () => $repo->update($period, $lockedBefore['version'], $lockedBefore['data']), 503); }
    finally { remove_filter('query', $brokenLock); }
    $assert($repo->get($period) === $lockedBefore && !Mutation::active(), 'Databasefeil endret kursdata eller etterlot aktiv mutasjon.');
    $cleanupFailure = static function ($id) use ($period): void { if ((int) $id === $period) throw new RuntimeException('Synthetic cache hook failure'); };
    add_action('clean_post_cache', $cleanupFailure);
    try { $reject(static function () use ($period): void { Mutation::run(static function () use ($period): void { Mutation::touch($period); }); }); }
    finally { remove_action('clean_post_cache', $cleanupFailure); }
    $assert(!Mutation::active() && (int) $wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', $lockName)) === 1, 'Oppryddingsfeil etterlot kurslåsen eller aktiv mutasjon.');

    // An overlapping draft in another period must block publication too.
    $conflictPeriod = $created[] = $repo->create('period', $p);
    $conflictGroup = $created[] = $repo->createGroup($conflictPeriod, $course, ['weekday' => 1, 'start_time' => '18:30', 'end_time' => '19:30', 'instructor_ids' => [$instructor], 'registration_status' => 'closed']);
    $repo->confirm($repo->previewGroup($conflictGroup, 1, []));
    $assert(str_contains(implode(' ', $repo->previewPublication($period, $draft['version'], true)['errors']), 'kollisjon'), 'Kollisjoner på tvers av perioder slipper gjennom.');
    $repo->lifecycle($conflictPeriod, 1, [$conflictGroup => 2], 'trash');

    $g = $repo->get($group);
    $badUrl = $repo->confirm($repo->previewGroup($group, $g['version'], ['registration_url' => 'https://letsreg.com.evil.example/event']))['state'];
    $assert(str_contains(implode(' ', $repo->previewPublication($period, $draft['version'], true)['errors']), 'LetsReg'), 'URL-suffiks kunne omgå godkjent vert.');
    // Form data uses local time and exact minor units, and rejects raw protected fields.
    $form = $p; $form['default_price_minor'] = '123,45'; $form['visible_from'] = '2027-01-01T12:00'; $form['visible_until'] = '2027-02-01T12:00';
    $form['sales_from'] = '2027-01-01T12:00'; $form['sales_until'] = '2027-01-10T12:00'; unset($form['cancelled']);
    foreach ($form as $key => $value) { if (is_int($value)) { $form[$key] = (string) $value; } }
    $parsed = CourseActions::parse('period', $form, []);
    $assert($parsed['default_price_minor'] === 12345 && $parsed['visible_from'] === '2027-01-01T11:00:00Z' && !$parsed['cancelled'], 'Skjema konverterer pris eller lokal tid feil.');
    $badForm = $form; $badForm['visible_from'] = '2027-03-28T02:30'; $reject(fn () => CourseActions::parse('period', $badForm, []));
    $reject(fn () => CourseActions::parse('group', ['sessions' => []], $g['data']));
    $reject(fn () => $actions->handle(['command' => 'save', 'kind' => 'period', 'id' => (string) $ordinary, 'version' => '1', '_wpnonce' => wp_create_nonce('rnl_course_command'), 'data' => $form]), 404);

    $_SERVER['REQUEST_METHOD'] = 'GET'; $_GET = ['page' => 'reginor-lite', 'period' => $period];
    ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Kurs i perioden') && str_contains($html, 'Gå til publisering'), 'Kursansvarlig får ikke arbeidsflaten.');
    $_GET = ['page' => 'rnl-resources']; ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'krever administratorrettigheter') && !str_contains($html, 'private_salary'), 'Administratorressurser er eksponert.');
    // Core routes and capabilities must deny editing ordinary content and raw RegiNor metadata.
    $assert(!current_user_can('edit_post', $ordinary) && !current_user_can('edit_post', $instructor) && !current_user_can('edit_post_meta', $group, ContentTypes::META), 'Objekttilgang er for vid.');
    $request = new WP_REST_Request('POST', '/wp/v2/pages/' . $ordinary); $request->set_param('title', 'Forbudt');
    $assert(rest_get_server()->dispatch($request)->get_status() === 403, 'Direkte REST-sideendring er tillatt.');
    $routes = rest_get_server()->get_routes(); $assert(!isset($routes['/wp/v2/rnl_groups']), 'Generisk kurs-REST er eksponert.');
    // Publication feedback links to the exact course fields, and inline text editing stays scoped.
    wp_set_current_user($admin);
    $textCourse = $created[] = $repo->create('course', ['title' => 'Manglende nivå', 'description' => 'Beskrivelsen finnes allerede.', 'level_description' => '', 'dance_style' => 'Salsa', 'partner_info' => 'Valgfritt']);
    $textPeriod = $created[] = $repo->create('period', $p);
    $textGroup = $created[] = $repo->createGroup($textPeriod, $textCourse, ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '19:00']);
    $textPreview = $repo->previewPublication($textPeriod, 1, true);
    $assert(array_column($textPreview['field_errors'], 'field') === ['level_description', 'registration_status'], 'Kontrollen hevder at en utfylt beskrivelse mangler.');
    ob_start(); (new ReflectionMethod(CoursePage::class, 'publication'))->invoke(new CoursePage(), $textPreview); $textHtml = ob_get_clean();
    $assert(str_contains($textHtml, '#rnl-course-description') && str_contains($textHtml, '#rnl-course-registration') && str_contains($textHtml, 'group=' . $textGroup), 'Publiseringsfeil mangler direktelenker til riktige kursfelt.');
    $sharedGroup = $created[] = $repo->createGroup($copy, $textCourse, ['weekday' => 3, 'start_time' => '18:00', 'end_time' => '19:00']);
    $_GET = ['page' => 'reginor-lite', 'period' => $textPeriod, 'group' => $textGroup];
    ob_start(); CoursePage::render(); $textHtml = ob_get_clean();
    $assert(str_contains($textHtml, 'value="save_course_description"') && str_contains($textHtml, 'name="data[level_description]"') && str_contains($textHtml, 'name="data[description]"'), 'Kurssiden mangler tekstfeltene som publisering krever.');
    $assert(str_contains($textHtml, 'brukes av 2 kurs') && str_contains($textHtml, 'id="rnl-course-registration"'), 'Felles beskrivelser eller påmeldingsfelt er ikke tydelig merket.');
    $textCommand = ['command' => 'save_course_description', 'id' => (string) $textGroup, 'version' => '1', 'course_version' => '1', '_wpnonce' => wp_create_nonce('rnl_course_command'),
        'data' => ['description' => 'Lær nye turer.', 'level_description' => 'Du bør ha fullført nybegynnerkurset.']];
    $beforeTextGroup = $repo->get($textGroup);
    $reject(fn () => $actions->handle(array_replace($textCommand, ['_wpnonce' => 'invalid'])), 403);
    $reject(fn () => $actions->handle(array_replace($textCommand, ['version' => '999'])), 409);
    $reject(fn () => $actions->handle(array_replace($textCommand, ['data' => $textCommand['data'] + ['title' => 'Ulovlig endring']])));
    $textResult = $actions->handle($textCommand);
    $assert($textResult['id'] === $textPeriod && $textResult['group'] === $textGroup && $repo->get($textGroup) === $beforeTextGroup, 'Tekstlagring mister kurskontekst eller endrer kursplanen.');
    $savedText = $repo->get($textCourse);
    $assert($savedText['data']['level_description'] === $textCommand['data']['level_description'] && $savedText['data']['title'] === 'Manglende nivå' && $savedText['data']['partner_info'] === 'Valgfritt', 'Tekstlagring mister felt eller lagrer ikke nivået.');
    $reject(fn () => $actions->handle($textCommand), 409);
    $assert(array_column($repo->previewPublication($textPeriod, 1, true)['field_errors'], 'field') === ['registration_status'], 'Rettet nivå forblir feil i kursrekkens publiseringskontroll.');
    wp_set_current_user($users[0]);
    $textCommand['_wpnonce'] = wp_create_nonce('rnl_course_command');
    $reject(fn () => $actions->handle($textCommand), 403);
    ob_start(); CoursePage::render(); $textHtml = ob_get_clean();
    $assert(str_contains($textHtml, 'Lær nye turer.') && str_contains($textHtml, 'Be en administrator') && !str_contains($textHtml, 'value="save_course_description"'), 'Kursansvarlig ser ikke tekstene eller får adminskjema.');

    wp_set_current_user($users[2]);
    $reject(fn () => $actions->handle(['command' => 'copy', '_wpnonce' => wp_create_nonce('rnl_course_command')]), 403);
    wp_set_current_user(0); $reject(fn () => $repo->previewPublication($period, $draft['version'], true), 403);
    $assert(get_post($ordinary)->to_array() === $before, 'Uvedkommende innhold er endret.');
} finally {
    wp_set_current_user($admin);
    if ($failure) { remove_filter('wp_insert_post_empty_content', $failure, 20); }
    remove_filter('rnl_instructor_post_types', $types);
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ($users as $user) { wp_delete_user($user); }
    wp_set_current_user($originalUser);
}
WP_CLI::success('Arbeidsflytkontroller bestått: ' . $checks . '. Testobjekter er ryddet bort.');
