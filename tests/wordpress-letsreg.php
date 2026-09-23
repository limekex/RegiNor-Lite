<?php
use RegiNor\Lite\Infrastructure\LetsRegConnection as Connection;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Admin\LetsRegPage;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$env = []; foreach (['AFFILIATE_ID', 'ORGANIZER_ID', 'USERNAME', 'PASSWORD', 'CLIENT_ID'] as $key) {
    $name = 'RNL_LETSREG_' . $key;
    if (defined($name)) throw new RuntimeException('Testen krever miljøvariabler uten konstante API-verdier.');
    $env[$name] = getenv($name); putenv($name);
}
$old = get_option(Connection::OPTION, null); $oldUser = get_current_user_id(); $user = 0;
$calls = []; $mode = 'ok'; $checks = 0;
$assert = static function ($ok, $message) use (&$checks) { ++$checks; if (!$ok) throw new RuntimeException($message); };
$response = static fn ($status, $data, $headers = []) => ['response' => ['code' => $status], 'headers' => $headers + ['content-type' => 'application/json'], 'body' => wp_json_encode($data)];
$http = static function ($pre, $args, $url) use (&$calls, &$mode, $response) {
    $calls[] = ['url' => $url, 'args' => $args];
    if ($url === Connection::TOKEN_URL) {
        if (in_array($mode, ['400', '401', '403', '429', '503', '302'], true)) return $response((int) $mode, ['error' => 'raw-secret-must-not-leak'], ['retry-after' => '700']);
        $data = ['access_token' => 'synthetic.token~valid', 'token_type' => 'bearer', 'expires_in' => 3600, 'refresh_token' => 'discard-this-refresh-token'];
        if ($mode === 'no_expiry') unset($data['expires_in']);
        if ($mode === 'bad_token') $data['access_token'] = "secret\r\nInjected: header";
        if ($mode === 'wrong_type') $data['token_type'] = 'Basic';
        if ($mode === 'expired') $data['expires_in'] = 0;
        if ($mode === 'short_expiry') $data['expires_in'] = 5;
        if ($mode === 'boolean_expiry') $data['expires_in'] = true;
        return $response(200, $data);
    }
    if ($url === Connection::ORIGIN . '/organizers/42') {
        if ($mode === 'organizer_401') return $response(401, ['error' => 'private error']);
        if ($mode === 'changed_during_check') putenv('RNL_LETSREG_PASSWORD=rotated-password');
        return $response(200, ['id' => $mode === 'wrong_org' ? 99 : 42, 'affiliateId' => $mode === 'wrong_affiliate' ? 99 : 7,
            'name' => 'Testarrangør', 'bankAccounts' => ['not-for-storage'], 'email' => 'private@example.invalid']);
    }
    if (str_starts_with($url, Connection::ORIGIN . '/organizers/42/events?')) {
        if ($mode === 'search_429') return $response(429, ['error' => 'private-search'], ['retry-after' => '800']);
        if ($mode === 'search_401') return $response(401, ['error' => 'private-search']);
        if ($mode === 'search_object') return $response(200, (object) []);
        if ($mode === 'search_empty') return $response(200, []);
        $row = ['id' => 12345, 'name' => '<b>Salsa Øvet 1</b>', 'organizer' => ['id' => $mode === 'search_owner' ? 12 : 42, 'affiliateId' => $mode === 'search_affiliate' ? 12 : 7],
            'active' => true, 'published' => true, 'isCancelled' => false, 'contactPerson' => ['email' => 'private-search@example.invalid'], 'availableRegistrations' => 999];
        if ($mode === 'search_status') $row['active'] = 'true';
        if ($mode === 'search_duplicate') return $response(200, [$row, $row]);
        if (in_array($mode, ['search_many', 'search_full'], true)) return $response(200, array_map(static fn ($id) => array_replace($row, ['id' => $id]), range(1, $mode === 'search_many' ? 21 : 20)));
        return $response(200, [$row]);
    }
    if ($url === Connection::ORIGIN . '/events/12345') {
        if ($mode === 'event_404') return $response(404, ['error' => 'private-error']);
        if ($mode === 'event_401') return $response(401, ['error' => 'private-error']);
        return $response(200, ['id' => $mode === 'event_wrong_id' ? 12 : 12345, 'name' => '<b>Salsa test</b>',
            'organizer' => ['id' => $mode === 'event_wrong_owner' ? 12 : 42, 'affiliateId' => $mode === 'event_wrong_affiliate' ? 12 : 7],
            'active' => true, 'published' => true, 'isCancelled' => $mode === 'bad_event_status' ? 'false' : false,
            'contactPerson' => ['email' => 'private-event@example.invalid'], 'availableRegistrations' => 999,
            'prices' => [['id' => 999, 'name' => 'embedded-unverified']], 'ordersTotalSum' => 1234]);
    }
    if ($url === Connection::ORIGIN . '/events/12345/prices') {
        if ($mode === 'prices_429') return $response(429, ['error' => 'private-error'], ['retry-after' => '800']);
        if ($mode === 'prices_401') return $response(401, ['error' => 'private-error']);
        if ($mode === 'prices_object') return $response(200, (object) []);
        if ($mode === 'prices_empty') return $response(200, []);
        if ($mode === 'event_changed_during_check') putenv('RNL_LETSREG_PASSWORD=rotated-during-event');
        $row = ['id' => 10, 'name' => '<b>Fører</b>', 'active' => true, 'available' => 999, 'registered' => 1, 'externalId' => 'private-price-id'];
        if ($mode === 'bad_price_id') $row['id'] = '10';
        if ($mode === 'bad_price_status') $row['active'] = 1;
        if ($mode === 'bad_price_name') $row['name'] = ['private-price-name'];
        if ($mode === 'duplicate_prices') return $response(200, [$row, $row]);
        if ($mode === 'many_prices') return $response(200, array_map(static fn ($id) => array_replace($row, ['id' => $id]), range(1, 201)));
        return $response(200, [$row, ['id' => 11, 'name' => null, 'active' => false]]);
    }
    throw new RuntimeException('Unexpected URL; no network allowed');
};
$ready = static function () { delete_option(Connection::OPTION); };
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
try {
    wp_set_current_user($admin); $ready();
    $assert(Connection::inspect()['state'] === 'missing', 'Missing config not recognized');
    $rejected = false; try { Connection::check(); } catch (RuntimeException $e) { $rejected = $e->getCode() === 400; }
    $assert($rejected && !$calls, 'Missing config triggered network');
    foreach (['AFFILIATE_ID' => '7', 'ORGANIZER_ID' => '42', 'USERNAME' => 'api@example.invalid', 'PASSWORD' => 'synthetic &+ password'] as $key => $value) putenv('RNL_LETSREG_' . $key . '=' . $value);
    $assert(Connection::inspect()['state'] === 'unchecked', 'New config inherits old check');
    $user = wp_insert_user(['user_login' => 'rnl-auth-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'rnl_course_manager']);
    if (is_wp_error($user)) throw new RuntimeException('Could not create temporary user');
    foreach ([0, $user] as $unauthorized) {
        wp_set_current_user($unauthorized);
        foreach (['inspect', 'check'] as $method) {
            $denied = false; try { Connection::$method(); } catch (RuntimeException $e) { $denied = $e->getCode() === 403; }
            $assert($denied && !$calls, 'Unauthorized account can inspect or check API');
        }
    }
    wp_set_current_user($admin);
    $state = Connection::check();
    $assert($state['state'] === 'verified' && $state['organizer_name'] === 'Testarrangør' && count($calls) === 2, 'Token and organizer verification failed');
    parse_str($calls[0]['args']['body'], $sent);
    $assert($sent === ['grant_type' => 'password', 'username' => '7:api@example.invalid', 'password' => 'synthetic &+ password'], 'Password form encoding or affiliate prefix wrong');
    $assert($calls[0]['args']['method'] === 'POST' && $calls[0]['args']['redirection'] === 0 && $calls[0]['args']['sslverify'] && $calls[0]['args']['reject_unsafe_urls'], 'Token POST security options missing');
    $assert($calls[1]['args']['headers']['authorization'] === 'Bearer synthetic.token~valid', 'Bearer token not sent to organizer endpoint');
    $stored = wp_json_encode(get_option(Connection::OPTION));
    foreach (['synthetic', 'password', 'access_token', 'refresh_token', 'api@example', 'bankAccounts', 'not-for-storage', 'private@example'] as $private) {
        $assert(!str_contains($stored, $private), 'Secret or unrelated organizer data persisted');
    }
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Testarrangør') && !str_contains($html, 'synthetic') && !str_contains($html, 'api@example.invalid'), 'Admin response leaks credentials');
    $denied = false; try { Connection::check(); } catch (RuntimeException $e) { $denied = $e->getCode() === 429; }
    $assert($denied && count($calls) === 2, 'Repeated check bypassed cooldown');
    $saved = get_option(Connection::OPTION); $saved['verified_until'] = time() - 1; update_option(Connection::OPTION, $saved);
    $assert(Connection::inspect()['state'] === 'expired', 'Old success stays current forever');
    putenv('RNL_LETSREG_PASSWORD=rotated-password');
    $assert(Connection::inspect()['state'] === 'unchecked' && !isset(Connection::inspect()['last_success_at']), 'Credential rotation retains old verification');
    putenv('RNL_LETSREG_PASSWORD=synthetic &+ password');
    foreach (['400' => 'authentication', '401' => 'authentication', '403' => 'permission', '429' => 'rate_limit', '503' => 'temporary', '302' => 'redirect',
        'bad_token' => 'invalid_token', 'wrong_type' => 'invalid_token', 'expired' => 'invalid_token', 'short_expiry' => 'invalid_token', 'boolean_expiry' => 'invalid_token',
        'wrong_org' => 'organization_mismatch', 'wrong_affiliate' => 'organization_mismatch', 'organizer_401' => 'authentication'] as $mode => $expected) {
        $mode = (string) $mode; $ready(); $before = count($calls); $state = Connection::check();
        $assert($state['state'] === 'error' && $state['error'] === $expected && empty($state['last_success_at']), 'Failed check falsely succeeds: ' . $mode);
        $assert(count($calls) === $before + (in_array($mode, ['wrong_org', 'wrong_affiliate', 'organizer_401'], true) ? 2 : 1), 'Unexpected retries or organizer lookup after invalid token');
        $assert(!str_contains(wp_json_encode($state), 'raw-secret'), 'Raw auth response escaped');
        if (in_array($mode, ['400', '401', '403'], true)) $assert($state['next_attempt_at'] >= time() + 299, 'Access failure cooldown missing');
        if (in_array($mode, ['429', '503'], true)) $assert($state['next_attempt_at'] >= time() + 699, 'Retry-After shortened');
    }
    $ready(); $mode = 'no_expiry'; $assert(Connection::check()['state'] === 'verified', 'One-use token requires invented lifetime');
    $previous = get_option(Connection::OPTION); $previous['next_attempt_at'] = 0; update_option(Connection::OPTION, $previous);
    $mode = '401'; $state = Connection::check();
    $assert($state['state'] === 'error' && $state['last_success_at'] === $previous['last_success_at'], 'Failure rewrites last success or appears verified');
    $ready(); $mode = 'ok'; putenv('RNL_LETSREG_CLIENT_ID=swagger'); Connection::check();
    parse_str($calls[count($calls) - 2]['args']['body'], $sent); $assert($sent['client_id'] === 'swagger', 'Explicit client ID missing');
    putenv('RNL_LETSREG_CLIENT_ID');
    $ready(); $before = count($calls); $denied = false;
    try { Mutation::run(static fn () => Connection::check()); } catch (RuntimeException $e) { $denied = $e->getCode() === 409; }
    $assert($denied && count($calls) === $before, 'Auth ran under global course lock');
    global $wpdb;
    $secondDb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    $lock = 'rnl_auth:' . md5(DB_NAME . ':' . $wpdb->prefix);
    try {
        $assert((int) $secondDb->get_var($secondDb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) === 1, 'Test lock unavailable');
        $denied = false; try { Connection::check(); } catch (RuntimeException $e) { $denied = $e->getCode() === 409; }
        $assert($denied && count($calls) === $before, 'Concurrent check sent a duplicate token request');
    } finally { $secondDb->get_var($secondDb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); $secondDb->close(); }
    $rejectSave = static fn ($new, $old) => $old;
    add_filter('pre_update_option_' . Connection::OPTION, $rejectSave, 10, 2);
    $denied = false; try { Connection::check(); } catch (RuntimeException $e) { $denied = $e->getCode() === 503; }
    remove_filter('pre_update_option_' . Connection::OPTION, $rejectSave);
    $assert($denied && count($calls) === $before, 'Failed attempt persistence still sent credentials');
    $ready(); $mode = 'changed_during_check'; $denied = false;
    try { Connection::check(); } catch (RuntimeException $e) { $denied = $e->getCode() === 409; }
    $assert($denied && Connection::inspect()['state'] === 'unchecked', 'In-flight config change stores stale success');
    putenv('RNL_LETSREG_PASSWORD=synthetic &+ password'); $ready(); $mode = 'ok';
    $before = count($calls); $state = Connection::check(12345);
    $assert(count($calls) === $before + 4 && $state['state'] === 'verified' && $state['stage'] === 'prices', 'Event check must authorize, verify organizer, then read event and prices');
    $assert($state['event'] === ['id' => 12345, 'name' => 'Salsa test', 'active' => true, 'published' => true, 'isCancelled' => false,
        'prices' => [['id' => 10, 'name' => 'Fører', 'active' => true], ['id' => 11, 'name' => '', 'active' => false]]], 'Event snapshot contains unverified or unwanted fields');
    $stored = wp_json_encode(get_option(Connection::OPTION));
    foreach (['synthetic', 'private-event', 'availableRegistrations', 'ordersTotalSum', 'embedded-unverified', 'private-price-id'] as $private) {
        $assert(!str_contains($stored, $private), 'Event check persisted raw data');
    }
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Salsa test') && str_contains($html, 'Fører') && !str_contains($html, '<b>Fører</b>') && !str_contains($html, '999'), 'Event preview missing, unescaped or exposes capacity');
    $saved = get_option(Connection::OPTION); $saved['verified_until'] = time() - 1; update_option(Connection::OPTION, $saved);
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Dette er et tidligere resultat.'), 'Old event preview appears current');
    $before = count($calls); $denied = false;
    try { Connection::check(12346); } catch (RuntimeException $e) { $denied = $e->getCode() === 429; }
    $assert($denied && count($calls) === $before, 'Changing event ID bypasses shared cooldown');
    foreach ([0, -1, 2147483648] as $invalid) {
        $denied = false; try { Connection::check($invalid); } catch (RuntimeException $e) { $denied = $e->getCode() === 400; }
        $assert($denied && count($calls) === $before, 'Invalid event ID reaches network');
    }
    foreach (['wrong_org' => ['organization_mismatch', 2], 'event_wrong_id' => ['event_mismatch', 3], 'event_wrong_owner' => ['event_mismatch', 3],
        'event_wrong_affiliate' => ['event_mismatch', 3], 'bad_event_status' => ['event_mismatch', 3], 'event_404' => ['event_missing', 3], 'event_401' => ['authentication', 3],
        'prices_401' => ['authentication', 4], 'prices_429' => ['rate_limit', 4], 'prices_object' => ['invalid_prices', 4], 'duplicate_prices' => ['invalid_prices', 4],
        'bad_price_id' => ['invalid_prices', 4], 'bad_price_status' => ['invalid_prices', 4], 'bad_price_name' => ['invalid_prices', 4], 'many_prices' => ['invalid_prices', 4]] as $mode => [$error, $requests]) {
        // Begin each failure with a previous success: it must never survive as a current preview.
        $saved['next_attempt_at'] = 0; update_option(Connection::OPTION, $saved);
        $before = count($calls); $state = Connection::check(12345);
        $assert($state['state'] === 'error' && $state['error'] === $error && !isset($state['event']), 'Failed event check preserves stale/partial preview: ' . $mode);
        $assert(count($calls) === $before + $requests, 'Unexpected request after failed ownership/schema/access: ' . $mode);
        if ($mode === 'prices_429') $assert($state['next_attempt_at'] >= time() + 799, 'Price rate limit not respected');
    }
    $ready(); $mode = 'prices_empty';
    $assert(Connection::check(12345)['event']['prices'] === [], 'Documented empty price list rejected');
    $ready(); $mode = 'no_expiry';
    $assert(Connection::check(12345)['state'] === 'verified', 'Bounded manual event check invents token lifetime');
    $ready(); $mode = 'event_changed_during_check'; $denied = false;
    try { Connection::check(12345); } catch (RuntimeException $e) { $denied = $e->getCode() === 409; }
    $assert($denied && Connection::inspect()['state'] === 'unchecked' && !isset(Connection::inspect()['event']), 'Changed credentials retain event preview');
    putenv('RNL_LETSREG_PASSWORD=synthetic &+ password'); $ready(); $mode = 'ok';
    $before = count($calls); $query = 'Salsa Øvet 1 & Par#';
    $state = Connection::check(null, ['query' => $query, 'offset' => 0]);
    $assert($state['state'] === 'verified' && $state['stage'] === 'search' && count($calls) === $before + 3, 'Search did not authorize and verify organizer before events');
    $request = $calls[count($calls) - 1]; parse_str(parse_url($request['url'], PHP_URL_QUERY), $sent);
    $assert($sent === ['Query' => $query, 'Offset' => '0', 'Limit' => '20', 'IncludeFields' => 'false', 'IncludeInstructors' => 'false', 'IncludeCancellationInfo' => 'false'], 'Search encoding or bounded pagination incorrect');
    $assert($request['args']['headers']['authorization'] === 'Bearer synthetic.token~valid', 'Search bearer missing');
    $assert($state['search']['events'] === [['id' => 12345, 'name' => 'Salsa Øvet 1', 'active' => true, 'published' => true, 'isCancelled' => false]] && !$state['search']['has_more'] && !isset($state['event']), 'Search projected unsafe fields or falsely verified categories');
    $stored = wp_json_encode(get_option(Connection::OPTION));
    foreach (['private-search', 'availableRegistrations', 'synthetic.token', 'password'] as $private) $assert(!str_contains($stored, $private), 'Search persisted secrets or unrelated data');
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Salsa Øvet 1') && str_contains($html, 'Kontroller valgt arrangement') && !str_contains($html, '<b>Salsa Øvet 1</b>'), 'Search choice missing or unescaped');
    $before = count($calls); $denied = false;
    try { Connection::check(12345); } catch (RuntimeException $e) { $denied = $e->getCode() === 429; }
    $assert($denied && count($calls) === $before, 'Search bypasses shared cooldown');
    foreach ([['query' => str_repeat('x', 121), 'offset' => 0], ['query' => "bad\nquery", 'offset' => 0], ['query' => [], 'offset' => 0],
        ['query' => '', 'offset' => -20], ['query' => '', 'offset' => 1], ['query' => '', 'offset' => 10020], ['query' => '', 'offset' => '20'], ['query' => '', 'offset' => 0, 'host' => 'elsewhere']] as $invalid) {
        $denied = false; try { Connection::check(null, $invalid); } catch (RuntimeException $e) { $denied = $e->getCode() === 400; }
        $assert($denied && count($calls) === $before, 'Malformed search reached network');
    }
    $denied = false; try { Connection::check(12345, ['query' => '', 'offset' => 0]); } catch (RuntimeException $e) { $denied = $e->getCode() === 400; }
    $assert($denied && count($calls) === $before, 'Mixed search/event request accepted');
    foreach (['search_owner' => 'invalid_events', 'search_affiliate' => 'invalid_events', 'search_status' => 'invalid_events', 'search_duplicate' => 'invalid_events',
        'search_object' => 'invalid_events', 'search_many' => 'invalid_events', 'search_401' => 'authentication', 'search_429' => 'rate_limit'] as $mode => $error) {
        $ready(); $before = count($calls); $state = Connection::check(null, ['query' => '', 'offset' => 20]);
        $assert($state['state'] === 'error' && $state['error'] === $error && !isset($state['search']) && count($calls) === $before + 3, 'Search retained partial or unowned list: ' . $mode);
        if ($mode === 'search_429') $assert($state['next_attempt_at'] >= time() + 799, 'Search rate limit shortened');
    }
    $ready(); $mode = 'search_full'; $state = Connection::check(null, ['query' => '', 'offset' => 20]);
    $assert($state['search']['has_more'] && count($state['search']['events']) === 20 && $state['search']['offset'] === 20, 'Full search page treated as complete catalog');
    ob_start(); LetsRegPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Neste side') && str_contains($html, 'Forrige side') && str_contains($html, 'Resultatside 2'), 'Pagination controls missing');
    $ready(); $mode = 'search_empty'; $state = Connection::check(null, ['query' => '', 'offset' => 40]);
    $assert($state['search']['events'] === [] && !$state['search']['has_more'], 'Empty final page rejected');

    $ready(); $mode = 'ok';
    Connection::check(null, ['query' => 'Rueda', 'offset' => 0], true);
    $state = Connection::check(12345, null, true);
    $assert($state['state'] === 'verified' && isset($state['event']), 'Interactive search cannot immediately select a result');
    for ($i = 0; $i < 8; $i++) Connection::check(12345, null, true);
    $before = count($calls); $denied = false;
    try { Connection::check(12345, null, true); } catch (RuntimeException $e) { $denied = $e->getCode() === 429; }
    $assert($denied && count($calls) === $before, 'Interactive picker bypasses account request budget');
    $ready(); $mode = '401'; Connection::check(12345, null, true); $before = count($calls); $denied = false;
    try { Connection::check(12345, null, true); } catch (RuntimeException $e) { $denied = $e->getCode() === 429; }
    $assert($denied && count($calls) === $before, 'Interactive picker bypasses access failure backoff');
} finally {
    remove_filter('pre_http_request', $http, PHP_INT_MAX);
    if (isset($rejectSave)) remove_filter('pre_update_option_' . Connection::OPTION, $rejectSave);
    if ($old === null) delete_option(Connection::OPTION); else update_option(Connection::OPTION, $old, false);
    foreach ($env as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
    if (is_int($user) && $user > 0) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($user); }
    wp_set_current_user($oldUser);
}
WP_CLI::success("$checks LetsReg authentication checks passed; no external requests sent.");
