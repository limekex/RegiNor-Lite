<?php
use RegiNor\Lite\Infrastructure\Appearance;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;
use RegiNor\Lite\Frontend\PublicSite;
use RegiNor\Lite\Domain\Publication\Clock;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') throw new RuntimeException('Kun lokalt WordPress.');
$old = get_option(Appearance::OPTION, null); $oldPage = get_option('rnl_course_page_id', null); $oldGet = $_GET; $original = get_current_user_id(); $created = []; $checks = 0;
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$assert = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($message); };
$clock = new class implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('-1 day'); } };
$repo = new CourseRepository($clock);
try {
    wp_set_current_user($admin);
    $venue = $created[] = $repo->create('venue', ['title' => 'Utseende teststed', 'address' => 'Test', 'latitude' => 59.9139, 'longitude' => 10.7522]);
    $room = $created[] = $repo->create('room', ['title' => 'Utseende testsal', 'venue_id' => $venue, 'appearance_custom' => true, 'appearance_color' => '#654321', 'appearance_alpha' => 45]);
    $course = $created[] = $repo->create('course', ['title' => 'Utseende testkurs', 'description' => 'Test', 'level_description' => 'Test', 'partner_info' => 'Test', 'dance_style' => 'Test', 'audience' => 'mixed']);
    $date = new DateTimeImmutable('+2 days', new DateTimeZone('Europe/Oslo'));
    $period = $created[] = $repo->create('period', ['title' => 'Utseende testperiode', 'timezone' => 'Europe/Oslo', 'start_date' => $date->format('Y-m-d'), 'default_session_count' => 2,
        'default_room_id' => $room, 'default_price_minor' => 10000, 'default_price_basis' => 'person', 'visible_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400),
        'visible_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * DAY_IN_SECONDS), 'sales_from' => gmdate('Y-m-d\TH:i:s\Z', time() - 86400), 'sales_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * DAY_IN_SECONDS), 'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []]);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => (int) $date->format('N'), 'start_time' => '18:00', 'end_time' => '19:00', 'registration_status' => 'later']);
    $repo->confirm($repo->previewGroup($group, 1, []));
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Utseende testside']); update_option('rnl_course_page_id', $page);
    $before = $repo->get($group);
    update_option(Appearance::OPTION, Appearance::validate(['background' => '#102030', 'day' => '#abcdef', 'room' => '#112233', 'course' => '#ffffff', 'highlight' => '#fff2cf', 'view' => 'week', 'layout' => 'stacked', 'days' => [(int) $date->format('N') => ['enabled' => true, 'color' => '#abcdef', 'alpha' => 75]]]));
    $repo->confirm($repo->previewGroup($group, $before['version'], ['appearance_custom' => true, 'appearance_color' => '#000000', 'appearance_source' => '', 'appearance_alpha' => 100, 'appearance_tone' => 'auto', 'featured' => true, 'dropin_enabled' => true, 'dropin_price_minor' => 20000]));
    $repo->publish($repo->previewPublication($period, 1, true));
    $assert(Appearance::course($group) === ['alpha' => 100, 'source' => '', 'tone' => 'auto', 'color' => '#000000', 'featured' => true], 'Kursutseende ble ikke lagret.');
    $assert($repo->get($group)['data']['sessions'] === $before['data']['sessions'] && get_post_status($group) === 'publish', 'Utseende endret kursdatoene.');
    wp_set_current_user(0); $catalog = (new Catalog())->read();
    foreach ([['rnl_view' => 'list'], ['rnl_view' => 'week'], ['rnl_course' => $group]] as $query) {
        $html = (new Renderer())->render($catalog, $query + ['rnl_period' => $period]);
        $assert(str_contains($html, 'rnl-dropin') && str_contains($html, 'Dette kurset tilbyr drop-in.') && str_contains($html, '200 kr per person og kurskveld.'), 'Drop-in-banner/pris mangler.');
        $assert(str_contains($html, 'rnl-featured') && !str_contains($html, 'Fremhevet kurs'), 'Fremheving skal vises uten egen etikett i frontend.');
        $assert(str_contains($html, '--rnl-course-bg:#000000;--rnl-course-ink:#ffffff;'), 'Egen bakgrunn/lesbar tekst mangler.');
        $assert(str_contains($html, 'rnl-days-stacked') && str_contains($html, '--rnl-day-bg:#abcdef;'), 'Globale visningsvalg mangler.');
    }
    $week = (new Renderer())->render($catalog, ['rnl_period' => $period, 'rnl_view' => 'week']);
    $assert(str_contains($week, '--rnl-day-bg:color-mix(in srgb,#abcdef 75%,transparent)') && str_contains($week, '--rnl-room-bg:color-mix(in srgb,#654321 45%,transparent)'), 'Dag- og salfarger eller alpha mangler i kalenderen.');
    $assert(str_contains($week, '<span class="rnl-room-name">Utseende testsal</span><span class="rnl-room-venue">Utseende teststed</span>'), 'Room and venue must occupy separate heading lines.');
    $savedAppearance = Appearance::settings();
    $assert($savedAppearance['day_color_views'] === 'week' && Appearance::dayCardStyle((int) $date->format('N')) === '', 'Existing day colors changed list appearance.');
    foreach (['week', 'list', 'both'] as $scope) {
        update_option(Appearance::OPTION, Appearance::validate(array_replace($savedAppearance, ['day_color_views' => $scope])));
        $listHtml = (new Renderer())->render($catalog, ['rnl_period' => $period, 'rnl_view' => 'list']);
        $weekHtml = (new Renderer())->render($catalog, ['rnl_period' => $period, 'rnl_view' => 'week']);
        $dayStyle = Appearance::dayCardStyle((int) $date->format('N'));
        $assert(($dayStyle !== '') === ($scope !== 'week'), 'Wrong day color scope for cards.');
        $assert((Appearance::dayStyle((int) $date->format('N')) !== '') === ($scope !== 'list'), 'Wrong day color scope for calendar.');
        $assert(str_contains($weekHtml, 'rnl-day-colors-off') === ($scope === 'list'), 'Calendar default day colors ignore scope.');
        $assert(str_contains($listHtml, '<strong class="rnl-room-name">Utseende testsal</strong>'), 'Room missing from course card.');
        if ($scope !== 'week') {
            $assert(str_contains($listHtml, $dayStyle . Appearance::courseStyle($group)), 'Explicit course color must follow day color.');
            $assert(str_contains($dayStyle, 'color-mix(in srgb,#abcdef 75%,transparent)'), 'Day alpha missing from cards.');
        }
        $profile = (new Renderer())->render($catalog, ['rnl_course' => $group]);
        $assert(substr_count($profile, '<strong class="rnl-room-name">Utseende testsal</strong>') === 2, 'Room must be clear in both booking and location on detail.');
        $assert(!str_contains($profile, '--rnl-course-bg:color-mix(in srgb,#abcdef'), 'Day colors leak to course detail.');
    }
    try { Appearance::validate(['day_color_views' => 'invalid']); $assert(false, 'Invalid day color scope accepted.'); } catch (InvalidArgumentException) {}
    update_option(Appearance::OPTION, $savedAppearance);
    $detail = (new Renderer())->render($catalog, ['rnl_course' => $group]);
    $assert(str_contains($detail, 'data-latitude="59.9139"') && str_contains($detail, 'www.openstreetmap.org/?mlat=59.9139'), 'Kartpunkt og OSM-lenke mangler på offentlig kurs.');
    $assert(!str_contains($detail, '<iframe'), 'Kart laster eksternt uten valg.');
    wp_set_current_user($admin); $_GET = ['period' => $period, 'step' => 'courses'];
    ob_start(); \RegiNor\Lite\Admin\CoursePage::render(); $adminHtml = ob_get_clean();
    $assert(str_contains($adminHtml, 'dashicons-star-filled') && str_contains($adminHtml, 'aria-label="Fremhevet kurs"') && str_contains($adminHtml, 'aria-label="Drop-in"') && str_contains($adminHtml, number_format_i18n(200, 2) . ' kr per person'), 'Kursets statusikoner, navn eller drop-in-pris mangler.');
    $_GET = []; ob_start(); \RegiNor\Lite\Admin\CoursePage::render(); $index = ob_get_clean();
    $dom = new DOMDocument(); @$dom->loadHTML($index); $xpath = new DOMXPath($dom);
    $links = $xpath->query('//a[@class="rnl-icon-action"]');
    $assert($links->length > 0, 'Periodehandlinger mangler.');
    foreach ($links as $link) { $assert(trim($link->textContent) === '' && $link->getAttribute('aria-label') !== '' && $link->getAttribute('aria-describedby') !== '', 'Handlingslenker skal ha bare ikon og tilgjengelig forklaring.'); }
    wp_set_current_user(0);
    $_GET = ['rnl_period' => $period];
    $assert(str_contains(PublicSite::render(), 'rnl-timetable'), 'Felles standardvisning ignoreres.');
    $assert(!str_contains(PublicSite::render(['default_view' => 'list']), 'rnl-timetable'), 'Eksplisitt shortcode-valg ignoreres.');
    $_GET['rnl_view'] = 'list'; $assert(!str_contains(PublicSite::render(), 'rnl-timetable'), 'Besøkendes visningsvalg ignoreres.');
    $_GET['rnl_view'] = 'week'; $assert(!str_contains(PublicSite::render(['allowed_views' => ['list']]), 'rnl-timetable'), 'Tillatte visninger kan omgås.');
    try { $repo->previewGroup($group, 1, ['featured' => false]); $assert(false, 'Anonym endret utseende.'); } catch (RuntimeException $e) { $assert($e->getCode() === 403, 'Tilgangskontroll mangler.'); }
    wp_set_current_user($admin);
    $state = $repo->get($period); $repo->lifecycle($period, $state['version'], array_map(static fn ($s) => $s['version'], $repo->groups($period)), 'draft');
    foreach ([['appearance_color' => '#fff;bad'], ['dropin_enabled' => true, 'dropin_price_minor' => null], ['dropin_enabled' => true, 'dropin_price_minor' => -1]] as $invalid) {
        try { $repo->previewGroup($group, $repo->get($group)['version'], $invalid); $assert(false, 'Ugyldig farge/pris tillates.'); } catch (InvalidArgumentException) { $assert(Appearance::course($group)['color'] === '#000000', 'Ugyldig data overskrev lagret valg.'); }
    }
    $repo->confirm($repo->previewGroup($group, $repo->get($group)['version'], ['appearance_color' => '#123456', 'appearance_source' => 'wp:primary', 'appearance_alpha' => 35, 'appearance_tone' => 'light', 'dropin_price_minor' => 0]));
    $style = Appearance::courseStyle($group);
    $assert(str_contains($style, 'color-mix(in srgb,var(--wp--preset--color--primary,#123456) 35%,transparent)') && str_contains($style, '--rnl-course-ink:#ffffff;'), 'Koblet palett, alpha eller tekstvalg gikk tapt.');
    $assert(\RegiNor\Lite\Infrastructure\ColorPalette::choices() !== [], 'WordPress-paletten ble ikke hentet.');
    $repo->confirm($repo->previewGroup($group, $repo->get($group)['version'], ['appearance_custom' => false, 'featured' => false, 'dropin_enabled' => false]));
    $assert(Appearance::course($group)['color'] === '' && !Appearance::course($group)['featured'], 'Standardutseende kan ikke gjenopprettes.');
    $repo->publish($repo->previewPublication($period, $repo->get($period)['version'], true));
    $html = (new Renderer())->render((new Catalog())->read(), ['rnl_course' => $group]);
    $assert(!str_contains($html, 'rnl-dropin'), 'Deaktivert drop-in vises fortsatt.');
    $repo->lifecycle($period, $repo->get($period)['version'], array_map(static fn ($s) => $s['version'], $repo->groups($period)), 'draft');
    $repo->confirm($repo->previewGroup($group, $repo->get($group)['version'], ['featured' => true, 'dropin_enabled' => true])); wp_set_current_user(0);
    $hidden = (new Catalog())->read(); $assert(!isset($hidden['groups'][$group]), 'Fremheving gjør kladd offentlig.');
    $html = (new Renderer())->render($hidden, ['rnl_course' => $group]); $assert(!str_contains($html, 'Fremhevet kurs'), 'Fremheving eksponeres for privat kurs.');
} finally {
    wp_set_current_user($admin); foreach (array_reverse($created) as $id) wp_delete_post($id, true);
    if ($old === null) delete_option(Appearance::OPTION); else update_option(Appearance::OPTION, $old);
    if ($oldPage === null) delete_option('rnl_course_page_id'); else update_option('rnl_course_page_id', $oldPage);
    $_GET = $oldGet; wp_set_current_user($original);
}
WP_CLI::success("$checks appearance checks passed; settings and fixtures restored.");
