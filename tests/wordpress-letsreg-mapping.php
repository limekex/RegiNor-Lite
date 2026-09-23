<?php
use RegiNor\Lite\Infrastructure\LetsRegConnection as Connection;
use RegiNor\Lite\Infrastructure\LetsRegMapping as Mapping;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\StateSchema;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Admin\CourseActions;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Admin\LetsRegCoursePanel;
use RegiNor\Lite\Admin\LetsRegPage;
use RegiNor\Lite\Admin\LetsRegCoursePicker;
use RegiNor\Lite\Infrastructure\LetsRegEventInspection;
use RegiNor\Lite\Frontend\Catalog;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$env = [];
foreach (['AFFILIATE_ID', 'ORGANIZER_ID', 'USERNAME', 'PASSWORD', 'CLIENT_ID'] as $key) {
    $name = 'RNL_LETSREG_' . $key;
    if (defined($name)) { throw new RuntimeException('Testen krever miljøvariabler uten konstante API-verdier.'); }
    $env[$name] = getenv($name);
}
$old = get_option(Connection::OPTION, null); $oldUser = get_current_user_id(); $oldGet = $_GET;
$created = []; $users = []; $calls = 0; $checks = 0; $mode = 'ok';
$assert = static function ($ok, $message) use (&$checks) { ++$checks; if (!$ok) throw new RuntimeException($message); };
$reject = static function (callable $operation, int $code = 0) use ($assert) {
    try { $operation(); } catch (Throwable $error) { $assert(!$code || $error->getCode() === $code, 'Unexpected rejection: ' . $error->getMessage()); return; }
    $assert(false, 'Invalid mapping operation was accepted');
};
$http = static function ($pre, $args, $url) use (&$calls, &$mode) {
    ++$calls;
    $data = str_starts_with($url, Connection::ORIGIN . '/organizers/42/events?') ? [
        ['id' => 12345, 'organizer' => ['id' => 42, 'affiliateId' => 7], 'name' => 'Salsa date search',
            'active' => true, 'published' => true, 'isCancelled' => false, 'startDate' => '2031-01-05T23:30:00Z'],
        ['id' => 12346, 'organizer' => ['id' => 42, 'affiliateId' => 7], 'name' => 'Missing date', 'active' => true, 'published' => true, 'isCancelled' => false],
    ] : match ($url) {
        Connection::TOKEN_URL => ['access_token' => 'synthetic.mapping.token', 'token_type' => 'bearer', 'expires_in' => 3600],
        Connection::ORIGIN . '/organizers/42' => ['id' => 42, 'affiliateId' => 7, 'name' => 'Mapping test organizer'],
        Connection::ORIGIN . '/events/12345' => ['id' => 12345, 'organizer' => ['id' => $mode === 'foreign_event' ? 99 : 42, 'affiliateId' => 7],
            'startDate' => '2031-01-06T18:30:00+01:00', 'endDate' => '2031-02-10T20:00:00+01:00',
            'registrationStartDate' => '2030-12-01T00:00:00Z', 'registrationEndDate' => '2031-01-12T22:00:00Z',
            'name' => 'Mapping test event', 'eventUrl' => 'https://www.letsreg.com/no/register/SalsaØvet1_4_26', 'active' => $mode !== 'inactive_event', 'published' => true, 'isCancelled' => $mode === 'cancelled'],
        Connection::ORIGIN . '/events/12345/prices' => array_map(static fn ($id) => ['id' => $id, 'price' => $id === 13 ? 750.5 : 950, 'name' => '<b>Mapping category ' . $id . '</b>', 'active' => $id !== 15], range(11, 15)),
        default => throw new RuntimeException('Unexpected HTTP request intercepted'),
    };
    return ['response' => ['code' => 200], 'headers' => ['content-type' => 'application/json'], 'body' => wp_json_encode($data)];
};
$ready = static function () { delete_option(Connection::OPTION); return Connection::check(12345); };
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$repo = new CourseRepository(); $actions = new CourseActions($repo);
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
try {
    wp_set_current_user($admin);
    foreach (['AFFILIATE_ID' => '7', 'ORGANIZER_ID' => '42', 'USERNAME' => 'mapping@example.invalid', 'PASSWORD' => 'synthetic-only', 'CLIENT_ID' => ''] as $key => $value) putenv('RNL_LETSREG_' . $key . '=' . $value);
    $ready(); $source = Mapping::source();
    $namedSource = ['event' => ['prices' => []]];
    foreach (['Rueda Videregående som fører', 'Rueda Videregående som følger', 'Parpåmelding Rueda som fører', 'Parpåmelding Rueda som følger', 'Fører eller følger', 'Parpåmelding som fører'] as $index => $name) {
        $namedSource['event']['prices'][] = ['id' => $index + 1, 'name' => $name, 'active' => $index !== 5];
    }
    $renderChoices = static function (array $selected = []) use ($namedSource): array {
        ob_start(); LetsRegCoursePanel::categoryFields($namedSource, $selected); $html = ob_get_clean();
        $tags = new WP_HTML_Tag_Processor($html); $values = []; $name = '';
        while ($tags->next_tag()) {
            if ($tags->get_tag() === 'SELECT') { $name = $tags->get_attribute('name'); }
            if ($tags->get_tag() === 'OPTION' && $tags->get_attribute('selected') !== null) { $values[$name] = $tags->get_attribute('value'); }
        }
        return [$html, $values];
    };
    [$suggestionHtml, $values] = $renderChoices();
    foreach ([1 => ['leader', 'single'], 2 => ['follower', 'single'], 3 => ['leader', 'pair'], 4 => ['follower', 'pair']] as $id => [$role, $form]) {
        $assert($values["data[categories][$id][role]"] === $role && $values["data[categories][$id][registration]"] === $form, 'Category name suggestion missing');
    }
    $assert($values['data[categories][5][role]'] === '' && !isset($values['data[categories][6][role]']), 'Ambiguous or inactive category auto-selected');
    $assert(str_contains($suggestionHtml, 'Forslag fra kategorinavnet') && str_contains($suggestionHtml, 'aria-describedby="rnl-category-1-suggestion"'), 'Suggestion not explained accessibly');
    [$savedHtml, $savedValues] = $renderChoices([1 => ['role' => 'open', 'registration' => 'pair']]);
    $assert($savedValues['data[categories][1][role]'] === 'open' && $savedValues['data[categories][1][registration]'] === 'pair', 'Saved manual choice replaced');
    $assert($savedValues['data[categories][2][role]'] === '' && str_contains($savedHtml, 'Forslag fra kategorinavnet'), 'Excluded category re-added or its suggestion hidden when opening a saved mapping');
    [$partialHtml, $partialValues] = $renderChoices([3 => ['role' => 'leader', 'registration' => 'pair']]);
    $assert(substr_count($partialHtml, 'data-category-role=') === 4, 'One saved category suppresses suggestions for the remaining three');
    $assert(str_contains($partialHtml, 'data-fill-category-suggestions') && str_contains($partialHtml, 'Tidligere kategorivalg er beholdt'), 'Missing explanation and explicit way to fill remaining categories');
    $assert($partialValues['data[categories][3][role]'] === 'leader' && $partialValues['data[categories][3][registration]'] === 'pair' && $partialValues['data[categories][4][role]'] === '', 'Opening partial mapping silently changes saved choices');
    [, $stickyValues] = $renderChoices([1 => ['role' => '', 'registration' => 'single']]);
    $assert($stickyValues['data[categories][1][role]'] === '' && $stickyValues['data[categories][3][role]'] === '', 'Explicitly omitted categories replaced after validation error');
    $invalidDates = LetsRegEventInspection::event(['id' => 9, 'name' => 'Test', 'organizer' => ['id' => 42, 'affiliateId' => 7], 'active' => true, 'published' => true, 'isCancelled' => false, 'startDate' => '2030-02-30T18:00:00Z', 'endDate' => 'tomorrow', 'registrationStartDate' => ['bad']], 9, 42, 7);
    $assert(!isset($invalidDates['startDate'], $invalidDates['endDate'], $invalidDates['registrationStartDate']), 'Malformed optional provider dates accepted');
    foreach ([-1, '950', 950.123, INF, 1000001, null] as $badAmount) {
        $prices = LetsRegEventInspection::prices([['id' => 1, 'name' => 'Test', 'active' => true, 'price' => $badAmount]]);
        $assert(!isset($prices[0]['price_minor']), 'Unverified price format converted to amount');
    }

    $choices = [11 => ['role' => 'leader', 'registration' => 'single'], 12 => ['role' => 'follower', 'registration' => 'single'],
        13 => ['role' => 'leader', 'registration' => 'pair'], 14 => ['role' => 'follower', 'registration' => 'pair']];
    $mapping = Mapping::build(12345, $source['verification_id'], $choices);
    $assert(array_column($mapping['categories'], 'participants_per_selection') === [1, 1, 1, 1], 'Pair category doubles participants');
    $assert($mapping['event_id'] === 12345 && $mapping['organizer_id'] === 42 && $mapping['affiliate_id'] === 7, 'Mapping ownership missing');
    $assert($mapping['categories'][0]['name'] === 'Mapping category 11', 'Untrusted name retained');
    $assert(!str_contains(wp_json_encode($mapping), 'synthetic') && !str_contains(wp_json_encode($mapping), 'fingerprint'), 'Mapping leaks credential material');
    $beforeCalls = $calls;
    foreach ([[], [99 => ['role' => 'leader', 'registration' => 'single']], [15 => ['role' => 'leader', 'registration' => 'single']],
        [11 => ['role' => '', 'registration' => 'single']], [11 => ['role' => 'pair', 'registration' => 'single']],
        [11 => ['role' => 'leader', 'registration' => 'bundle']], [11 => ['role' => 'leader']],
        [11 => ['role' => 'leader', 'registration' => 'single', 'name' => 'forged']]] as $invalid) {
        $reject(static fn () => Mapping::build(12345, $source['verification_id'], $invalid));
    }
    $reject(static fn () => Mapping::build(12346, $source['verification_id'], $choices), 409);
    $reject(static fn () => Mapping::build(12345, wp_generate_uuid4(), $choices), 409);
    $open = Mapping::build(12345, $source['verification_id'], [11 => ['role' => 'open', 'registration' => 'single']]);
    $assert($open['categories'][0]['role'] === 'open', 'Courses without partner roles not supported');
    $venue = $created[] = $repo->create('venue', ['title' => 'Mapping test venue', 'address' => 'Test']);
    $room = $created[] = $repo->create('room', ['title' => 'Mapping test room', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Mapping test course', 'description' => 'Synthetic course description', 'level_description' => 'Synthetic level description', 'dance_style' => 'Salsa', 'partner_info' => '']);
    $period = $created[] = $repo->create('period', ['title' => 'Mapping test period', 'timezone' => 'Europe/Oslo', 'start_date' => '2031-01-06',
        'default_session_count' => 2, 'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person',
        'visible_from' => '2020-01-01T00:00:00Z', 'visible_until' => '2032-01-01T00:00:00Z', 'sales_from' => '2030-01-01T00:00:00Z', 'sales_until' => '2032-01-01T00:00:00Z',
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []]);
    $fields = ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'later'];
    $reject(static fn () => $repo->createGroup($period, $course, $fields + ['letsreg_mapping' => $mapping]));
    $group = $created[] = $repo->createGroup($period, $course, $fields);
    $repo->confirm($repo->previewGroup($group, 1, []));
    $initial = $repo->get($group);
    $reject(static fn () => $repo->previewGroup($group, $initial['version'], ['letsreg_mapping' => $mapping]));
    $reject(static fn () => CourseActions::parse('group', ['letsreg_mapping' => $mapping], $initial['data']));
    $bad = $mapping; $bad['categories'][1]['id'] = 11;
    $reject(static fn () => StateSchema::validate('group', $initial['data'] + ['letsreg_mapping' => $bad]));
    $bad['categories'][1]['id'] = 12; $bad['categories'][2]['participants_per_selection'] = 2;
    $reject(static fn () => StateSchema::validate('group', $initial['data'] + ['letsreg_mapping' => $bad]));
    $bad = $mapping; $bad['event_name'] = 'Forged event';
    $reject(static fn () => $repo->previewLetsRegMapping($group, $initial['version'], $bad), 409);
    $payload = ['command' => 'preview_letsreg', 'id' => (string) $group, 'version' => (string) $initial['version'], 'mapping_action' => 'link',
        'event_id' => '12345', 'verification_id' => $source['verification_id'], 'data' => ['categories' => $choices], '_wpnonce' => wp_create_nonce('rnl_course_command')];
    $reject(static fn () => $actions->handle(array_replace($payload, ['_wpnonce' => 'invalid'])), 403);
    $proposal = $actions->handle($payload)['proposal'];
    $assert($repo->get($group) === $initial, 'Preview persists the mapping');
    $assert($proposal['data']['sessions'] === $initial['data']['sessions'] && $proposal['changes'] === [], 'Mapping replans course sessions');
    $tampered = $proposal; $tampered['data']['letsreg_mapping']['event_id'] = 999;
    $reject(static fn () => $repo->confirm($tampered), 403);
    $oldState = get_option(Connection::OPTION); $expired = $oldState; $expired['verified_until'] = time() - 1; update_option(Connection::OPTION, $expired);
    $reject(static fn () => $repo->confirm($proposal), 409);
    update_option(Connection::OPTION, $oldState);
    putenv('RNL_LETSREG_PASSWORD=changed');
    $reject(static fn () => $repo->confirm($proposal), 409);
    putenv('RNL_LETSREG_PASSWORD=synthetic-only');
    $different = $oldState; $different['event']['id'] = 12346; update_option(Connection::OPTION, $different);
    $reject(static fn () => $repo->confirm($proposal), 409); update_option(Connection::OPTION, $oldState);
    $inactive = $oldState; $inactive['event']['prices'][0]['active'] = false; update_option(Connection::OPTION, $inactive);
    $reject(static fn () => $repo->confirm($proposal)); update_option(Connection::OPTION, $oldState);
    $lostCapability = static function ($caps) { $caps['manage_options'] = false; return $caps; };
    add_filter('user_has_cap', $lostCapability);
    $reject(static fn () => $repo->confirm($proposal), 403);
    remove_filter('user_has_cap', $lostCapability);
    $result = $actions->handle(['command' => 'confirm', 'proposal' => base64_encode(wp_json_encode($proposal)), '_wpnonce' => $payload['_wpnonce']]);
    $saved = $repo->get($group);
    $assert($saved['data']['letsreg_mapping'] === $mapping && $saved['version'] === $initial['version'] + 1, 'Mapping save failed');
    $assert(end($saved['history'])['data'] === $initial['data'], 'Mapping history missing');
    $reject(static fn () => $repo->confirm($proposal), 409);
    $assert($calls === $beforeCalls, 'Mapping preview/confirmation makes external requests');
    ob_start(); LetsRegCoursePanel::render($group, $saved); $html = ob_get_clean();
    $assert(str_contains($html, 'Mapping test event') && str_contains($html, 'name="data[categories][13][registration]"') && str_contains($html, 'Parpåmelding'), 'Mapping form missing');
    $assert(!str_contains($html, 'name="data[categories][15]') && !str_contains($html, '<b>Mapping category'), 'Inactive or unsafe category selectable');
    $_GET = ['page' => 'reginor-lite', 'period' => $period, 'group' => $group];
    ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'id="rnl-letsreg-course"') && str_contains($html, 'command" value="preview_letsreg"'), 'Course page does not expose mapping form');
    $_GET = ['page' => 'rnl-letsreg', 'rnl_course' => $group];
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Tilbake til kursoppsettet') && str_contains($html, 'group=' . $group), 'Return to course link missing');
    $manager = wp_insert_user(['user_login' => 'rnl-mapping-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'rnl_course_manager']);
    if (is_wp_error($manager)) throw new RuntimeException('Could not create test account');
    $users[] = $manager;
    foreach ([0] as $user) {
        wp_set_current_user($user);
        $reject(static fn () => Mapping::source(), 403);
        $reject(static fn () => $repo->previewLetsRegMapping($group, $saved['version'], null), 403);
        $reject(static fn () => $actions->handle(array_replace($payload, ['_wpnonce' => wp_create_nonce('rnl_course_command')])), 403);
    }
    wp_set_current_user($manager);
    $assert(current_user_can('rnl_link_letsreg') && !current_user_can('manage_options'), 'Role upgrade did not delegate only course linking');
    $assert(Mapping::source() === null, 'Manager inherited another user’s checked event');
    $reject(static fn () => Connection::inspect(), 403);
    $reject(static fn () => Connection::check(), 403);
    $assert(Mapping::canImport(), 'Course manager lacks delegated import permission');
    ob_start(); LetsRegCoursePicker::render($group, $saved); $html = ob_get_clean();
    $assert(str_contains($html, 'data-rnl-letsreg-picker') && str_contains($html, 'data-search-form') && !str_contains($html, 'page=rnl-letsreg'), 'Delegated course search missing or links to admin-only settings');
    $_GET = ['page' => 'reginor-lite'];
    $oldScreen = $GLOBALS['current_screen'] ?? null;
    set_current_screen('toplevel_page_reginor-lite');
    try { do_action('admin_enqueue_scripts', 'toplevel_page_reginor-lite'); }
    finally { $GLOBALS['current_screen'] = $oldScreen; }
    $assert(wp_script_is('rnl-letsreg-picker', 'enqueued'), 'Manager search script not enqueued');
    delete_option(Connection::OPTION); // Ordinary edits must work even when provider control has expired or failed.
    $normal = $repo->confirm($repo->previewGroup($group, $saved['version'], ['price_terms' => 'Manager edit']));
    $assert($normal['state']['data']['letsreg_mapping'] === $mapping, 'Ordinary manager edit loses mapping');
    wp_set_current_user($admin);
    $copy = $created[] = $repo->copyPeriod($period, 1, 'Mapping test copy', '2031-02-03');
    foreach ($repo->groups($copy) as $gid => $row) {
        $created[] = $gid;
        $assert(empty($row['data']['letsreg_mapping']) && $row['data']['registration_url'] === '', 'Period copy retains external mapping');
        foreach ($row['history'] as $history) $assert(empty($history['data']['letsreg_mapping']), 'Copied history retains source mapping');
    }
    $v = $repo->get($group)['version'];
    $remove = $repo->previewLetsRegMapping($group, $v, null);
    $repo->confirm($repo->previewGroup($group, $v, ['price_terms' => 'Concurrent edit']));
    $reject(static fn () => $repo->confirm($remove), 409);
    $remove = $repo->previewLetsRegMapping($group, $v + 1, null);
    $periodData = $repo->get($period)['data']; $repo->update($period, 1, array_replace($periodData, ['title' => 'Concurrent period edit']));
    $reject(static fn () => $repo->confirm($remove), 409);
    $removed = $repo->confirm($repo->previewLetsRegMapping($group, $v + 1, null))['state'];
    $assert($removed['data']['letsreg_mapping'] === null && $removed['data']['registration_url'] === $saved['data']['registration_url'], 'Removal changes registration URL');
    $assert($removed['data']['sessions'] === $saved['data']['sessions'], 'Removal changes sessions');
    $assert($removed['data']['period_version'] === $saved['data']['period_version'], 'Mapping operation silently acknowledges a new period schedule');
    foreach (['inactive_event', 'cancelled'] as $mode) {
        $ready(); $source = Mapping::source();
    $invalidDates = LetsRegEventInspection::event(['id' => 9, 'name' => 'Test', 'organizer' => ['id' => 42, 'affiliateId' => 7], 'active' => true, 'published' => true, 'isCancelled' => false, 'startDate' => '2030-02-30T18:00:00Z', 'endDate' => 'tomorrow', 'registrationStartDate' => ['bad']], 9, 42, 7);
    $assert(!isset($invalidDates['startDate'], $invalidDates['endDate'], $invalidDates['registrationStartDate']), 'Malformed optional provider dates accepted');
    foreach ([-1, '950', 950.123, INF, 1000001, null] as $badAmount) {
        $prices = LetsRegEventInspection::prices([['id' => 1, 'name' => 'Test', 'active' => true, 'price' => $badAmount]]);
        $assert(!isset($prices[0]['price_minor']), 'Unverified price format converted to amount');
    }

        $reject(static fn () => Mapping::build(12345, $source['verification_id'], $choices));
    }
    $mode = 'ok'; $ready(); $source = Mapping::source();
    $mapping = Mapping::build(12345, $source['verification_id'], $choices);
    $v = $removed['version'];
    $repo->confirm($repo->previewLetsRegMapping($group, $v, $mapping));
    $repo->confirm($repo->previewGroup($group, $v + 1, []));
    $repo->publish($repo->previewPublication($period, 2, true));
    $reject(static fn () => $repo->previewLetsRegMapping($group, $repo->get($group)['version'], null), 409);
    $public = wp_json_encode((new Catalog())->read());
    $assert(str_contains($public, 'Mapping test course'), 'Public privacy test has no published course');
    $assert(!str_contains($public, 'Mapping test event') && !str_contains($public, 'Mapping category') && !str_contains($public, 'verification_id') && !str_contains($public, 'letsreg_mapping'), 'Private mapping leaks into public catalog');
    $assert(!get_post_type_object('rnl_group')->show_in_rest, 'Raw mapping exposed through REST');
    // Inline linking on a published course must preserve every field except the chosen link/mapping.
    $payload = ['nonce' => wp_create_nonce('rnl_letsreg_course'), 'id' => (string) $group, 'operation' => 'select', 'event_id' => '12345'];
    $beforeCalls = $calls;
    $reject(static fn () => LetsRegCoursePicker::dispatch(array_replace($payload, ['nonce' => 'wrong'])), 403);
    wp_set_current_user(0);
    $reject(static fn () => LetsRegCoursePicker::dispatch(array_replace($payload, ['nonce' => wp_create_nonce('rnl_letsreg_course')])), 403);
    wp_set_current_user($admin);
    $assert($calls === $beforeCalls, 'Unauthorized picker sent provider requests');
    $level = $created[] = $repo->create('level', ['title' => 'Mapping test event', 'description' => '', 'sort_order' => 10, 'active' => true]);
    wp_set_current_user($manager);
    $payload['nonce'] = wp_create_nonce('rnl_letsreg_course');
    $reject(static fn () => LetsRegCoursePicker::dispatch(array_replace($payload, ['mode' => 'import', 'id' => (string) $period])), 409);
    $deniedObject = static function ($caps, $cap, $userId, $args) use ($group) {
        return $cap === 'edit_post' && ($args[0] ?? 0) === $group ? ['do_not_allow'] : $caps;
    };
    add_filter('map_meta_cap', $deniedObject, 100, 4);
    try { $reject(static fn () => LetsRegCoursePicker::dispatch($payload), 403); }
    finally { remove_filter('map_meta_cap', $deniedObject, 100); }
    $assert($calls === $beforeCalls, 'Import/object permission denial sent provider requests');
    $search = LetsRegCoursePicker::dispatch(array_replace($payload, ['operation' => 'search', 'query' => 'Salsa', 'offset' => '0']));
    $assert(count($search['events']) === 2 && $search['events'][0]['id'] === 12345, 'Manager cannot search configured organizer');
    $groupBeforeSuggestion = $repo->get($group);
    $selected = LetsRegCoursePicker::dispatch($payload); // Immediate selection after successful manual control.
    $assert($selected['event_url'] === 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26', 'Provider public URL missing or incorrectly encoded');
    $assert(str_contains($selected['html'], 'rnl-inline-category-') && !str_contains($selected['html'], '<b>'), 'Inline categories not safely rendered');
    $suggestions = $selected['suggestions'];
    $assert(($suggestions['fields']['level_id'] ?? null) === $level, 'Picker does not use configured levels for title suggestions');
    $assert($repo->get($group) === $groupBeforeSuggestion, 'Requesting suggestions changes saved course');
    $assert($suggestions['fields']['title'] === 'Mapping test event' && $suggestions['fields']['weekday'] === '1' && $suggestions['fields']['start_time'] === '18:30' && $suggestions['fields']['end_time'] === '20:00', 'Teaching suggestions differ from provider local times');
    $assert($suggestions['fields']['registration_from'] === '2030-12-01T01:00' && $suggestions['fields']['registration_status'] === 'automatic', 'Registration dates not converted to course timezone');
    $assert($suggestions['prices'][2]['price_minor'] === 75050 && count($suggestions['prices']) === 4, 'Price suggestions include inactive categories or double pair price');
    $assert(!str_contains(wp_json_encode($selected), 'synthetic.mapping.token') && !str_contains(wp_json_encode($selected), 'synthetic-only') && !str_contains(wp_json_encode($selected), 'fingerprint'), 'Delegated response leaks credentials');
    wp_set_current_user($admin);
    $assert(Mapping::source() === null, 'Another user can reuse the manager selection');
    wp_set_current_user($manager);
    $before = $repo->get($group); $parentBefore = $repo->get($period); $beforeCalls = $calls;
    $save = ['nonce' => $payload['nonce'], 'id' => (string) $group, 'operation' => 'save', 'version' => (string) $before['version'],
        'event_id' => '12345', 'verification_id' => $selected['verification_id'], 'data' => ['categories' => $choices], 'use_api_url' => '1', 'registration_url' => 'https://evil.example/forged'];
    $reply = LetsRegCoursePicker::dispatch($save); $after = $repo->get($group);
    $assert(get_post_status($group) === 'publish' && get_post_status($period) === 'publish' && $repo->get($period) === $parentBefore, 'Inline mapping requires republication or changes parent period');
    $expected = $before['data']; $expected['letsreg_mapping'] = $after['data']['letsreg_mapping']; $expected['registration_url'] = $selected['event_url'];
    $assert($after['data'] === $expected && $reply['registration_url'] === $selected['event_url'], 'Inline save changes unrelated data or trusts posted URL');
    $assert($calls === $beforeCalls && end($after['history'])['data'] === $before['data'], 'Inline save sends HTTP or loses history');
    $reject(static fn () => LetsRegCoursePicker::dispatch($save), 409);
    $stale = get_option(Connection::OPTION); $stale['verified_until'] = time() - 1; update_option(Connection::OPTION, $stale);
    $save['version'] = (string) $after['version'];
    $reject(static fn () => LetsRegCoursePicker::dispatch($save), 409);
    $reply = LetsRegCoursePicker::dispatch(['nonce' => $payload['nonce'], 'id' => (string) $group, 'operation' => 'remove', 'version' => (string) $after['version']]);
    $removed = $repo->get($group);
    $assert(!$reply['linked'] && $removed['data']['registration_url'] === $selected['event_url'] && get_post_status($group) === 'publish', 'Remove changes URL or publication status');
    $sample = ['id' => 1, 'name' => 'URL test', 'organizer' => ['id' => 42, 'affiliateId' => 7], 'active' => true, 'published' => true, 'isCancelled' => false];
    foreach (['http://www.letsreg.com/event/a', 'https://evil.example/event/a', 'https://www.letsreg.com:8443/a', 'https://user:secret@letsreg.com/a', 'javascript:alert(1)', null, ['invalid']] as $badUrl) {
        $assert(!isset(LetsRegEventInspection::event($sample + ['eventUrl' => $badUrl], 1, 42, 7)['event_url']), 'Invalid provider URL offered for autofill');
    }
    ob_start(); \RegiNor\Lite\Admin\LetsRegCoursePicker::render($group, $removed); $inline = ob_get_clean();
    $assert(str_contains($inline, 'data-rnl-letsreg-picker') && str_contains($inline, 'use_api_url') && str_contains($inline, 'data-url-difference'), 'Picker or explicit URL replacement choice missing');
    delete_option(Connection::OPTION);
    $search = LetsRegCoursePicker::dispatch(['nonce' => $payload['nonce'], 'id' => (string) $group, 'operation' => 'search', 'query' => 'Salsa', 'offset' => '0']);
    $assert($search['events'][0]['start_on'] === '2031-01-06' && $search['events'][0]['start_label'] === '06.01.2031', 'Search start date does not use period timezone at midnight boundary');
    $assert($search['events'][1]['start_on'] === null && $search['events'][1]['start_label'] === null, 'Missing provider date is fabricated');
    $assert($search['period'] === ['start' => '2031-01-06', 'end' => null], 'Search does not use the actual saved course period window');
    $assert(str_contains($inline, 'name="period_only" checked') && str_contains($inline, 'Kun treff i valgt kursperiode'), 'Course search lacks single default-enabled period toggle');
    $beforeCalls = $calls;
    putenv('RNL_LETSREG_ORGANIZER_ID');
    $reject(static fn () => LetsRegCoursePicker::dispatch($payload), 400);
    putenv('RNL_LETSREG_ORGANIZER_ID=42');
    $assert($calls === $beforeCalls, 'Missing administrator setup sent external requests');
    $throttled = get_option(Connection::OPTION); $throttled['request_window'] = time(); $throttled['request_count'] = 10;
    update_option(Connection::OPTION, $throttled, false);
    $reject(static fn () => LetsRegCoursePicker::dispatch($payload), 429);
    $assert($calls === $beforeCalls, 'Delegated lookup bypasses account rate limit');
    delete_option(Connection::OPTION); $mode = 'foreign_event';
    $reject(static fn () => LetsRegCoursePicker::dispatch($payload), 503);
    $assert(Mapping::source() === null, 'Foreign organizer event is available for mapping');
    $assert($repo->get($group) === $removed, 'Failed lookup changed the course');
} finally {
    if (isset($lostCapability)) remove_filter('user_has_cap', $lostCapability);
    remove_filter('pre_http_request', $http, PHP_INT_MAX);
    if ($old === null) delete_option(Connection::OPTION); else update_option(Connection::OPTION, $old, false);
    foreach ($env as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
    wp_set_current_user($admin);
    foreach (array_reverse($created) as $id) wp_delete_post($id, true);
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ($users as $id) wp_delete_user($id);
    wp_set_current_user($oldUser); $_GET = $oldGet;
}
WP_CLI::success("$checks LetsReg mapping checks passed; no external requests sent.");
