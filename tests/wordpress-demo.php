<?php
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\Renderer;
use RegiNor\Lite\Domain\Publication\Clock;
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokalt WordPress.'); }
$demo = get_option('rnl_demo_courses_v1'); if (!$demo || ($demo['format'] ?? 1) !== 3) throw new RuntimeException('Kjør npm run demo:courses først.');
$original = get_current_user_id(); $checks = 0; $sessions = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($message); };
try {
    wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]); $repo = new CourseRepository();
    $clock = new class($demo['periods'][0]['from']) implements Clock {
        public function __construct(private string $from) {} public function now(): DateTimeImmutable { return new DateTimeImmutable($this->from . ' 20:00', new DateTimeZone('Europe/Oslo')); }
    };
    $catalog = (new Catalog($clock))->read();
    foreach ($demo['periods'] as $period) {
        $groups = $repo->groups($period['id']); $assert(count($groups) === 12, 'Perioden må ha tolv kurs.'); $slots = []; $late = 0; $delayedId = 0; $longer = 0;
        foreach ($groups as $id => $state) {
            $g = $state['data']; $slots[$g['room_id']][$g['weekday']][] = $g['start_time'];
            $assert(in_array($g['weekday'], [1, 2], true) && $g['start_time'] > '18:00', 'Feil dag/tid.');
            $assert($g['registration_status'] === 'later' && $g['registration_url'] === '', 'Demo tilbyr reell påmelding.');
            $minutes = static fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
            if ($minutes($g['end_time']) - $minutes($g['start_time']) === 90) {
                $longer++; $assert($g['weekday'] === 2 && $g['start_time'] === '20:55' && $g['end_time'] === '22:25', 'Tidsforskyvning må være 30 min senere start og 30 min lengre varighet.');
            }
            foreach ($g['sessions'] as $s) { $sessions++; $assert($s['date'] >= $period['from'] && $s['date'] <= $period['until'] && $s['room_id'] === $g['room_id'] && $s['start_time'] === $g['start_time'] && $s['end_time'] === $g['end_time'], 'Økt utenfor perioden eller i feil sal.'); }
            if ($g['first_date']) { $late++; $delayedId = $id; $assert($g['first_date'] === (new DateTimeImmutable($period['from']))->modify('+1 week')->format('Y-m-d') && count($g['sessions']) === 5, 'Forskyvning skal være en uke, fem kvelder.'); }
        }
        $assert(count($slots) === 2 && $late === 1 && $longer === 1, 'To saler og ett forskjøvet kurs kreves.');
        foreach ($slots as $days) { $assert(count($days) === 2 && count($days[1]) === 3 && count($days[2]) === 3, 'Tre kurs per sal per kveld kreves.'); }
        $assert(($catalog['groups'][$delayedId]['delayed_start'] ?? false) === true, 'Offentlig modell mangler senere oppstart.');
        $html = (new Renderer())->render($catalog, ['rnl_period' => $period['id'], 'rnl_view' => 'week']);
        $assert(substr_count($html, 'class="rnl-room-heading"') === 4 && preg_match_all('/class="rnl-week-course(?: [^"]*)?"/', $html) === 12, 'Kalenderen mangler to saler på begge dager.');
        $assert(str_contains($html, '20:55–22:25') && str_contains($html, 'grid-row:162 / 252'), 'Kalenderen mangler forskjøvet og lengre tidsblokk.');
        $assert(substr_count($html, 'Senere oppstart:') === 1, 'Forskjøvet oppstart vises ikke tydelig én gang.');
    }
    $assert($demo['periods'][1]['from'] === (new DateTimeImmutable($demo['periods'][0]['until']))->modify('+1 day')->format('Y-m-d'), 'Periodene følger ikke rett etter hverandre.');
    $assert($sessions === 142, 'Feil totalantall faktiske økter.');
} finally { wp_set_current_user($original); }
WP_CLI::success("$checks demo checks passed: 24 courses, 142 sessions, two rooms, two consecutive periods.");
