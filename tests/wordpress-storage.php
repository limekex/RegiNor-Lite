<?php
/** Integration checks. Only run in the disposable local wp-env installation. */

use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\VersionConflict;
use RegiNor\Lite\Domain\Publication\Clock;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Lagringstesten krever WP-CLI og WP_ENVIRONMENT_TYPE=local.');
}

$created = [];
$userIds = [];
$originalUser = get_current_user_id();
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) { throw new RuntimeException($message); }
};
$reject = static function (callable $operation, int $code = 0) use ($assert): void {
    try { $operation(); } catch (Throwable $error) {
        $assert($code === 0 || $error->getCode() === $code, 'Uventet feilkode: ' . $error->getMessage());
        return;
    }
    $assert(false, 'En ugyldig operasjon ble ikke avvist.');
};
$clock = new class implements Clock {
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-01T12:00:00Z'); }
};
$repo = new CourseRepository($clock);
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$instructorTypes = static fn (): array => ['rnl_test_instructor'];
try {
    wp_set_current_user($admin);
    $legacy = $created[] = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Syntetisk eldre kurs']);
    add_post_meta($legacy, 'legacy_course_field', 'Uendret gammel verdi');
    $legacyBefore = [get_post($legacy)->to_array(), get_post_meta($legacy)];
    foreach (ContentTypes::TYPES as $kind => $type) {
        $object = get_post_type_object($type);
        $assert($object !== null && !$object->public && !$object->publicly_queryable && !$object->show_in_rest, 'Innholdstypen må være privat uten REST-rute.');
        $assert(isset(get_registered_meta_keys('post', $type)[ContentTypes::META]), 'Metadata må være registrert.');
        $assert(str_starts_with($object->cap->edit_posts, 'rnl_'), 'Egen capability-mapping mangler.');
    }
    $venue = $created[] = $repo->create('venue', ['title' => 'Syntetisk sted', 'address' => 'Testgata 1']);
    $room = $created[] = $repo->create('room', ['title' => 'Testsal', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Syntetisk salsa', 'description' => 'Tekst med \\ og "anførsel"',
        'level_description' => 'Ingen forkunnskaper', 'dance_style' => 'Salsa', 'partner_info' => '']);
    $periodData = ['title' => 'Syntetisk periode', 'timezone' => 'Europe/Oslo', 'start_date' => '2026-10-12',
        'default_session_count' => 3, 'default_room_id' => $room, 'default_price_minor' => 120000, 'default_price_basis' => 'person',
        'visible_from' => '2026-10-01T00:00:00Z', 'visible_until' => '2026-11-01T00:00:00Z',
        'sales_from' => null, 'sales_until' => null, 'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []];
    $period = $created[] = $repo->create('period', $periodData);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00']);
    $initial = $repo->get($group);
    $assert($initial['version'] === 1 && $initial['data']['price_minor'] === 120000 && get_post_status($group) === 'draft', 'Kladd og kopierte standardverdier mangler.');
    $assert($repo->get($course)['data']['description'] === 'Tekst med \\ og "anførsel"', 'WordPress-slashing ødela tekst.');
    $reject(static fn () => $repo->create('room', ['title' => 'Feil referanse', 'venue_id' => $course]), 404);
    $reject(static fn () => $repo->create('period', array_replace($periodData, ['visible_until' => $periodData['visible_from']])));
    $reject(static fn () => $repo->create('period', array_replace($periodData, ['unknown_field' => 'ikke tillatt'])));

    $preview = $repo->previewGroup($group, 1, []);
    $assert(count($preview['data']['sessions']) === 3 && $repo->get($group) === $initial, 'Forhåndsvisningen må være uten datamutasjon.');
    $tampered = $preview;
    $tampered['data']['title'] = 'Uvedkommende endring';
    $reject(static fn () => $repo->confirm($tampered), 403);
    $result = $repo->confirm($preview);
    $state = $result['state'];
    $assert($state['version'] === 2 && count($state['history']) === 1 && $state['history'][0]['data'] === $initial['data'], 'Full historikk og versjon må lagres sammen.');
    $assert(count(array_unique(array_column($state['data']['sessions'], 'id'))) === 3, 'Økt-ID-er må være unike.');
    $reject(static fn () => $repo->confirm($preview), 409);
    $reject(static fn () => $repo->update($group, 2, $state['data']));
    $reject(static fn () => $repo->previewGroup($group, 2, ['sessions' => []]));
    $reject(static fn () => $repo->previewGroup($group, 2, ['period_id' => $course]));
    $reject(static fn () => $repo->previewGroup($group, 2, ['registration_url' => 'javascript:alert(1)']));
    $reject(static fn () => $repo->previewGroup($group, 2, ['price_minor' => -1]));
    $reject(static fn () => $repo->previewGroup($group, 2, ['instructor_ids' => [$course]]));

    $pending = $repo->previewGroup($group, 2, ['start_time' => '19:00', 'end_time' => '20:00']);
    $periodData['default_price_minor'] = 130000;
    $repo->update($period, 1, $periodData);
    $reject(static fn () => $repo->confirm($pending), 409);
    $assert($repo->get($group)['data']['price_minor'] === 120000, 'Periodestandarder må ikke endre eksisterende grupper.');
    $assert($repo->periodReview($period)[$group]['needs_schedule_review'], 'Perioderevisjon skal gi synlig avklaringsbehov.');
    $reject(static fn () => $repo->update($period, 1, $periodData), 409);

    $firstId = $state['data']['sessions'][0]['id'];
    $moved = $repo->previewSessionChange($group, 2, $firstId, ['date' => '2026-10-13', 'status' => 'moved', 'reason' => 'Avtalt flytting']);
    $state = $repo->confirm($moved)['state'];
    $assert($state['data']['sessions'][0]['id'] === $firstId && $state['data']['sessions'][0]['original_date'] === '2026-10-12', 'Flytting mistet identitet.');
    $regenerated = $repo->previewGroup($group, 3, []);
    $assert($regenerated['data']['sessions'][0] === $state['data']['sessions'][0], 'Vanlig omplanlegging overskrev flyttingen.');
    $state = $repo->confirm($regenerated)['state'];
    $cancel = $repo->previewSessionChange($group, 4, $state['data']['sessions'][1]['id'], ['status' => 'cancelled', 'reason' => 'Avlyst etter avtale']);
    $state = $repo->confirm($cancel)['state'];
    $preview = $repo->previewGroup($group, 5, []);
    $assert($preview['data']['sessions'][1]['status'] === 'cancelled' && $preview['issues'] !== [], 'Avlysning må bevares og kreve avklaring av erstatning.');
    $reject(static fn () => $repo->confirm($preview));
    $breakId = wp_generate_uuid4();
    $periodData['breaks'] = [['id' => $breakId, 'from' => '2026-10-13', 'until' => '2026-10-13', 'reason' => 'Lokalet stengt']];
    $p = $repo->update($period, 2, $periodData);
    $assert($p['data']['breaks'][0]['id'] === $breakId, 'Oppholds-ID ble ikke bevart.');
    $preview = $repo->previewGroup($group, 5, []);
    $assert(str_contains(implode(' ', $preview['issues']), 'manuelt flyttet'), 'Opphold over flyttet økt må varsles.');

    $otherPeriod = $created[] = $repo->create('period', array_replace($periodData, ['title' => 'Annen periode', 'breaks' => []]));
    $otherGroup = $created[] = $repo->createGroup($otherPeriod, $course, ['weekday' => 1, 'start_time' => '18:30', 'end_time' => '19:30', 'session_count' => 4]);
    $preview = $repo->previewGroup($otherGroup, 1, []);
    $assert($preview['warnings'] !== [], 'Sluttdato utenfor vinduet må varsles.');
    $assert($preview['conflicts'] !== [], 'Romkonflikt på tvers av perioder må vises før lagring.');
    $repo->confirm($preview);
    $assert($repo->conflicts() !== [], 'Lagrede perioder må inngå i kollisjonskontroll.');
    wp_update_post(['ID' => $otherGroup, 'post_status' => 'publish']);
    $model = $repo->periodModel($otherPeriod);
    $assert($model->firstSessionStartsAt->format('Y-m-d H:i') === '2026-10-12 18:30'
        && $model->lastSessionEndsAt->format('Y-m-d H:i') === '2026-11-02 19:30', 'Periodegrenser må bruke faktiske økter.');
    $reject(static fn () => $repo->confirm($repo->previewGroup($otherGroup, 2, [])), 409);
    wp_update_post(['ID' => $otherGroup, 'post_status' => 'draft']);
    $lastId = $repo->get($otherGroup)['data']['sessions'][3]['id'];
    $repo->confirm($repo->previewSessionChange($otherGroup, 2, $lastId, ['status' => 'cancelled', 'reason' => 'Siste kveld avlyst']));
    wp_update_post(['ID' => $otherGroup, 'post_status' => 'publish']);
    $assert($repo->periodModel($otherPeriod)->lastSessionEndsAt->format('Y-m-d') === '2026-10-26', 'Avlyste økter skal ikke forlenge undervisningsperioden.');

    register_post_type('rnl_test_instructor', ['public' => true]);
    add_filter('rnl_instructor_post_types', $instructorTypes);
    $instructor = $created[] = wp_insert_post(['post_type' => 'rnl_test_instructor', 'post_status' => 'publish', 'post_title' => 'Syntetisk instruktør']);
    $thirdRoom = $created[] = $repo->create('room', ['title' => 'Annen sal', 'venue_id' => $venue]);
    $thirdGroup = $created[] = $repo->createGroup($otherPeriod, $course, ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '19:00', 'room_id' => $thirdRoom, 'instructor_ids' => [$instructor]]);
    $repo->confirm($repo->previewGroup($thirdGroup, 1, []));
    $fourthGroup = $created[] = $repo->createGroup($period, $course, ['weekday' => 2, 'start_time' => '18:00', 'end_time' => '19:00', 'instructor_ids' => [$instructor]]);
    $profilePlan = $repo->previewGroup($fourthGroup, 1, []);
    $assert(count(array_filter($profilePlan['conflicts'], static fn ($c) => in_array((string) $instructor, $c['instructors'], true))) > 0,
        'Instruktørkonflikt må finnes på tvers av perioder og saler.');
    $assert($profilePlan['data']['instructor_ids'] === [$instructor], 'Profilreferanse må beholdes uten duplisering.');

    // A stale CAS must not overwrite a writer that wins after the initial version read.
    $deniedWrite = static fn ($check, $id, $key) => $id === $course && $key === ContentTypes::META ? false : $check;
    $beforeDenied = $repo->get($course);
    add_filter('update_post_metadata', $deniedWrite, 10, 3);
    try { $reject(static fn () => $repo->update($course, $beforeDenied['version'], $beforeDenied['data']), 503); }
    finally { remove_filter('update_post_metadata', $deniedWrite, 10); }
    $assert($repo->get($course) === $beforeDenied, 'Avvist skriving endret data.');
    global $wpdb;
    $brokenSql = static fn ($sql) => str_starts_with($sql, "UPDATE `{$wpdb->postmeta}`") && str_contains($sql, ContentTypes::META) ? 'UPDATE rnl_nonexistent_test_table SET missing = 1' : $sql;
    $oldSuppress = $wpdb->suppress_errors(true); add_filter('query', $brokenSql);
    try { $reject(static fn () => $repo->update($course, $beforeDenied['version'], $beforeDenied['data']), 503); }
    finally { remove_filter('query', $brokenSql); $wpdb->suppress_errors($oldSuppress); }
    $assert($repo->get($course) === $beforeDenied, 'SQL-feil endret kursdata.');

    $competitor = $repo->get($course);
    $competitor['version']++;
    $competitor['data']['title'] = 'Konkurrerende lagring';
    $race = static function ($check, $objectId, $key, $value, $previous) use ($course, $competitor, &$race) {
        if ($objectId === $course && $key === ContentTypes::META) {
            remove_filter('update_post_metadata', $race, 10);
            update_post_meta($course, ContentTypes::META, wp_slash($competitor));
        }
        return $check;
    };
    add_filter('update_post_metadata', $race, 10, 5);
    $reject(static fn () => $repo->update($course, 1, array_replace($competitor['data'], ['title' => 'Gammel lagring'])), 409);
    remove_filter('update_post_metadata', $race, 10);
    $assert($repo->get($course)['data']['title'] === 'Konkurrerende lagring', 'CAS overskrev nyere data.');

    $subscriber = wp_insert_user(['user_login' => 'rnl-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    if (is_wp_error($subscriber)) { throw new RuntimeException('Kunne ikke opprette testbruker.'); }
    $userIds[] = $subscriber;
    wp_set_current_user($subscriber);
    $reject(static fn () => $repo->get($group), 403);
    $reject(static fn () => $repo->create('period', $periodData), 403);
    $reject(static fn () => $repo->confirm($preview), 403);
    wp_set_current_user(0);
    $reject(static fn () => $repo->get($group), 403);
    $routes = rest_get_server()->get_routes();
    $assert(!isset($routes['/wp/v2/rnl_groups']), 'Kursgrupper skal ikke ha rå REST-skriverute.');
    $assert(!current_user_can('edit_post_meta', $group, ContentTypes::META), 'Rå meta må ikke kunne skrives uten repository.');
    $assert($legacyBefore === [get_post($legacy)->to_array(), get_post_meta($legacy)], 'Eksisterende innhold ble endret.');
} finally {
    wp_set_current_user($admin);
    remove_filter('rnl_instructor_post_types', $instructorTypes);
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($userIds as $id) { wp_delete_user($id); }
    wp_set_current_user($originalUser);
}
WP_CLI::success('Lagringskontroller bestått: ' . $checks . '. Testobjekter er ryddet bort.');
