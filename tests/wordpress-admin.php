<?php
/** Updated product rules, period overview and translation contracts; synthetic/local only. */
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Infrastructure\Wpml;
use RegiNor\Lite\Admin\PeriodOverview;
use RegiNor\Lite\Admin\CoursePage;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;
use RegiNor\Lite\Frontend\PublicSite;
use RegiNor\Lite\Frontend\SchemaPresenter;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokale tester.'); }
$created = []; $checks = 0; $original = get_current_user_id(); $oldPage = get_option('rnl_course_page_id', null);
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
$clock = new class implements Clock { public string $time = '2030-01-08T12:00:00Z'; public function now(): DateTimeImmutable { return new DateTimeImmutable($this->time); } };
$repo = new CourseRepository($clock); $overview = new PeriodOverview($repo, $clock);
$registered = []; $register = static function ($context, $name, $value) use (&$registered): void { if ($context === Wpml::CONTEXT) { $registered[$name] = $value; } };
$strings = static function ($translation, $source, $domain) { return $domain === 'reginor-lite' ? (['Kursperioder' => 'Course periods', 'Aktiv nå' => 'Active now', 'Sjekk ledige plasser hos LetsReg' => 'Check places at LetsReg', 'Kurs' => '<script>unsafe()</script>'] [$source] ?? $translation) : $translation; };
$dynamic = static function ($value, $context, $key) { return $context === Wpml::CONTEXT && str_ends_with($key, '.description') ? 'Learn salsa together.' : $value; };
$pageFilter = null;
try {
    wp_set_current_user($admin); add_action('wpml_register_single_string', $register, 10, 3);
    $venue = $created[] = $repo->create('venue', ['title' => 'Teststed', 'address' => 'Testgata 1']);
    $room = $created[] = $repo->create('room', ['title' => 'Testsal', 'venue_id' => $venue]);
    $course = $created[] = $repo->create('course', ['title' => 'Nybegynner', 'description' => 'Lær salsa sammen.', 'level_description' => 'Ingen forkunnskaper.', 'dance_style' => 'Salsa', 'partner_info' => 'Kom alene.']);
    $p = ['title' => 'Pågående testperiode', 'timezone' => 'Europe/Oslo', 'start_date' => '2030-01-07', 'default_session_count' => 3,
        'default_room_id' => $room, 'default_price_minor' => 120000, 'default_price_basis' => 'person', 'visible_from' => '2030-01-01T00:00:00Z',
        'visible_until' => '2030-01-10T00:00:00Z', 'sales_from' => '2030-01-01T00:00:00Z', 'sales_until' => '2030-01-15T00:00:00Z',
        'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => []];
    // Build future sessions first, then advance time into the period.
    $clock->time = '2029-12-01T12:00:00Z';
    $period = $created[] = $repo->create('period', $p);
    $group = $created[] = $repo->createGroup($period, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'instructor_ids' => [], 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/synthetic']);
    $repo->confirm($repo->previewGroup($group, 1, []));
    foreach (['letsreg.com', 'www.letsreg.com', 'letsreg.no', 'www.letsreg.no'] as $host) {
        $current = $repo->get($group);
        $repo->confirm($repo->previewGroup($group, $current['version'], ['registration_url' => 'https://' . $host]));
        $assert($repo->previewPublication($period, 1, true)['errors'] === [], 'Gyldig LetsReg-domene avvises ved publisering: ' . $host);
    }
    foreach (['https://www.letsreg.no.evil.example/event/kurs', 'https://other.letsreg.no/event/kurs', 'https://www.letsreg.no:8443/event/kurs'] as $url) {
        $current = $repo->get($group);
        $repo->confirm($repo->previewGroup($group, $current['version'], ['registration_url' => $url]));
        $assert($repo->previewPublication($period, 1, true)['errors'] !== [], 'Ugodkjent domene eller port ble godkjent: ' . $url);
    }
    $unicodeUrl = 'https://www.letsreg.com/no/register/SalsaØvet1_4_26';
    $encodedUrl = 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26';
    foreach ([$unicodeUrl, $encodedUrl] as $url) {
        $current = $repo->get($group);
        $saved = $repo->confirm($repo->previewGroup($group, $current['version'], ['registration_url' => $url]));
        $assert($saved['state']['data']['registration_url'] === $encodedUrl, 'Norske tegn avvises eller URL-en dobbeltkodes ved lagring.');
        $assert($repo->previewPublication($period, 1, true)['errors'] === [], 'Kurslenken med Ø avvises ved publisering.');
    }
    $current = $repo->get($group);
    $repo->confirm($repo->previewGroup($group, $current['version'], ['registration_url' => 'https://www.letsreg.no']));
    $preview = $repo->previewPublication($period, 1, true);
    $assert($preview['errors'] === [], 'Valgfri instruktør eller ikke-blokkerende tidsinformasjon stopper publisering.');
    $assert(count($preview['warnings']) === 2, 'Informasjon om synlighet/sen påmelding mangler.');
    $repo->publish($preview);
    $assert(get_post_status($period) === 'publish', 'Publisering uten instruktør feilet.');
    $clock->time = '2030-01-08T12:00:00Z';
    $future = $created[] = $repo->create('period', array_replace($p, ['title' => 'Neste periode', 'start_date' => '2030-02-04']));
    $newest = $created[] = $repo->create('period', array_replace($p, ['title' => 'Sist opprettet', 'start_date' => '2030-03-04']));
    $rows = $overview->rows();
    $assert(array_key_first($rows) === $newest, 'Nyeste opprettede periode står ikke øverst.');
    $assert($rows[$period]['active'] && $rows[$period]['days'] === 13, 'Aktiv periode eller kalenderdager er feil.');
    $assert($rows[$future]['next'] && !$rows[$newest]['next'], 'Neste periode følger opprettelsesrekkefølge i stedet for oppstart.');
    $assert($rows[$future]['days'] === 27 && $rows[$period]['until'] === '2030-01-21', 'Periodedatoer skal komme fra faktiske kurskvelder.');
    $assert($rows[$period]['places'] === ['Teststed'], 'Sted mangler fra periodeoversikten.');
    $missingRoom = $created[] = $repo->createGroup($future, $course, ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00',
        'room_id' => 0, 'registration_status' => 'available', 'registration_url' => 'https://www.letsreg.com/event/synthetic']);
    $repo->confirm($repo->previewGroup($missingRoom, 1, []));
    $assert(str_contains(implode(' ', $repo->previewPublication($future, 1, true)['errors']), 'trenger en sal'), 'Valgfri instruktør opphever kravet om sal.');
    Wpml::flush();
    $assert(($registered['course.' . $course . '.description'] ?? '') === 'Lær salsa sammen.', 'Kurstext registreres ikke hos WPML.');
    $assert(!str_contains(json_encode(array_keys($registered)), 'registration_url') && !str_contains(json_encode(array_keys($registered)), 'history'), 'Operasjonelle data registreres for oversettelse.');
    $originalState = get_post_meta($group, ContentTypes::META, true);
    add_filter('wpml_translate_single_string', $dynamic, 10, 3); add_filter('gettext', $strings, 10, 3);
    $read = (new Catalog($clock))->read(); $g = $read['groups'][$group];
    $assert($g['registration_url'] === 'https://www.letsreg.no', 'Godkjent norsk LetsReg-lenke fjernes i offentlig visning.');
    $assert($g['description'] === 'Learn salsa together.' && $g['capacity']['label'] === 'Check places at LetsReg', 'Oversettelser brukes ikke i offentlig lesemodell.');
    $assert($g['instructors'] === [] && $g['registration_url'] !== '', 'Instruktørkravet gjeninnføres i offentlig visning.');
    $page = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Kurs']);
    $translatedPage = $created[] = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Courses']);
    update_option('rnl_course_page_id', $page);
    $pageFilter = static fn ($id, $type) => $type === 'page' && $id === $page ? $translatedPage : $id;
    add_filter('wpml_object_id', $pageFilter, 10, 2);
    $assert(PublicSite::pageId() === $translatedPage && str_contains(PublicSite::url($group), 'rnl_course=' . $group), 'Oversatt kursside mister kursidentiteten.');
    $html = (new Renderer())->render($read, ['rnl_course' => $group]);
    $assert(!str_contains($html, '<h4>Instruktører</h4>') && str_contains($html, 'Learn salsa together.'), 'Tom instruktørliste eller manglende oversatt detalj.');
    $schema = SchemaPresenter::group($g, PublicSite::url($group));
    $assert($schema['description'] === $g['description'] && $schema['hasCourseInstance']['inLanguage'] === str_replace('_', '-', get_locale()), 'Schema følger ikke språkvalget.');
    $assert(get_post_meta($group, ContentTypes::META, true) === $originalState, 'Oversettelse endrer kursdata eller historikk.');
    // Both input forms must project the same booking target; reads must not rewrite saved metadata.
    foreach ([$unicodeUrl, $encodedUrl] as $url) {
        $unicodeState = $originalState; $unicodeState['data']['registration_url'] = $url;
        update_post_meta($group, ContentTypes::META, wp_slash($unicodeState));
        $beforeRead = get_post_meta($group, ContentTypes::META, true);
        $unicodeRead = (new Catalog($clock))->read(); $unicodeGroup = $unicodeRead['groups'][$group];
        $assert($unicodeGroup['registration_url'] === $encodedUrl, 'Offentlig lesemodell mister lenken med norske tegn.');
        $unicodeHtml = (new Renderer())->render($unicodeRead, ['rnl_course' => $group]);
        $assert(str_contains($unicodeHtml, 'href="' . $encodedUrl . '"'), 'Påmeldingsknappen har feil kodet URL.');
        $unicodeSchema = SchemaPresenter::group($unicodeGroup, PublicSite::url($group));
        $assert($unicodeSchema['hasCourseInstance']['offers']['url'] === $encodedUrl, 'Schema og påmeldingsknapp har ulike adresser.');
        $assert(get_post_meta($group, ContentTypes::META, true) === $beforeRead, 'Lesing av Unicode-lenken endrer lagrede metadata.');
    }
    update_post_meta($group, ContentTypes::META, wp_slash($originalState));
    $_GET = ['page' => 'reginor-lite']; $_POST = [];
    ob_start(); CoursePage::render(); $adminHtml = ob_get_clean();
    $assert(str_contains($adminHtml, 'Course periods') && str_contains($adminHtml, 'rnl-period-table'), 'Oversatt periodeoversikt mangler tabell.');
    $assert(strpos($adminHtml, 'Sist opprettet') < strpos($adminHtml, 'Neste periode'), 'Tabellen har feil rekkefølge.');
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $period];
    ob_start(); CoursePage::render(); $adminHtml = ob_get_clean();
    $assert(!str_contains($adminHtml, '<script>unsafe()') && str_contains($adminHtml, '&lt;script&gt;'), 'Oversatte tabelltekster blir ikke escaped.');
    $assert(str_contains($adminHtml, 'Kurs i perioden') && !str_contains($adminHtml, 'name="data[visible_from]"'), 'Periodesiden viser alle oppsett samtidig.');
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $future, 'step' => 'publish'];
    ob_start(); CoursePage::render(); $adminHtml = ob_get_clean();
    $assert(!str_contains($adminHtml, 'name="outside"') && !str_contains($adminHtml, 'name="late_sales"') && str_contains($adminHtml, 'Kontroller og forhåndsvis publisering'), 'Ekstra godkjenningsbokser finnes fortsatt.');
    $clock->time = '2030-01-10T00:00:00Z';
    $assert((new Catalog($clock))->read()['groups'] === [], 'Ikke-blokkerende varsel opphever faktisk synlighetsgrense.');
    $clock->time = '2030-01-21T12:00:00Z'; $rows = $overview->rows();
    $assert($rows[$period]['active'] && $rows[$period]['days'] === 0, 'Siste lokale kursdag har feil antall dager.');
    $clock->time = '2030-01-22T00:00:00Z'; $assert(!$overview->rows()[$period]['active'], 'Avsluttet periode er fortsatt grønn.');
    $dst = $created[] = $repo->create('period', array_replace($p, ['title' => 'Sommertid', 'start_date' => '2030-04-01']));
    $clock->time = '2030-03-30T12:00:00Z';
    $assert($overview->rows()[$dst]['days'] === 2, 'Dager til oppstart feiler ved overgang til sommertid.');
    // Date limits belong to the teaching period, never to sales/visibility windows.
    $clock->time = '2029-12-01T12:00:00Z';
    $bounded = $created[] = $repo->create('period', array_replace($p, ['title' => 'Avgrenset periode', 'end_date' => '2030-01-21']));
    $bg = $created[] = $repo->createGroup($bounded, $course, ['weekday' => 1, 'start_time' => '16:00', 'end_time' => '17:00']);
    $reject = static function (callable $operation, string $message) use ($assert): void {
        try { $operation(); } catch (InvalidArgumentException $error) { $assert(true, $message); return; }
        $assert(false, $message);
    };
    $break = ['id' => wp_generate_uuid4(), 'from' => '2030-01-21', 'until' => '2030-01-21', 'reason' => 'Kursfri'];
    $assert(\RegiNor\Lite\Infrastructure\StateSchema::validate('period', array_replace($p, ['end_date' => '2030-01-21', 'breaks' => [$break]]))['breaks'] === [$break], 'Opphold på siste periodedag skal tillates.');
    foreach (['2030-01-06', '2030-01-22'] as $outside) {
        $badBreak = array_replace($break, ['from' => $outside, 'until' => $outside]);
        $reject(fn () => $repo->update($bounded, 1, array_replace($p, ['end_date' => '2030-01-21', 'breaks' => [$badBreak]])), 'Opphold utenfor perioden ble lagret.');
        $reject(fn () => $repo->previewGroup($bg, 1, ['breaks' => [$badBreak]]), 'Kursopphold utenfor perioden ble godtatt.');
    }
    $reject(fn () => $repo->update($bounded, 1, array_replace($p, ['end_date' => '2030-01-06'])), 'Sluttdato før start ble tillatt.');
    $reject(fn () => $repo->previewGroup($bg, 1, ['start_date' => '2030-01-06']), 'Kursstart før perioden ble tillatt.');
    $reject(fn () => $repo->previewGroup($bg, 1, ['breaks' => [$break]]), 'Opphold kunne forlenge kurset forbi periodens slutt.');
    $assert($repo->get($bg)['version'] === 1 && $repo->get($bounded)['version'] === 1, 'Avviste datoendringer skrev data.');
    $preview = $repo->previewGroup($bg, 1, []);
    $assert(end($preview['data']['sessions'])['date'] === '2030-01-21', 'Kurset kan ikke avsluttes på selve sluttdatoen.');
    $repo->confirm($preview);
    $sessionId = $preview['data']['sessions'][0]['id'];
    $outsideSession = $repo->previewSessionChange($bg, 2, $sessionId, ['date' => '2030-01-22', 'status' => 'moved', 'reason' => 'Salen opptatt']);
    $assert($outsideSession['issues'] !== [], 'Flytting utenfor perioden mangler forklaring.');
    $reject(fn () => $repo->confirm($outsideSession), 'Flytting utenfor perioden kunne bekreftes.');
    $shortened = array_replace($p, ['end_date' => '2030-01-14']);
    $repo->update($bounded, 1, $shortened);
    $assert($repo->previewReview($bg, 2)['issues'] !== [], 'Behold datoer omgikk ny sluttdato.');
    $assert($repo->previewPublication($bounded, 2, true)['errors'] !== [], 'Publisering omgikk ny sluttdato.');
    $replanned = $repo->previewGroup($bg, 2, ['session_count' => 2]);
    $assert($replanned['issues'] === [] && count($replanned['data']['sessions']) === 2, 'Gamle økter hindret gyldig omplanlegging.');
    $repo->confirm($replanned);
    $cancelled = $repo->previewSessionChange($bg, 3, $sessionId, ['status' => 'cancelled', 'reason' => 'Avlyst']);
    $repo->confirm($cancelled);
    $replacement = $repo->previewReplacement($bg, 4, $sessionId, '2030-01-15', 'Ny kveld');
    $assert($replacement['issues'] !== [], 'Erstatningskveld omgikk sluttdato.');
    $copy = $repo->copyPeriod($bounded, 2, 'Neste avgrensede periode', '2030-04-01');
    $created[] = $copy; foreach (array_keys($repo->groups($copy)) as $copiedGroup) { $created[] = $copiedGroup; }
    $assert($repo->get($copy)['data']['end_date'] === null, 'Kopiering beholdt gammel sluttdato.');
    $raw = array_map(static fn ($value) => is_scalar($value) ? (string) $value : $value, $p);
    foreach (['visible_from', 'visible_until', 'sales_from', 'sales_until'] as $key) { $raw[$key] = ''; }
    $raw['default_price_minor'] = '1200,00'; $raw['end_date'] = '2030-01-21';
    $empty = ['id' => '', 'from' => '', 'until' => '', 'reason' => ''];
    $raw['breaks'] = [$empty, array_replace($empty, ['from' => '2030-01-07', 'reason' => 'Fridag']), $empty];
    $parsed = \RegiNor\Lite\Admin\CourseActions::parse('period', $raw, []);
    $assert(count($parsed['breaks']) === 1 && $parsed['breaks'][0]['until'] === '2030-01-07', 'Tom til-dato ble ikke én fridag, eller tomme rader ble lagret.');
    $raw['breaks'][1]['reason'] = '';
    $reject(fn () => \RegiNor\Lite\Admin\CourseActions::parse('period', $raw, []), 'Opphold uten forklaring ble lagret.');
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $bounded, 'step' => 'period'];
    ob_start(); CoursePage::render(); $formHtml = ob_get_clean();
    $assert(substr_count($formHtml, 'data-break-fallback') === 1 && substr_count($formHtml, 'data-add-break') === 1, 'Skjemaet tilbyr flere tomme opphold samtidig.');
    preg_match('/data-registration-hosts="([^"]+)"/', $formHtml, $hostsAttribute);
    $editorHosts = json_decode(html_entity_decode($hostsAttribute[1] ?? '', ENT_QUOTES), true);
    $assert($editorHosts === \RegiNor\Lite\Infrastructure\RegistrationDomains::allowed() && in_array('www.letsreg.no', $editorHosts, true), 'Skjemaets instant-validering bruker en annen domeneliste.');
    $assert(str_contains($formHtml, 'data-period-end="2030-01-14"') && str_contains($formHtml, 'name="data[end_date]"'), 'Skjemaet mangler undervisningsgrensen.');
    preg_match_all('/<(?:(?:input)|(?:select)|(?:textarea))\b[^>]*data-field="([^"]+)"[^>]*>/', $formHtml, $controls);
    foreach ($controls[0] as $control) { $assert(str_contains($control, 'aria-describedby='), 'Felt er ikke koblet til hjelp/feil.'); }
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $bounded, 'group' => (string) $bg];
    ob_start(); CoursePage::render(); $formHtml = ob_get_clean();
    $assert(substr_count($formHtml, 'class="rnl-session-tools"') === 1 && !str_contains($formHtml, 'Endre kurskveld '), 'Kurskvelder har fortsatt dominerende gjentatte hovedhandlinger.');
    $assert(str_contains($formHtml, 'https://www.letsreg.com/event/ditt-kurs') && str_contains($formHtml, 'data-field="registration_url"'), 'LetsReg-feltet mangler eksempel/valideringskobling.');
    $reflection = new ReflectionClass(CoursePage::class);
    $reflection->getProperty('error')->setValue(null, 'Testfeil');
    $reflection->getProperty('submitted')->setValue(null, [
        'command' => 'save_course_description', 'id' => (string) $bg,
        'data' => ['description' => 'Behold min beskrivelse ved feil', 'level_description' => 'Behold mine forkunnskaper ved feil'],
    ]);
    ob_start(); CoursePage::render(); $textHtml = ob_get_clean();
    $assert(str_contains($textHtml, '>Behold min beskrivelse ved feil</textarea>')
        && str_contains($textHtml, '>Behold mine forkunnskaper ved feil</textarea>'),
        'Beskrivelsesfeil mister tekstene i kursoppsettet.');
    $reflection->getProperty('submitted')->setValue(null, ['command' => 'save', 'kind' => 'period', 'id' => (string) $bounded, 'data' => array_replace($raw, ['breaks' => array_fill(0, 9, $empty)])]);
    $_GET = ['page' => 'reginor-lite', 'period' => (string) $bounded, 'step' => 'period'];
    ob_start(); CoursePage::render(); $formHtml = ob_get_clean();
    $assert(substr_count($formHtml, 'data-break-row>') === 1, 'Gjentatt skjemafeil mangedobler tomme opphold.');
    $raw['breaks'] = [$empty, array_replace($empty, ['from' => '2030-01-07', 'reason' => 'Behold denne forklaringen'])];
    $reflection->getProperty('submitted')->setValue(null, ['command' => 'save', 'kind' => 'period', 'id' => (string) $bounded, 'data' => $raw]);
    ob_start(); CoursePage::render(); $formHtml = ob_get_clean();
    $assert(str_contains($formHtml, 'value="Behold denne forklaringen"') && substr_count($formHtml, 'data-break-row>') === 2, 'Delvis utfylte opphold mistes ved fjerning av tomme rader.');
    $_GET = ['page' => 'reginor-lite', 'group' => (string) $bg];
    $reflection->getProperty('submitted')->setValue(null, ['command' => 'session', 'id' => (string) $bg, 'session_id' => $sessionId, 'data' => ['date' => '2030-01-22', 'reason' => 'Behold forklaring ved feil']]);
    ob_start(); CoursePage::render(); $formHtml = ob_get_clean();
    $assert(str_contains($formHtml, 'class="rnl-session-tools" open') && str_contains($formHtml, 'value="Behold forklaring ved feil"'), 'Feil lukker kurskvelden eller mister utfylt forklaring.');
    $assert(str_contains($formHtml, 'data-period-end="2030-01-14"'), 'Direktelenke til kurs mistet periodegrensene.');
    $reflection->getProperty('error')->setValue(null, ''); $reflection->getProperty('submitted')->setValue(null, []);

    // A one-off intro before the normal period requires an explicit, course-local approval.
    $clock->time = '2029-12-01T12:00:00Z';
    $introPeriod = $created[] = $repo->create('period', array_replace($p, ['title' => 'Periode med intro',
        'end_date' => '2030-01-21', 'visible_from' => '2029-12-01T00:00:00Z', 'visible_until' => '2030-02-01T00:00:00Z']));
    $introFields = ['title' => 'Introkurs', 'weekday' => 1, 'first_date' => '2029-12-03', 'latest_date' => '2029-12-03', 'session_count' => 1,
        'start_time' => '11:00', 'end_time' => '13:30', 'registration_status' => 'available',
        'registration_url' => 'https://www.letsreg.com/event/synthetic-intro'];
    $reject(fn () => $repo->createGroup($introPeriod, $course, $introFields), 'Tidlig kursstart uten bekreftelse ble tillatt.');
    $reject(fn () => $repo->createGroup($introPeriod, $course, $introFields + ['allow_early_start' => false]), 'Avslått unntak ble tillatt.');
    $intro = $created[] = $repo->createGroup($introPeriod, $course, $introFields + ['allow_early_start' => true]);
    $periodBeforeIntro = $repo->get($introPeriod);
    $introPreview = $repo->previewGroup($intro, 1, []);
    $assert($introPreview['issues'] === [] && array_column($introPreview['data']['sessions'], 'date') === ['2029-12-03'], 'Godkjent introkurs får feil dato/antall.');
    $assert(str_contains(implode(' ', $introPreview['warnings']), 'Bekreftet unntak'), 'Forhåndsvisning mangler bekreftet datounntak.');
    $repo->confirm($introPreview);
    $introState = $repo->get($intro);
    $assert($introState['data']['allow_early_start'] === true && $repo->get($introPeriod) === $periodBeforeIntro, 'Datounntaket lagres ikke lokalt eller endrer perioden.');
    foreach ([['allow_early_start' => false], ['first_date' => '2029-12-04'], ['first_date' => '2030-01-28'],
        ['breaks' => [array_replace($break, ['from' => '2029-12-02', 'until' => '2029-12-02'])]]] as $badIntro) {
        $reject(fn () => $repo->previewGroup($intro, 2, $badIntro), 'Datounntaket omgår bekreftelse, ukedag eller datogrenser.');
    }
    $assert($repo->get($intro) === $introState, 'Avvist endring skrev til introkurset.');
    $introSession = $introState['data']['sessions'][0]['id'];
    $tooEarly = $repo->previewSessionChange($intro, 2, $introSession, ['date' => '2029-12-02', 'status' => 'moved', 'reason' => 'Prøve']);
    $assert($tooEarly['issues'] !== [], 'Flytting før godkjent første dato ble tillatt.');
    $normal = $created[] = $repo->createGroup($introPeriod, $course, ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'registration_status' => 'closed']);
    $normalPreview = $repo->previewGroup($normal, 1, []); $repo->confirm($normalPreview);
    $assert($normalPreview['data']['sessions'][0]['date'] === '2030-01-07' && !$normalPreview['data']['allow_early_start'], 'Introkurset endrer de vanlige kursene.');
    $introCopy = $repo->copyPeriod($introPeriod, 1, 'Ny introperiode', '2030-04-01'); $created[] = $introCopy;
    foreach ($repo->groups($introCopy) as $copiedId => $copiedState) {
        $created[] = $copiedId;
        $assert(!$copiedState['data']['allow_early_start'] && $copiedState['data']['first_date'] === null, 'Kopi beholder godkjenning av datounntak.');
    }
    $_GET = ['page' => 'reginor-lite', 'group' => (string) $intro]; $_POST = [];
    ob_start(); CoursePage::render(); $introForm = ob_get_clean();
    $assert(str_contains($introForm, 'name="data[allow_early_start]" value="0"') && preg_match('/type="checkbox"[^>]*name="data\[allow_early_start\]"[^>]*checked/', $introForm) === 1, 'Bekreftet unntak mangler avkryssing eller verdi ved fjerning.');
    $assert(str_contains($introForm, 'data-course-start="2029-12-03"'), 'Øktredigering mangler godkjent datogrense.');
    $formRaw = array_diff_key($introState['data'], array_flip(['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping']));
    $formRaw = array_map(static fn ($value) => is_array($value) ? $value : (string) $value, $formRaw);
    $formRaw['price_minor'] = '1200'; $formRaw['allow_early_start'] = '0';
    $parsed = \RegiNor\Lite\Admin\CourseActions::parse('group', $formRaw, $introState['data']);
    $assert($parsed['allow_early_start'] === false, 'Uavkrysset skjema tolkes som godkjent unntak.');
    $formRaw['allow_early_start'] = '1';
    $assert(\RegiNor\Lite\Admin\CourseActions::parse('group', $formRaw, $introState['data'])['allow_early_start'] === true, 'Avkrysset skjema mister unntaket.');
    $overlap = $created[] = $repo->createGroup($introPeriod, $course, $introFields + ['allow_early_start' => true]);
    $overlapPreview = $repo->previewGroup($overlap, 1, []);
    $assert($overlapPreview['conflicts'] !== [], 'Godkjent datounntak omgår salkollisjoner.');
    $repo->confirm($overlapPreview);
    $assert($repo->previewPublication($introPeriod, 1, true)['errors'] !== [], 'Godkjent datounntak tillater publisering med kollisjon.');
    $repo->groupLifecycle($overlap, 2, 1, false);
    $publication = $repo->previewPublication($introPeriod, 1, true);
    $assert($publication['errors'] === [] && str_contains(implode(' ', $publication['warnings']), 'Introkurs: Bekreftet unntak'), 'Publisering stopper godkjent intro eller mangler kursnavn og unntak.');
    $repo->publish($publication);
    $introCatalog = (new Catalog($clock))->read(); $introPublic = $introCatalog['groups'][$intro];
    $assert($introPublic['early_start'] && $introPublic['count'] === 1 && $introPublic['first'] === '2029-12-03T10:00:00Z', 'Offentlig dato eller antall for introkurs er feil.');
    $introSchema = SchemaPresenter::group($introPublic, PublicSite::url($intro));
    $assert($introSchema['hasCourseInstance']['startDate'] === $introPublic['first'] && count($introSchema['hasCourseInstance']['courseSchedule']) === 1, 'Schema bruker periodestart i stedet for introøkten.');
    $introWeek = (new Renderer())->render($introCatalog, ['rnl_period' => $introPeriod, 'rnl_view' => 'week']);
    $assert(str_contains($introWeek, 'Dato: 3. desember 2029'), 'Ukeskalenderen mangler den faktiske datoen for introøkten før perioden.');
    $calendarEvents = get_post_meta($intro, \RegiNor\Lite\Infrastructure\CourseCalendar::META, true)['default'];
    $ics = \RegiNor\Lite\Domain\Calendar\ICalendar::render('Intro', 'synthetic-intro', $calendarEvents);
    $assert(str_contains($ics, 'DTSTART:20291203T100000Z') && substr_count($ics, 'BEGIN:VEVENT') === 1, 'Kalenderfilen bruker ikke den ene faktiske introøkten.');
    $clock->time = '2029-11-30T23:59:59Z';
    $assert(!isset((new Catalog($clock))->read()['groups'][$intro]), 'Datounntaket omgår synlighetsvinduet.');
} finally {
    remove_action('wpml_register_single_string', $register, 10); remove_filter('gettext', $strings, 10); remove_filter('wpml_translate_single_string', $dynamic, 10);
    if ($pageFilter) { remove_filter('wpml_object_id', $pageFilter, 10); }
    wp_set_current_user($admin); foreach (array_reverse($created) as $id) { wp_delete_post($id, true); }
    if ($oldPage === null) { delete_option('rnl_course_page_id'); } else { update_option('rnl_course_page_id', $oldPage); }
    wp_set_current_user($original);
}
WP_CLI::success('Periode-, kontroll- og oversettelsesprøver bestått: ' . $checks . '. Testdata er ryddet bort.');
