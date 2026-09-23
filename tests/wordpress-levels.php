<?php
use RegiNor\Lite\Admin\CourseActions;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;
use RegiNor\Lite\Frontend\SchemaPresenter;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Domain\Publication\Clock;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$created = []; $checks = 0; $original = get_current_user_id(); $oldPage = get_option('rnl_course_page_id', null); $oldGet = $_GET;
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$reject = static function (callable $fn) use ($assert): void {
    try { $fn(); } catch (Throwable $e) { $assert(true, 'Rejected'); return; }
    $assert(false, 'Ugyldig nivåhandling ble godtatt.');
};
$clock = new class implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('2029-12-01T12:00:00Z'); } };
$repo = new CourseRepository($clock); $catalog = new Catalog($clock); $actions = new CourseActions($repo);
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]; $manager = 0;
try {
    wp_set_current_user($admin);
    $base = ['description' => 'For deg som har danset før.', 'sort_order' => 20, 'active' => true];
    $level = $created[] = $repo->create('level', ['title' => 'Testnivå Øvet 1'] + $base);
    $beginner = $created[] = $repo->create('level', array_replace($base, ['title' => 'Testnivå Nybegynner', 'sort_order' => 10]));
    $unused = $created[] = $repo->create('level', ['title' => 'SKJULT NIVÅ UTEN OFFENTLIGE KURS'] + $base);
    $retired = $created[] = $repo->create('level', array_replace($base, ['title' => 'Utgått nivå', 'active' => false]));
    $assert(!get_post_type_object('rnl_level')->public && !get_post_type_object('rnl_level')->show_in_rest, 'Nivåer eksponerer rå lagring.');
    $reject(fn () => $repo->create('level', array_replace($base, ['title' => ' ', 'sort_order' => 10])));
    $reject(fn () => $repo->create('level', array_replace($base, ['title' => 'Feil', 'sort_order' => -1])));
    $nonce = wp_create_nonce('rnl_course_command');
    $actions->handle(['command' => 'save', 'kind' => 'level', 'id' => (string) $level, 'version' => '1', '_wpnonce' => $nonce,
        'data' => ['title' => 'Testnivå Øvet 1', 'description' => 'For deg som har danset før.', 'sort_order' => '20', 'active' => '1']]);
    $assert($repo->get($level)['version'] === 2, 'Nivåskjema lagrer ikke med versjon.');
    $reject(fn () => $repo->update($level, 1, ['title' => 'Utdatert'] + $base));
    $reject(fn () => $actions->handle(['command' => 'save', 'kind' => 'level', '_wpnonce' => 'invalid', 'data' => ['title' => 'Feil'] + $base]));
    $venue = $created[] = $repo->create('venue', ['title' => 'Nivåprøve sted', 'address' => 'Testgate 1']);
    $room = $created[] = $repo->create('room', ['title' => 'Nivåprøve sal', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Felles kursbeskrivelse', 'description' => 'Lær å danse.', 'level_description' => 'Les nivået for dette kurset.', 'dance_style' => 'Salsa', 'partner_info' => '', 'audience' => 'mixed']);
    $period = $created[] = $repo->create('period', ['title' => 'Nivåprøve periode', 'timezone' => 'Europe/Oslo', 'start_date' => '2030-01-07',
        'default_session_count' => 3, 'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person',
        'visible_from' => '2029-01-01T00:00:00Z', 'visible_until' => '2030-03-01T00:00:00Z',
        'sales_from' => '2029-01-01T00:00:00Z', 'sales_until' => '2030-02-01T00:00:00Z', 'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []]);
    $fields = ['title' => 'Øvetkurs prøve', 'weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/test', 'level_id' => $level];
    $reject(fn () => $repo->createGroup($period, $course, array_replace($fields, ['level_id' => $venue])));
    $reject(fn () => $repo->createGroup($period, $course, array_replace($fields, ['level_id' => $retired])));
    $group = $created[] = $repo->createGroup($period, $course, $fields);
    $other = $created[] = $repo->createGroup($period, $course, array_replace($fields, ['title' => 'Nybegynnerkurs prøve', 'weekday' => 2, 'level_id' => $beginner]));
    $legacy = $created[] = $repo->createGroup($period, $course, array_replace($fields, ['title' => 'Uten nivå prøve', 'weekday' => 3, 'level_id' => 0]));
    foreach ([$group, $other, $legacy] as $id) { $repo->confirm($repo->previewGroup($id, 1, [])); }
    $reject(fn () => $repo->previewGroup($group, 2, ['level_id' => $retired]));
    $_GET = ['page' => 'rnl-resources']; ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(!str_contains($html, 'name="data[audience]"'), 'Gammelt målgruppevalg vises fortsatt i oppsettet.');
    $assert(str_contains($html, 'Kursinnhold og ressurser') && str_contains($html, 'Kursnivåer') && str_contains($html, 'name="data[sort_order]"'), 'Ressurssiden mangler nivåoppsett.');
    $manager = wp_insert_user(['user_login' => 'rnl-levels-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'rnl_course_manager']);
    if (is_wp_error($manager)) { throw new RuntimeException('Kunne ikke opprette testbruker.'); }
    wp_set_current_user($manager);
    $assert(isset($repo->listing('level')[$level]) && !current_user_can('rnl_edit_levels'), 'Kursansvarlig må kunne velge, men ikke redigere felles nivåer.');
    $reject(fn () => $repo->create('level', ['title' => 'Ikke tillatt'] + $base));
    $reject(fn () => $repo->update($level, 2, ['title' => 'Ikke tillatt'] + $base));
    $before = $repo->get($group);
    $changed = $repo->confirm($repo->previewGroup($group, $before['version'], ['level_id' => $beginner]));
    $assert($changed['state']['data']['level_id'] === $beginner && $changed['state']['data']['sessions'] === $before['data']['sessions'], 'Nivåvalg fra kursansvarlig må lagres uten å endre øktene.');
    $repo->confirm($repo->previewGroup($group, $changed['state']['version'], ['level_id' => $level]));
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $period, 'group' => (string) $group]; ob_start(); CoursePage::render(); $html = ob_get_clean();
    $assert(str_contains($html, 'name="data[level_id]"') && str_contains($html, 'Testnivå Øvet 1') && !str_contains($html, 'Utgått nivå'), 'Kursansvarlig mangler aktive nivåvalg på kurset.');
    wp_set_current_user($admin);
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Nivåprøve kursside', 'post_content' => '[reginor_courses]']);
    update_option('rnl_course_page_id', $page);
    $preview = $repo->previewPublication($period, 1, true);
    $assert($preview['errors'] === [], 'Valgfrie nivåer blokkerer publisering.');
    $levelState = $repo->get($level); $repo->update($level, $levelState['version'], array_replace($levelState['data'], ['description' => 'Oppdatert forklaring']));
    $reject(fn () => $repo->publish($preview));
    $repo->publish($repo->previewPublication($period, 1, true));
    wp_set_current_user(0); $read = $catalog->read();
    $assert($read['groups'][$group]['level_id'] === $level && $read['groups'][$other]['level_id'] === $beginner, 'Delt kursbeskrivelse overstyrer enkeltkursets nivå.');
    $assert($read['groups'][$legacy]['level_id'] === 0, 'Kurs uten nytt nivå endres.');
    $oldLink = (new Renderer())->render($read, ['rnl_period' => $period, 'rnl_level' => 'beginner']);
    $assert(str_contains($oldLink, 'Øvetkurs prøve') && str_contains($oldLink, 'Nybegynnerkurs prøve'), 'Gammel målgruppelenke skal ikke gjette et nytt nivå.');
    foreach (['list', 'week'] as $view) {
        $html = (new Renderer())->render($read, ['rnl_period' => $period, 'rnl_view' => $view, 'rnl_level' => $level]);
        $assert(!str_contains($html, 'Jeg er nybegynner') && !str_contains($html, 'Jeg har danset før'), 'Gamle målgruppesnarveier vises fortsatt.');
        $assert(str_contains($html, 'Øvetkurs prøve') && !str_contains($html, 'Nybegynnerkurs prøve') && !str_contains($html, 'Uten nivå prøve'), 'Nivåfilteret avgrenser ikke kursene.');
        $assert(str_contains($html, 'rnl_level=' . $level) && str_contains($html, 'name="rnl_level"'), 'Nivåvalg mangler i filter eller lenker.');
        $assert(strpos($html, 'Testnivå Nybegynner') < strpos($html, 'Testnivå Øvet 1'), 'Nivåene sorteres ikke etter oppsettet.');
        $assert(!str_contains($html, 'SKJULT NIVÅ UTEN OFFENTLIGE KURS'), 'Filteret røper nivåer uten offentlige kurs.');
    }
    $detail = (new Renderer())->render($read, ['rnl_course' => $group, 'rnl_level' => $level, 'rnl_view' => 'week']);
    $assert(str_contains($detail, 'Testnivå Øvet 1') && str_contains($detail, 'Oppdatert forklaring') && str_contains($detail, 'rnl_level=' . $level), 'Detalj mangler nivå, forklaring eller returvalg.');
    $assert(SchemaPresenter::group($read['groups'][$group], 'https://example.test/kurs')['educationalLevel'] === 'Testnivå Øvet 1', 'Schema bruker et annet nivå.');
    $assert(str_contains((new Renderer())->render($read, ['rnl_period' => $period, 'rnl_level' => $unused]), 'Ingen kurs passer akkurat'), 'Ukjent nivå viser feil kurs fremfor tomt resultat.');
    $translated = static fn ($value, $context, $key) => $key === 'level.' . $level . '.title' ? 'Intermediate level' : $value;
    add_filter('wpml_translate_single_string', $translated, 10, 3);
    $assert($catalog->read()['groups'][$group]['level_name'] === 'Intermediate level', 'Nivånavnet oversettes ikke.');
    remove_filter('wpml_translate_single_string', $translated, 10);
    wp_set_current_user($admin); $state = $repo->get($level); $repo->update($level, $state['version'], array_replace($state['data'], ['active' => false]));
    wp_set_current_user(0);
    $assert($catalog->read()['groups'][$group]['level_name'] === 'Testnivå Øvet 1', 'Deaktivering skjuler nivået fra eksisterende offentlige kurs.');
    wp_set_current_user($admin);
    $copy = $created[] = $repo->copyPeriod($period, $repo->get($period)['version'], 'Nivåkopi', '2030-04-01');
    $copied = $repo->groups($copy); foreach (array_keys($copied) as $id) { $created[] = $id; }
    $assert(in_array($level, array_map(static fn ($g) => $g['data']['level_id'] ?? 0, $copied), true), 'Periodekopi mister nivåvalget.');
} finally {
    wp_set_current_user($admin);
    foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    if ($manager && !is_wp_error($manager)) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($manager); }
    if ($oldPage === null) { delete_option('rnl_course_page_id'); } else { update_option('rnl_course_page_id', $oldPage); }
    $_GET = $oldGet; wp_set_current_user($original);
}
WP_CLI::success('Nivåkontroller bestått: ' . $checks . '. Testdata er ryddet bort.');
