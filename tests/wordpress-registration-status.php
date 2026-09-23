<?php
use RegiNor\Lite\Infrastructure\LetsRegConnection as Connection;
use RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore as Store;
use RegiNor\Lite\Infrastructure\RegistrationState;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\LetsRegMapping;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$env = []; $created = []; $keys = []; $checks = 0; $calls = 0; $oneTokenCalls = 0; $mode = 'ok'; $remaining = 10;
$manager = 0; $original = get_current_user_id(); $oldConnection = get_option(Connection::OPTION, null); $oldPoll = get_option('rnl_letsreg_poll', null);
$assert = static function ($ok, $message) use (&$checks) { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$http = static function ($pre, $args, $url) use (&$calls, &$mode, &$remaining, &$oneTokenCalls) {
    if (Mutation::active()) { throw new RuntimeException('API call under course lock'); }
    ++$calls;
    $status = $mode === '401' ? 401 : ($mode === '429' ? 429 : 200);
    if ($mode === 'one_token_only' && $url === Connection::TOKEN_URL && ++$oneTokenCalls > 1) { $status = 401; }
    $event = ['id' => 12345, 'name' => 'Synthetic automatic status', 'organizer' => ['id' => 42, 'affiliateId' => 7],
        'active' => true, 'published' => true, 'isCancelled' => $mode === 'cancelled', 'isArchived' => false, 'hasWaitinglist' => $mode === 'waiting',
        'registrationStartDate' => gmdate('Y-m-d\TH:i:s\Z', time() + ($mode === 'later' ? 3600 : -3600)),
        'registrationEndDate' => gmdate('Y-m-d\TH:i:s\Z', time() + ($mode === 'closed' ? -60 : 7200)),
        'maxAllowedRegistrations' => 20, 'registeredParticipants' => 20 - $remaining, 'availableRegistrations' => $remaining, 'eventUrl' => 'https://www.letsreg.com/event/status-test'];
    $body = match ($url) {
        Connection::TOKEN_URL => ['access_token' => 'synthetic.status.token', 'token_type' => 'bearer', 'expires_in' => 3600],
        Connection::ORIGIN . '/organizers/42' => ['id' => 42, 'affiliateId' => 7, 'name' => 'Synthetic organizer'],
        Connection::ORIGIN . '/events/12345' => $event,
        Connection::ORIGIN . '/events/12346' => array_replace($event, ['id' => 12346]),
        Connection::ORIGIN . '/events/12345/prices', Connection::ORIGIN . '/events/12346/prices' => [['id' => 11, 'name' => 'Fører', 'active' => true, 'available' => $remaining, 'registered' => 0, 'price' => 100,
            'availableFrom' => null, 'availableTill' => null]],
        default => throw new RuntimeException('Unexpected external URL intercepted'),
    };
    if ($url === Connection::TOKEN_URL && $mode === 'unbounded_token') { unset($body['expires_in']); }
    return ['response' => ['code' => $status], 'headers' => ['content-type' => 'application/json', 'retry-after' => '700'], 'body' => wp_json_encode($body)];
};
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$repo = new CourseRepository();
$ready = static function () { delete_option(Connection::OPTION); return Connection::check(12345, null, true); };
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
try {
    wp_set_current_user($admin);
    foreach (['AFFILIATE_ID' => '7', 'ORGANIZER_ID' => '42', 'USERNAME' => 'status@example.invalid', 'PASSWORD' => 'synthetic-only', 'CLIENT_ID' => ''] as $key => $value) {
        $name = 'RNL_LETSREG_' . $key;
        if (defined($name)) { throw new RuntimeException('Testen krever miljøvariabler uten konstante credentials.'); }
        $env[$name] = getenv($name); putenv($name . '=' . $value);
    }
    delete_option('rnl_letsreg_poll');
    $identity = Connection::identity(); $key = 'rnl_av_' . hash('sha256', $identity['fingerprint'] . ':12345'); $keys[] = $key;
    $ready();
    $mapping = LetsRegMapping::build(12345, LetsRegMapping::source()['verification_id'], [11 => ['role' => 'leader', 'registration' => 'single']]);
    $keys[] = 'rnl_av_' . hash('sha256', $identity['fingerprint'] . ':12346');
    $mode = 'one_token_only'; $before = $calls; delete_option('rnl_letsreg_poll');
    Connection::pollBatch([12345, 12346]);
    $assert($calls === $before + 7 && $oneTokenCalls === 1, 'Polling batch must reuse one valid token across event checks');
    $assert(get_option('rnl_letsreg_poll')['error'] === null, 'Repeated token issuance broke second event');
    $mode = 'ok'; $before = $calls;
    Connection::pollBatch([12345]);
    $assert($calls === $before + 4, 'Token survived beyond its bounded polling batch');
    delete_option('rnl_letsreg_poll');
    $mode = 'unbounded_token'; $before = $calls; Connection::pollBatch([12345, 12346]);
    $assert($calls === $before + 4, 'Token with unknown lifetime was reused or issued twice in a batch');
    $assert(get_option('rnl_letsreg_poll')['next_at'] >= time() + 55, 'Unknown token lifetime does not defer next event');
    $mode = 'ok'; delete_option('rnl_letsreg_poll');

    $venue = $created[] = $repo->create('venue', ['title' => 'Status test venue', 'address' => 'Testgata 1']);
    $room = $created[] = $repo->create('room', ['title' => 'Status test room', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Status test description', 'description' => 'Test', 'level_description' => 'Test', 'dance_style' => 'Salsa', 'partner_info' => '', 'audience' => 'mixed']);
    $date = new DateTimeImmutable('next monday', new DateTimeZone('Europe/Oslo'));
    $period = $created[] = $repo->create('period', ['title' => 'Status test period', 'timezone' => 'Europe/Oslo', 'start_date' => $date->format('Y-m-d'),
        'default_session_count' => 3, 'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person',
        'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'visible_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400 * 90),
        'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400 * 90),
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []]);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'automatic', 'registration_url' => 'https://www.letsreg.com/event/status-test']);
    $repo->saveLetsRegMapping($group, 1, $mapping);
    $repo->confirm($repo->previewGroup($group, $repo->get($group)['version'], []));
    $repo->publish($repo->previewPublication($period, 1, true));
    $p = $repo->get($period)['data']; $g = $repo->get($group)['data']; $saved = $repo->get($group);
    foreach (['ok' => 'available', 'later' => 'later', 'closed' => 'closed', 'cancelled' => 'cancelled', 'full' => 'full', 'waiting' => 'waiting'] as $mode => $expected) {
        $remaining = in_array($mode, ['full', 'waiting'], true) ? 0 : 10;
        $ready(); $before = $calls;
        $read = (new Catalog())->read(); $view = $read['groups'][$group];
        $assert($view['status'] === $expected && $repo->salesStatus($group) === $expected, 'Backend/frontend status mismatch: ' . $mode);
        $html = (new Renderer())->render($read, ['rnl_course' => $group]);
        $assert(str_contains($html, 'Fullt - Venteliste aktiv') === ($expected === 'waiting'), 'Waitlist badge shown without full event and enabled waitlist');
        $assert(($view['registration_url'] !== '') === in_array($expected, ['available', 'waiting'], true), 'Wrong signup action: ' . $mode);
        $assert($calls === $before && $repo->get($group) === $saved, 'Public read calls API or polling overwrites course');
        $assert($read['expires_at'] === null || $read['expires_at'] > time() + Store::TTL, 'Freshness deadline still expires the whole page');
    }
    // Pair categories render once, including unlimited and last-known (old) observations.
    $pairRows = [['name'=>'Fører i par','role'=>'leader','registration'=>'pair','status'=>'unlimited','available'=>null],
        ['name'=>'Følger i par','role'=>'follower','registration'=>'pair','status'=>'unlimited','available'=>null]];
    ob_start(); \RegiNor\Lite\Frontend\CategoryCapacityPresenter::render(['categories'=>$pairRows,'checked_at'=>time()-9000,'expires_at'=>null], false, time()); $pairHtml=ob_get_clean();
    $assert(substr_count($pairHtml,'<li>')===1 && str_contains($pairHtml,'∞') && str_contains($pairHtml,'aria-label="Ubegrenset kapasitet"') && str_contains($pairHtml,'Siste kjente kapasitet'),'Pair grouping, infinity accessibility or last-known disclaimer missing');
    $mode = 'ok'; $remaining = 10; $ready();
    $read = (new Catalog())->read();
    $assert(($read['groups'][$group]['capacity']['categories'][0]['available'] ?? null) === 10, 'Category count missing from public projection');
    $assert(!isset($read['groups'][$group]['capacity']['categories'][0]['reported_available']) && !isset($read['groups'][$group]['capacity']['categories'][0]['reported_registered']), 'Raw category observation leaks into public projection');
    foreach ([['rnl_period' => $period], ['rnl_period' => $period, 'rnl_view' => 'week'], ['rnl_course' => $group]] as $query) {
        $html = (new Renderer())->render($read, $query);
        $assert(isset($query['rnl_course']) ? (str_contains($html, 'Opptil 10 plasser') && str_contains($html, 'Ledige plasser per kategori') && str_contains($html, 'Tall fra LetsReg')) : (!str_contains($html, 'Ledige plasser per kategori') && str_contains($html, 'Se kurset')), 'Capacity details must appear only on the course profile');
    }
    ob_start(); \RegiNor\Lite\Admin\LetsRegCapacityPanel::render($g); $panel = ob_get_clean();
    $assert(Store::view($g, new DateTimeImmutable())['categories'][0]['reported_registered'] === 0 && str_contains($panel, 'Påmeldte hos LetsReg'), 'Registered count missing or conflated with availability');
    $assert(str_contains($panel, 'Rapportert ledig hos LetsReg') && str_contains($panel, 'Opptil 10 plasser'), 'Live capacity panel missing reported or bounded count');
    $_GET = ['page' => 'rnl-capacity', 'group' => (string) $group]; $_POST = [];
    ob_start(); \RegiNor\Lite\Admin\CapacityPage::render(); $panel = ob_get_clean();
    $assert(str_contains($panel, 'Kapasitet fra LetsReg') && !str_contains($panel, 'Lagre prøveoppsett'), 'Mapped course still uses demonstration page');
    $_GET = [];
    $futurePeriod = array_replace($p, ['sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600)]);
    $assert(RegistrationState::resolve($futurePeriod, $g, new DateTimeImmutable())['status'] === 'available', 'Automatic status inherits future period sales date');
    $closedPeriod = array_replace($p, ['sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() - 1)]);
    $assert(RegistrationState::resolve($closedPeriod, $g, new DateTimeImmutable())['status'] === 'available', 'Automatic status inherits expired period sales date');
    $emptyPeriod = array_replace($p, ['sales_from' => null, 'sales_until' => null]);
    $assert(RegistrationState::resolve($emptyPeriod, $g, new DateTimeImmutable())['status'] === 'available', 'API requires local period sales dates');
    $localCourse = array_replace($g, ['registration_from' => gmdate('Y-m-d\TH:i:s\Z', time() + 1800)]);
    $assert(RegistrationState::resolve($p, $localCourse, new DateTimeImmutable())['status'] === 'later', 'Explicit course opening ignored');
    $localCourse = array_replace($g, ['registration_until' => gmdate('Y-m-d\TH:i:s\Z', time() - 1)]);
    $assert(RegistrationState::resolve($p, $localCourse, new DateTimeImmutable())['status'] === 'closed', 'Explicit course closing ignored');
    $assert(RegistrationState::resolve(array_replace($futurePeriod, ['cancelled' => true]), $g, new DateTimeImmutable())['status'] === 'cancelled', 'Automatic bypasses cancelled period');
    $assert(RegistrationState::resolve($futurePeriod, $g, new DateTimeImmutable(), false)['status'] === 'hidden', 'Automatic bypasses visibility');
    $read = (new Catalog())->read(); $read['periods'][$period]['sales_from'] = $futurePeriod['sales_from'];
    $read['groups'][$group]['status'] = 'later';
    $read['groups'][$group]['effective_registration_from'] = gmdate('Y-m-d\TH:i:s\Z', time() + 600);
    $read['groups'][$group]['effective_registration_until'] = null;
    $html = (new Renderer())->render($read, ['rnl_course' => $group]);
    $assert(str_contains($html, 'kl. ' . wp_date('H:i', time() + 600, new DateTimeZone($g['timezone']))), 'Detail uses period date instead of API opening without closing bound');
    $manual = array_replace($g, ['registration_status' => 'closed']);
    $assert(RegistrationState::resolve($p, $manual, new DateTimeImmutable())['status'] === 'closed', 'Manual closure ignored');
    $assert(empty(RegistrationState::resolve($p, $manual, new DateTimeImmutable())['capacity']['categories']), 'Manual override advertises API category counts');
    $manual['registration_status'] = 'available';
    $assert(RegistrationState::resolve($p, $manual, new DateTimeImmutable())['status'] === 'available', 'Explicit manual override does not replace API status');
    $manual['registration_until'] = gmdate('Y-m-d\TH:i:s\Z', time() - 1);
    $assert(RegistrationState::resolve($p, $manual, new DateTimeImmutable())['status'] === 'closed', 'Manual override bypasses local sales dates');
    $mode = 'ok'; $remaining = 10; $ready();
    $stored = get_transient($key); $stored['expires_at'] = time() - 1; set_transient($key, $stored, 86400);
    $assert(RegistrationState::resolve($p, $g, new DateTimeImmutable())['status'] === 'available', 'Refresh deadline removed confirmed status');
    $expiredAt = new DateTimeImmutable();
    $deadlines = array_map(static fn($date) => $date->getTimestamp(), RegistrationState::resolve($p, $g, $expiredAt)['boundaries']);
    $assert(!in_array($stored['expires_at'], $deadlines, true), 'Refresh deadline invalidates entire page');
    $assert(!in_array($expiredAt->getTimestamp() + 60, $deadlines, true), 'Unknown API status invalidates whole page after one minute');
    $expiredRead = (new Catalog())->read();
    $assert(!empty($expiredRead['groups'][$group]['capacity']['categories']), 'Last known counts disappeared after refresh deadline');
    $assert(str_contains((new Renderer())->render($expiredRead, ['rnl_course' => $group]), 'Opptil 10') && str_contains((new Renderer())->render($expiredRead, ['rnl_course' => $group]), 'Siste kjente kapasitet'), 'Last known count or disclaimer missing');
    ob_start(); \RegiNor\Lite\Admin\LetsRegCapacityPanel::render($g); $panel = ob_get_clean();
    $assert(str_contains($panel, 'Opptil 10') && str_contains($panel, 'Siste kjente kapasitet'), 'Admin lost last known counts');
    $assert(RegistrationState::resolve($futurePeriod, $g, new DateTimeImmutable())['status'] === 'available', 'Stale API observation incorrectly inherits period sales dates');
    $stored['checked_at'] = time() - 700; set_transient($key, $stored, 86400);
    wp_set_current_user(0); $before = $calls; Store::runDue([$group]);
    $assert($calls === $before + 4 && Store::view($g, new DateTimeImmutable())['status'] === 'available', 'Anonymous cron cannot refresh authenticated observation');
    $before = $calls; Store::runDue([$group]); $assert($calls === $before, 'Fresh event polled again');
    $stored = get_transient($key); $stored['checked_at'] = time() - 700; set_transient($key, $stored, 86400);
    delete_option('rnl_letsreg_poll'); $mode = '429'; Store::runDue([$group]);
    $assert(get_option('rnl_letsreg_poll')['next_at'] >= time() + 690, 'Retry-After ignored');
    $before = $calls; Store::runDue([$group]); $assert($calls === $before, 'Rate limit bypassed');
    $assert(Store::view($g, new DateTimeImmutable())['status'] === 'available', 'Rate limit removed last confirmed status');
    $assert(Store::view($g, new DateTimeImmutable())['categories'][0]['reported_available'] === 10, 'API failure erased last known count');
    wp_set_current_user($admin);
    try { Connection::check(12345, null, true); $assert(false, 'Interactive check bypasses polling Retry-After'); }
    catch (RuntimeException $e) { $assert($e->getCode() === 429 && $calls === $before, 'Interactive check must respect account backoff'); }
    wp_set_current_user(0);
    delete_option('rnl_letsreg_poll'); $mode = '401'; Store::runDue([$group]);
    $assert(get_option('rnl_letsreg_poll')['blocked'], 'Authentication failure does not halt polling');
    $before = $calls; Store::runDue([$group]); $assert($calls === $before, 'Authentication failure retried automatically');
    $assert(Store::view($g, new DateTimeImmutable())['status'] === 'available', 'Authentication failure removed last confirmed status');
    delete_transient($key);
    $assert(Store::view($g, new DateTimeImmutable())['status'] === 'available', 'Cache eviction removed last confirmed status');
    $assert(RegistrationState::resolve($p, $g, new DateTimeImmutable('+3 hours'))['status'] === 'closed', 'Last known sale window ignored during outage');
    wp_set_current_user($admin); $mode = 'ok'; $ready();
    $assert(!get_option('rnl_letsreg_poll'), 'Successful manual control does not resume polling');
    putenv('RNL_LETSREG_PASSWORD=rotated-synthetic');
    $assert(Store::view($g, new DateTimeImmutable())['status'] === 'unknown', 'Old credentials observation reused');
    putenv('RNL_LETSREG_PASSWORD=synthetic-only');
    $other = $g; $other['letsreg_mapping']['event_id'] = 12347;
    $assert(Store::view($other, new DateTimeImmutable())['status'] === 'unknown', 'Another event inherits availability');
    $wrong = $g; $wrong['letsreg_mapping']['organizer_id'] = 99;
    $assert(Store::view($wrong, new DateTimeImmutable())['status'] === 'unknown', 'Another organizer inherits availability');
    $public = wp_json_encode((new Catalog())->read());
    $assert(!str_contains($public, 'synthetic.status.token') && !str_contains($public, 'fingerprint') && !str_contains($public, 'letsreg_mapping'), 'Private data in public model');
    $assert(wp_next_scheduled(Store::HOOK) !== false, 'Status poller is not scheduled');
    // Published course status is an independent versioned write, available to course managers.
    $parentBefore = $repo->get($period); $before = $repo->get($group); $beforeCalls = $calls;
    $manager = wp_insert_user(['user_login' => 'rnl_status_' . wp_generate_password(8, false), 'user_pass' => wp_generate_password(), 'role' => 'rnl_course_manager']);
    wp_set_current_user($manager);
    $payload = ['nonce' => wp_create_nonce('rnl_registration_status'), 'period' => (string) $period, 'id' => (string) $group,
        'version' => (string) $before['version'], 'operation' => 'save', 'status' => 'closed'];
    $reply = \RegiNor\Lite\Admin\RegistrationStatusControl::dispatch($payload);
    $after = $repo->get($group);
    $assert($after['data'] === array_replace($before['data'], ['registration_status' => 'closed']), 'Status write changes unrelated course data');
    $assert(get_post_status($group) === 'publish' && get_post_status($period) === 'publish' && $repo->get($period) === $parentBefore, 'Quick status unpublishes or changes parent');
    $assert($calls === $beforeCalls && $reply['rows'][0]['status'] === 'closed', 'AJAX save calls provider or returns wrong selection');
    $reject = static function ($input, $code) use ($assert) {
        try { \RegiNor\Lite\Admin\RegistrationStatusControl::dispatch($input); }
        catch (Throwable $e) { $assert(!$code || $e->getCode() === $code, 'Wrong rejection: ' . $e->getMessage()); return; }
        $assert(false, 'Invalid status update accepted');
    };
    $reject($payload, 409);
    $payload['version'] = (string) $after['version'];
    $reject(array_replace($payload, ['nonce' => 'invalid']), 403);
    $reject(array_replace($payload, ['period' => (string) $room]), 0);
    $reject(array_replace($payload, ['status' => 'invented']), 0);
    $reply = \RegiNor\Lite\Admin\RegistrationStatusControl::dispatch(array_replace($payload, ['status' => 'automatic']));
    $assert($reply['rows'][0]['status'] === 'automatic', 'Cannot return to automatic on published course');
    $readReply = \RegiNor\Lite\Admin\RegistrationStatusControl::dispatch(array_replace($payload, ['operation' => 'read']));
    $assert($readReply['rows'][0]['version'] === $repo->get($group)['version'] && $calls === $beforeCalls, 'Status refresh stale or calls provider');
    $cached = get_transient($key); $cached['checked_at'] = time() - 700; $cached['expires_at'] = time() - 1; set_transient($key, $cached, 86400);
    delete_option('rnl_letsreg_poll'); $callsBeforeRefresh = $calls; $courseBeforeRefresh = $repo->get($group);
    $readReply = \RegiNor\Lite\Admin\RegistrationStatusControl::dispatch(array_replace($payload, ['operation' => 'read']));
    $assert($calls === $callsBeforeRefresh + 4 && Store::view($g, new DateTimeImmutable())['status'] === 'available', 'Overview does not refresh overdue linked event for course manager');
    $assert($repo->get($group) === $courseBeforeRefresh && $repo->get($period) === $parentBefore, 'Overview refresh changes saved course or publication');
    $callsBeforeRefresh = $calls; Store::runDue([]); Store::runDue([$group]);
    $assert($calls === $callsBeforeRefresh, 'Empty period or fresh course triggers API calls');
    wp_set_current_user(0); $reject($payload, 403);
    wp_set_current_user($admin);
    ob_start(); \RegiNor\Lite\Admin\RegistrationStatusControl::render($group, $repo->get($group), $p); $control = ob_get_clean();
    $assert(str_contains($control, 'data-rnl-status-row') && str_contains($control, 'aria-describedby') && str_contains($control, 'Automatisk fra LetsReg'), 'Accessible status control missing');

} finally {
    remove_filter('pre_http_request', $http, PHP_INT_MAX);
    if ($manager) { wp_delete_user($manager); }
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    foreach ($keys as $key) { delete_transient($key); delete_option($key); }
    foreach ($env as $name => $value) { $value === false ? putenv($name) : putenv($name . '=' . $value); }
    foreach ([Connection::OPTION => $oldConnection, 'rnl_letsreg_poll' => $oldPoll] as $name => $value) { $value === null ? delete_option($name) : update_option($name, $value, false); }
    wp_set_current_user($original);
}
WP_CLI::success($checks . ' automatic registration checks passed; synthetic data removed.');
