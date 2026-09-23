<?php
/** Explicit local-only demo data; never loaded by the plugin or run on activation. */
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Domain\Publication\Clock;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Testdata kan bare opprettes i lokalt WordPress.'); }
$admin = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
wp_set_current_user($admin);
$result = Mutation::run(static function (): array {
    $existing = get_option('rnl_demo_courses_v1');
    if ($existing && ($existing['format'] ?? 1) >= 3) { return $existing; }
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Oslo'));
    $start = $existing ? new DateTimeImmutable($existing['periods'][0]['from'], new DateTimeZone('Europe/Oslo')) : $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
    // Only synthetic fixture creation sees this clock; live scheduling rules stay unchanged.
    $clock = new class($start->modify('-1 day')) implements Clock {
        public function __construct(private DateTimeImmutable $time) {}
        public function now(): DateTimeImmutable { return $this->time; }
    };
    $repo = new CourseRepository($clock);
    if ($existing && ($existing['format'] ?? 1) === 2) {
        foreach ($existing['periods'] as $period) {
            $periodState = $repo->get($period['id'], 'period');
            $repo->lifecycle($period['id'], $periodState['version'], array_map(static fn ($g) => $g['version'], $repo->groups($period['id'])), 'draft');
            $group = $period['groups'][11];
            $state = $repo->get($group, 'group');
            $repo->confirm($repo->previewGroup($group, $state['version'], ['start_time' => '20:55', 'end_time' => '22:25']));
            $repo->publish($repo->previewPublication($period['id'], $repo->get($period['id'])['version'], true));
        }
        $existing['format'] = 3; update_option('rnl_demo_courses_v1', $existing, false); return $existing;
    }
    $created = $existing['created'] ?? [];
    if ($existing) {
        $old = $repo->get($existing['periods'][0]['groups'][0], 'group')['data'];
        $room = $old['room_id']; $venue = $repo->get($room, 'room')['data']['venue_id'];
        $courses = array_map(static fn ($id) => $repo->get($id, 'group')['data']['course_id'], array_slice($existing['periods'][0]['groups'], 0, 3));
    } else {
    $venue = $created[] = $repo->create('venue', ['title' => 'Demo – Dansestudio', 'address' => 'Eksempelgata 1 (testdata)']);
    $room = $created[] = $repo->create('room', ['title' => 'Demo – Sal A', 'venue_id' => $venue]);
    $courses = [];
    foreach ([['Salsa nybegynner', 'beginner', 'Ingen forkunnskaper.'], ['Salsa litt øvet', 'experienced', 'Du kjenner grunnstegene.'], ['Bachata åpent nivå', 'mixed', 'Vi tilpasser undervisningen til deltakerne.']] as [$title, $audience, $level]) {
        $courses[] = $created[] = $repo->create('course', ['title' => 'Demo – ' . $title, 'audience' => $audience,
            'description' => 'Dette er testdata for å prøve kursoversikten. Ingen reell påmelding eller betaling.',
            'level_description' => $level, 'dance_style' => str_starts_with($title, 'Bachata') ? 'Bachata' : 'Salsa',
            'partner_info' => 'Du kan komme alene. Vi bytter partner underveis.']);
    }
    }
    $roomB = $created[] = $repo->create('room', ['title' => 'Demo – Sal B', 'venue_id' => $venue]);
    $periods = [];
    $utc = static fn (DateTimeImmutable $date): string => $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    for ($round = 0; $round < 2; $round++) {
        $from = $start->modify('+' . ($round * 6) . ' weeks'); $until = $from->modify('+6 weeks -1 day');
        if ($existing) {
            $period = $existing['periods'][$round]['id'];
            $state = $repo->get($period, 'period');
            $repo->lifecycle($period, $state['version'], array_map(static fn ($g) => $g['version'], $repo->groups($period)), 'draft');
        } else {
        $period = $created[] = $repo->create('period', ['title' => 'Demo – ' . ($round ? 'Neste kursperiode' : 'Aktiv kursperiode'),
            'timezone' => 'Europe/Oslo', 'start_date' => $from->format('Y-m-d'), 'end_date' => $until->format('Y-m-d'),
            'default_session_count' => 6, 'default_room_id' => $room, 'default_price_minor' => 120000, 'default_price_basis' => 'person',
            'visible_from' => $utc($start->modify('-1 day')), 'visible_until' => $utc($until->modify('+1 day')),
            'sales_from' => $utc($start->modify('-1 day')), 'sales_until' => $utc($until->modify('+1 day')),
            'show_as_upcoming' => true, 'cancelled' => false, 'breaks' => [], 'default_view' => 'week']);
        }
        $groups = $existing['periods'][$round]['groups'] ?? [];
        foreach ($existing ? [$roomB] : [$room, $roomB] as $hall) {
        foreach ([1, 2] as $weekday) {
            foreach ([['18:15', '19:15'], ['19:20', '20:20'], ['20:25', '21:25']] as $i => [$begin, $end]) {
                $group = $groups[] = $created[] = $repo->createGroup($period, $courses[$i], ['weekday' => $weekday, 'start_time' => $hall === $roomB && $weekday === 2 && $i === 2 ? '20:55' : $begin,
                    'end_time' => $hall === $roomB && $weekday === 2 && $i === 2 ? '22:25' : $end, 'room_id' => $hall,
                    'first_date' => $hall === $roomB && $weekday === 1 && $i === 0 ? $from->modify('+1 week')->format('Y-m-d') : null,
                    'session_count' => $hall === $roomB && $weekday === 1 && $i === 0 ? 5 : 6, 'registration_status' => 'later', 'registration_url' => '',
                    'price_terms' => 'Eksempelpris for hele kurset. Testdata – ingen betaling.']);
                $repo->confirm($repo->previewGroup($group, 1, []));
            }
        }
        }
        $repo->publish($repo->previewPublication($period, $repo->get($period)['version'], true));
        $periods[] = ['id' => $period, 'from' => $from->format('Y-m-d'), 'until' => $until->format('Y-m-d'), 'groups' => $groups];
    }
    $page = \RegiNor\Lite\Frontend\PublicSite::pageId();
    if (!$page) {
        $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'RegiNor – demonstrasjon', 'post_content' => '[reginor_courses]'], true);
        if (is_wp_error($page)) { throw new RuntimeException($page->get_error_message()); }
        $created[] = $page; update_option('rnl_course_page_id', $page, false);
    }
    $result = ['format' => 3, 'created' => $created, 'periods' => $periods, 'page' => $page, 'url' => get_permalink($page)];
    update_option('rnl_demo_courses_v1', $result, false);
    return $result;
}, true);
WP_CLI::line(wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
