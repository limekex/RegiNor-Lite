<?php
/** Local, synthetic capacity jobs; no provider calls or participant data. */
use RegiNor\Lite\Infrastructure\CapacityStore;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Capacity\CapacityView;
use RegiNor\Lite\Admin\CapacityPage;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$created = $users = []; $checks = 0; $original = get_current_user_id();
$oldCron = wp_next_scheduled(CapacityStore::HOOK);
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$reject = static function (callable $op, int $code = 0) use ($assert): void {
    try { $op(); } catch (Throwable $e) { $assert(!$code || $e->getCode() === $code, 'Feil avvisning: ' . $e->getMessage()); return; }
    $assert(false, 'Ugyldig handling ble tillatt.');
};
$clock = new class implements Clock {
    public int $stamp = 1900000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->stamp); }
};
$store = new CapacityStore($clock); $repo = new CourseRepository($clock);
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
try {
    wp_set_current_user($admin);
    $course = $created[] = $repo->create('course', ['title' => 'Kapasitetsprøve', 'description' => 'Kun test.', 'level_description' => 'Nybegynner', 'dance_style' => 'Salsa', 'partner_info' => 'Valgfri']);
    $p = ['title' => 'Kapasitetsprøve periode', 'timezone' => 'Europe/Oslo', 'start_date' => '2030-04-01', 'default_session_count' => 2,
        'default_room_id' => 0, 'default_price_minor' => 100000, 'default_price_basis' => 'person', 'visible_from' => null,
        'visible_until' => null, 'sales_from' => null, 'sales_until' => null, 'show_as_upcoming' => false, 'cancelled' => false, 'breaks' => []];
    $period = $created[] = $repo->create('period', $p);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00']);
    foreach (['rnl_course_manager', 'subscriber'] as $role) {
        $user = wp_insert_user(['user_login' => 'rnl-capacity-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => $role]);
        if (is_wp_error($user)) { throw new RuntimeException('Testbruker kunne ikke opprettes.'); } $users[] = $user;
    }
    $assert($store->inspect($group) === null, 'Kurs starter med kapasitet eller mapping.');
    $reject(fn () => $store->request($group));
    $reject(fn () => $store->configure($group, 0, 'live'));
    $store->configure($group, 0, 'fresh'); $pending = $store->inspect($group);
    $assert($pending['snapshot'] === null && $pending['next_attempt_at'] === $clock->stamp, 'Konfigurasjon oppretter ikke varig jobb.');
    $assert(wp_next_scheduled(CapacityStore::HOOK) !== false, 'Jobbkjøreren er ikke registrert.');
    $reject(fn () => $store->configure($group, 0, 'full'), 409);
    $reject(fn () => $store->request($group), 429);
    $assert($store->runDue($group) === 1, 'Forfalt jobb blir ikke kjørt.');
    $good = $store->inspect($group);
    $assert($good['last_attempt_at'] === $clock->stamp && $good['last_success_at'] === $clock->stamp, 'Kontrolltidene lagres ikke.');
    $assert($good['snapshot']['available'] === 8 && $good['error'] === null, 'Komplett bilde mangler.');
    $assert($good['next_attempt_at'] === $clock->stamp + 300, 'Neste periodiske kontroll er feil.');
    $assert((new CapacityStore($clock))->runDue($group) === 0, 'En ny prosess kjører samme jobb igjen før fristen.');
    wp_set_current_user($users[0]);
    $assert($store->inspect($group)['last_success_at'] === $clock->stamp, 'Kursansvarlig kan ikke se aggregert kontroll.');
    $reject(fn () => $store->configure($group, $good['version'], 'full'), 403);
    $reject(fn () => $store->inspect($course), 403);
    $clock->stamp += 60; $store->request($group);
    $assert($store->inspect($group)['next_attempt_at'] === $clock->stamp, 'Manuell kontroll går ikke i samme kø.');
    $reject(fn () => $store->request($group), 429);
    $reject(fn () => CapacityPage::command(['command' => 'check_capacity', 'group' => [$group]]));
    wp_set_current_user($users[1]); $reject(fn () => $store->inspect($group), 403); $reject(fn () => $store->request($group), 403);
    wp_set_current_user(0); $reject(fn () => $store->request($group), 403);
    $assert(CapacityStore::publicView($group, $clock->stamp)['state'] === 'unknown', 'Demotall lekker offentlig.');
    wp_set_current_user($admin);
    // Simulate transient source failure without reconfiguring the mapping or resetting the last good observation.
    $state = $store->inspect($group); $state['scenario'] = 'error'; update_post_meta($group, CapacityStore::META, wp_slash($state));
    $store->runDue($group); $failed = $store->inspect($group);
    $assert($failed['last_success_at'] === $good['last_success_at'] && $failed['snapshot'] === $good['snapshot'], 'Feil overskriver siste gode bilde eller suksessdato.');
    $assert($failed['last_attempt_at'] === $clock->stamp && $failed['error'] === 'temporary', 'Feil forsøksdato eller kode.');
    $assert(CapacityView::project($failed['snapshot'], $clock->stamp, $failed['error'], false, true)['state'] === 'error', 'Siste gode antall vises etter feil.');
    $assert($failed['next_attempt_at'] >= $clock->stamp + 60 && $failed['next_attempt_at'] <= $clock->stamp + 90, 'Retry mangler backoff/jitter.');
    $clock->stamp = $failed['next_attempt_at'];
    $state = $failed; $state['scenario'] = 'rate_limit'; update_post_meta($group, CapacityStore::META, wp_slash($state));
    $store->runDue($group); $limited = $store->inspect($group);
    $assert($limited['not_before'] === $clock->stamp + 600, 'Retry-After blir ignorert.');
    $clock->stamp += 61; $reject(fn () => $store->request($group), 429);
    $assert($store->runDue($group) === 0, 'Automatikk omgår leverandørens ventetid.');
    $clock->stamp = $limited['not_before'];
    $state = $limited; $state['scenario'] = 'full'; update_post_meta($group, CapacityStore::META, wp_slash($state));
    $store->runDue($group); $recovered = $store->inspect($group);
    $assert($recovered['error'] === null && $recovered['failures'] === 0 && $recovered['snapshot']['available'] === 0, 'Vellykket kontroll gjenoppretter ikke normal polling eller skiller null fra 0.');
    // Fail the final storage write after persisting an attempt. A retry must recover the durable job.
    $clock->stamp = $recovered['next_attempt_at'];
    $failure = static function ($check, $id, $key, $value) use ($group) {
        return $id === $group && $key === CapacityStore::META && $value['error'] === null ? false : $check;
    };
    add_filter('update_post_metadata', $failure, 10, 4);
    try { $reject(fn () => $store->runDue($group), 409); } finally { remove_filter('update_post_metadata', $failure, 10); }
    $interrupted = $store->inspect($group);
    $assert($interrupted['error'] === 'interrupted' && $interrupted['last_success_at'] === $recovered['last_success_at'] && $interrupted['snapshot'] === $recovered['snapshot'], 'Lagringsfeil ga et falskt ferskt bilde.');
    $clock->stamp = $interrupted['next_attempt_at']; $store->runDue($group); $recovered = $store->inspect($group);
    $assert($recovered['error'] === null && $recovered['last_success_at'] === $clock->stamp, 'Avbrutt kontroll kunne ikke gjenopptas.');
    $copy = $repo->copyPeriod($period, 1, 'Neste prøveperiode', '2030-06-03');
    $created[] = $copy; $children = $repo->groups($copy);
    foreach (array_keys($children) as $child) { $created[] = $child; $assert($store->inspect($child) === null, 'Periodekopiering beholder ekstern tilkobling/snapshot.'); }
    $assert($repo->get($group)['version'] === 1, 'Kapasitetskontroll endrer redaksjonell versjon.');
    $meta = get_registered_meta_keys('post', 'rnl_group')[CapacityStore::META];
    $assert($meta['show_in_rest'] === false && !current_user_can('edit_post_meta', $group, CapacityStore::META), 'Rå kapasitetsmetadata kan redigeres utenom kontrollflyten.');
    $_GET = ['group' => (string) $group]; $_POST = [];
    ob_start(); CapacityPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Demonstrasjon') && str_contains($html, 'Lagre prøveoppsett') && !str_contains($html, 'source_version'), 'Adminvisning mangler forklaring eller lekker interne felter.');
    wp_set_current_user($users[0]); ob_start(); CapacityPage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'Kontroller nå') && !str_contains($html, 'Lagre prøveoppsett'), 'Kursansvarlig får feil kontroller.');
    wp_set_current_user($admin); $store->configure($group, $recovered['version'], 'off');
    $assert($store->inspect($group) === null && $store->runDue($group) === 0, 'Frakobling stopper ikke jobben.');
} finally {
    wp_set_current_user($admin);
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ($users as $id) { wp_delete_user($id); }
    if (!$oldCron) { wp_clear_scheduled_hook(CapacityStore::HOOK); }
    wp_set_current_user($original);
}
WP_CLI::success('Kapasitetskontroller bestått: ' . $checks . '. Testobjekter er ryddet bort.');
