<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Publication\Period;
use RegiNor\Lite\Domain\Publication\SystemClock;
use RegiNor\Lite\Domain\Scheduling\ConflictValidator;
use RegiNor\Lite\Domain\Scheduling\ScheduleReplanner;
use RegiNor\Lite\Domain\Scheduling\SessionAllocation;
use RegiNor\Lite\Domain\Scheduling\StoredSession;

/** Internal authenticated repository. No public/REST controller exposes this aggregate. */
final class CourseRepository
{
    use CourseWorkflow;
    use CourseImport;
    use CourseSourceReview;
    public function __construct(private readonly Clock $clock = new SystemClock()) {}

    public function create(string $kind, array $data): int
    {
        return Mutation::run(fn () => $this->createLocked($kind, $data));
    }

    private function createLocked(string $kind, array $data): int
    {
        $type = ContentTypes::type($kind);
        $this->requireCapability(get_post_type_object($type)->cap->create_posts);
        if ($kind === 'group') {
            throw new InvalidArgumentException(__('Opprett kurset fra en periode med createGroup().', 'reginor-lite'));
        }
        $data = $this->validate($kind, $data);
        return $this->insert($kind, $data);
    }

    public function createGroup(int $periodId, int $courseId, array $fields): int
    {
        return Mutation::run(fn () => $this->createGroupLocked($periodId, $courseId, $fields));
    }

    private function createGroupLocked(int $periodId, int $courseId, array $fields): int
    {
        return $this->insert('group', $this->groupData($periodId, $courseId, $fields));
    }

    private function groupData(int $periodId, int $courseId, array $fields, ?array $newCourse = null, bool $copying = false): array
    {
        $this->requireCapability(get_post_type_object(ContentTypes::type('group'))->cap->create_posts);
        $period = $this->get($periodId, 'period');
        $course = ['data' => $newCourse ?? $this->resource($courseId, 'course')];
        if ($newCourse !== null && $courseId !== 0) { throw new InvalidArgumentException(__('Velg enten ny eller eksisterende kursbeskrivelse.', 'reginor-lite')); }
        if (get_post_status($periodId) !== 'draft') {
            throw new RuntimeException(__('Ta perioden tilbake til kladd før du legger til kurs.', 'reginor-lite'), 409);
        }
        foreach (['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping'] as $protected) {
            if (array_key_exists($protected, $fields)) {
                throw new InvalidArgumentException(__('Kursets tilhørighet og økter settes av systemet.', 'reginor-lite'));
            }
        }
        $data = array_replace($this->newGroupDefaults($periodId), ['course_id' => $courseId, 'title' => $course['data']['title']], $fields);
        // A period copy inherits an existing choice, including a retired level.
        if (!$copying) { $this->requireSelectableLevel($data); }
        return $this->validate('group', $data, $newCourse !== null);
    }

    private function insert(string $kind, array $data): int
    {
        $id = wp_insert_post(wp_slash(['post_type' => ContentTypes::type($kind), 'post_status' => 'draft',
            'post_title' => $data['title'], 'post_author' => get_current_user_id()]), true);
        if (is_wp_error($id)) {
            throw new RuntimeException(__('Kunne ikke opprette kursobjektet.', 'reginor-lite'));
        }
        Mutation::touch($id);
        try {
            if (!add_post_meta($id, ContentTypes::META, wp_slash($this->envelope($data)), true)) {
                throw new RuntimeException(__('Kunne ikke lagre kursobjektets data.', 'reginor-lite'));
            }
        } catch (\Throwable $error) {
            // Only remove the new, incomplete object created by this call.
            wp_delete_post($id, true);
            throw $error;
        }
        return $id;
    }

    public function get(int $id, ?string $expectedKind = null, bool $includeTrash = false): array
    {
        clean_post_cache($id);
        $post = get_post($id);
        $kind = $post ? array_search($post->post_type, ContentTypes::TYPES, true) : false;
        if ($kind === false || ($expectedKind !== null && $kind !== $expectedKind) || (!$includeTrash && $post->post_status === 'trash')) {
            throw new RuntimeException(__('Kursobjektet finnes ikke.', 'reginor-lite'), 404);
        }
        ContentTypes::type($kind);
        $this->requireCapability('edit_post', $id);
        // Re-read after a failed CAS; do not trust an old process-local metadata cache.
        wp_cache_delete($id, 'post_meta');
        $rows = get_post_meta($id, ContentTypes::META, false);
        if (count($rows) !== 1 || !is_array($rows[0])) {
            throw new RuntimeException(__('Kursobjektet har manglende eller tvetydig lagring.', 'reginor-lite'));
        }
        if (is_wp_error(rest_validate_value_from_schema($rows[0], StateSchema::envelope($kind)))) {
            throw new RuntimeException(__('Kursobjektets lagrede format er ugyldig. Ingen data er endret.', 'reginor-lite'));
        }
        return $rows[0];
    }

    public function update(int $id, int $expectedVersion, array $data): array
    {
        return Mutation::run(fn () => $this->updateLocked($id, $expectedVersion, $data));
    }

    private function updateLocked(int $id, int $expectedVersion, array $data): array
    {
        $previous = $this->get($id);
        $kind = array_search(get_post_type($id), ContentTypes::TYPES, true);
        if ($kind === 'group') {
            throw new InvalidArgumentException(__('Endringer av kurs krever forhåndsvisning og bekreftelse.', 'reginor-lite'));
        }
        $this->assertVersion($previous, $expectedVersion);
        return $this->write($id, $previous, $this->validate($kind, $data));
    }

    public function previewGroup(int $id, int $expectedVersion, array $changes): array
    {
        $previous = $this->get($id, 'group');
        $this->assertVersion($previous, $expectedVersion);
        foreach (['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping'] as $field) {
            if (array_key_exists($field, $changes)) {
                throw new InvalidArgumentException(__('Tilhørighet og lagrede økter kan ikke erstattes gjennom vanlig omplanlegging.', 'reginor-lite'));
            }
        }
        $data = $this->validate('group', array_replace($previous['data'], $changes));
        $this->requireSelectableLevel($data, $previous['data']);
        $period = $this->get($data['period_id'], 'period');
        $planning = $data;
        if (($period['data']['end_date'] ?? null) !== null) {
            $planning['latest_date'] = min($planning['latest_date'] ?? $period['data']['end_date'], $period['data']['end_date']);
        }
        $plan = (new ScheduleReplanner())->preview($previous['data']['sessions'], StateSchema::request($planning, $period['data']['breaks']),
            $this->clock->now(), $data['room_id'], $data['instructor_ids'], 'wp_generate_uuid4');
        $data['sessions'] = $plan['sessions'];
        $data['period_version'] = $period['version'];
        return $this->proposal($id, $previous, $data, $period, $plan['changes'], $plan['issues']);
    }

    /** Narrow update: identity and optionally the checked provider URL; no schedule/status changes. */
    /** Change only registration policy; preserve publication, schedule and parent version. */
    public function saveRegistrationStatus(int $id, int $expectedVersion, string $status): array
    {
        return Mutation::run(function () use ($id, $expectedVersion, $status): array {
            $previous = $this->get($id, 'group');
            $this->assertVersion($previous, $expectedVersion);
            if (!in_array($status, StateSchema::data('group')['properties']['registration_status']['enum'], true)) {
                throw new InvalidArgumentException(__('Velg en gyldig påmeldingsstatus.', 'reginor-lite'));
            }
            $data = $previous['data'];
            $this->get($data['period_id'], 'period');
            if ($status === 'automatic' && empty($data['letsreg_mapping'])) {
                throw new InvalidArgumentException(__('Koble kurset til arrangement og priskategorier hos LetsReg før du velger automatisk status.', 'reginor-lite'));
            }
            if (in_array($status, ['automatic', 'available', 'waiting'], true) && ($data['registration_url'] === ''
                || !in_array(strtolower((string) wp_parse_url($data['registration_url'], PHP_URL_HOST)), RegistrationDomains::allowed(), true)
                || !in_array(wp_parse_url($data['registration_url'], PHP_URL_PORT), [null, 443], true))) {
                throw new InvalidArgumentException(__('Kurset mangler en gyldig LetsReg-lenke. Legg inn lenken i kursoppsettet før du åpner påmeldingen.', 'reginor-lite'));
            }
            if ($status === 'external' && ($data['registration_url'] === '' || !in_array(wp_parse_url($data['registration_url'], PHP_URL_PORT), [null, 443], true))) { throw new InvalidArgumentException(__('Legg inn egen påmeldingslenke under Pris og påmelding før du velger denne statusen.', 'reginor-lite')); }
            if ($status === 'dropin' && ($data['dropin_price_minor'] ?? null) === null) { throw new InvalidArgumentException(__('Oppgi drop-in-pris i kursoppsettet først. Bruk 0 hvis timen er gratis.', 'reginor-lite')); }
            if ($data['registration_status'] === $status) { return $previous; }
            $data['registration_status'] = $status;
            $next = $this->write($id, $previous, $data, 'saved', true);
            if (get_post_status($id) === 'publish') { CourseCalendar::published(new \RegiNor\Lite\Frontend\Catalog($this->clock), (int) $data['period_id']); }
            return $next;
        }, true);
    }

    public function saveLetsRegMapping(int $id, int $expectedVersion, ?array $mapping, bool $useEventUrl = false): array
    {
        LetsRegMapping::authorize();
        return Mutation::run(function () use ($id, $expectedVersion, $mapping, $useEventUrl): array {
            $previous = $this->get($id, 'group');
            $this->assertVersion($previous, $expectedVersion);
            if (!in_array(get_post_status($id), ['draft', 'publish'], true)) {
                throw new RuntimeException(__('Kurset kan ikke kobles i denne statusen.', 'reginor-lite'), 409);
            }
            $data = $this->validate('group', array_replace($previous['data'], ['letsreg_mapping' => $mapping]));
            LetsRegMapping::assertCurrent($data['letsreg_mapping']);
            if ($useEventUrl) {
                $source = LetsRegMapping::source();
                if (!$mapping || !$source || $source['verification_id'] !== $mapping['verification_id'] || empty($source['event']['event_url'])) {
                    throw new InvalidArgumentException(__('LetsReg returnerte ingen gyldig påmeldingslenke. Behold eksisterende lenke eller fyll den inn i kursoppsettet.', 'reginor-lite'));
                }
                $data['registration_url'] = $source['event']['event_url'];
            }
            $next = $this->write($id, $previous, $data, 'saved', true);
            $reviewed = get_post_meta($id, LetsRegChanges::META, true);
            if ($mapping && (!is_array($reviewed) || ($reviewed['key'] ?? '') !== LetsRegChanges::key($mapping))) {
                LetsRegChanges::baseline($id, $mapping, LetsRegMapping::source()['event']);
            } elseif (!$mapping) { delete_post_meta($id, LetsRegChanges::META); }
            return $next;
        }, true);
    }

    public function previewLetsRegMapping(int $id, int $expectedVersion, ?array $mapping): array
    {
        LetsRegMapping::authorize();
        $previous = $this->get($id, 'group');
        $this->assertVersion($previous, $expectedVersion);
        if (get_post_status($id) !== 'draft') { throw new RuntimeException(__('Ta perioden tilbake til kladd før du endrer LetsReg-koblingen.', 'reginor-lite'), 409); }
        $data = $this->validate('group', array_replace($previous['data'], ['letsreg_mapping' => $mapping]));
        LetsRegMapping::assertCurrent($data['letsreg_mapping']);
        $proposal = $this->proposal($id, $previous, $data, $this->get($data['period_id'], 'period'), [], []);
        unset($proposal['token']);
        $proposal['mapping_change'] = true;
        $proposal['token'] = $this->signature($proposal);
        return $proposal;
    }

    public function previewSessionChange(int $id, int $expectedVersion, string $sessionId, array $changes): array
    {
        $previous = $this->get($id, 'group');
        $this->assertVersion($previous, $expectedVersion);
        if (array_diff(array_keys($changes), ['date', 'start_time', 'end_time', 'room_id', 'instructor_ids', 'status', 'reason'])) {
            throw new InvalidArgumentException(__('Øktens identitet, opprinnelige dato og tidssone kan ikke endres.', 'reginor-lite'));
        }
        $data = $previous['data'];
        $found = false;
        foreach ($data['sessions'] as $i => $before) {
            if ($before['id'] !== $sessionId) {
                continue;
            }
            $found = true;
            if (StoredSession::time($before)->startsAt <= $this->clock->now()) {
                throw new InvalidArgumentException(__('En påbegynt eller avsluttet økt kan ikke endres gjennom fremtidig omplanlegging.', 'reginor-lite'));
            }
            $after = array_replace($before, $changes);
            if (!in_array($after['status'], ['moved', 'cancelled'], true)) {
                throw new InvalidArgumentException(__('Velg flyttet eller avlyst, med forklaring.', 'reginor-lite'));
            }
            $start = \RegiNor\Lite\Domain\LocalDateTime::at($after['date'], $after['start_time'], $after['timezone']);
            $end = \RegiNor\Lite\Domain\LocalDateTime::at($after['date'], $after['end_time'], $after['timezone']);
            if ($start <= $this->clock->now()) {
                throw new InvalidArgumentException(__('En kursøkt kan ikke flyttes bakover i tid.', 'reginor-lite'));
            }
            $after['starts_at'] = $start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $after['ends_at'] = $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $data['sessions'][$i] = $after;
            $diff = [['id' => $sessionId, 'before' => $before, 'after' => $after]];
        }
        if (!$found) {
            throw new RuntimeException(__('Økten tilhører ikke dette kurset.', 'reginor-lite'), 404);
        }
        $data = $this->validate('group', $data);
        $period = $this->get($data['period_id'], 'period');
        return $this->proposal($id, $previous, $data, $period, $diff, []);
    }

    private function proposal(int $id, array $previous, array $data, array $period, array $diff, array $issues): array
    {
        try { PlanningBounds::group($data, $period['data'], true); }
        catch (InvalidArgumentException $error) { $issues[] = $error->getMessage(); }
        foreach ($data['sessions'] as $session) {
            if ($session['status'] !== 'moved' || StoredSession::time($session)->startsAt <= $this->clock->now()) {
                continue;
            }
            foreach (array_merge($period['data']['breaks'], $data['breaks']) as $break) {
                if ($break['from'] <= $session['date'] && $session['date'] <= $break['until']) {
                    $issues[] = __('Et opphold treffer en manuelt flyttet kurskveld den ', 'reginor-lite') . $session['date'] . __('. Flytt kurskvelden eller juster oppholdet.', 'reginor-lite');
                }
            }
        }
        $proposal = ['id' => $id, 'version' => $previous['version'], 'period_version' => $period['version'],
            'data' => $data, 'changes' => $diff, 'issues' => array_values(array_unique($issues)),
            'conflicts' => $this->conflicts($id, $data),
            'warnings' => $this->windowWarnings($data, $period['data']), 'actor_id' => get_current_user_id()];
        $proposal['token'] = $this->signature($proposal);
        return $proposal;
    }

    /** Confirmation only accepts the exact preview; references and conflicts are checked again. */
    public function confirm(array $proposal): array
    {
        return Mutation::run(fn () => $this->confirmLocked($proposal));
    }

    private function confirmLocked(array $proposal): array
    {
        $token = $proposal['token'] ?? '';
        unset($proposal['token']);
        if (!is_string($token) || !hash_equals($this->signature($proposal), $token)
            || ($proposal['actor_id'] ?? 0) !== get_current_user_id()) {
            throw new RuntimeException(__('Forhåndsvisningen er ugyldig. Lag en ny forhåndsvisning.', 'reginor-lite'), 403);
        }
        $previous = $this->get($proposal['id'], 'group');
        $this->assertVersion($previous, $proposal['version']);
        $period = $this->get($previous['data']['period_id'], 'period');
        $this->assertVersion($period, $proposal['period_version']);
        if ($proposal['issues'] !== []) {
            throw new InvalidArgumentException(__('Avklar øktkonfliktene før oppsettet kan lagres.', 'reginor-lite'));
        }
        $data = $this->validate('group', $proposal['data']);
        $this->requireSelectableLevel($data, $previous['data']);
        if (!empty($proposal['mapping_change']) || ($data['letsreg_mapping'] ?? null) !== ($previous['data']['letsreg_mapping'] ?? null)) {
            LetsRegMapping::assertCurrent($data['letsreg_mapping'] ?? null);
        }
        PlanningBounds::group($data, $period['data'], true);
        foreach ($proposal['changes'] as $change) {
            foreach (['before', 'after'] as $side) {
                if ($change[$side] !== null && StoredSession::time($change[$side])->startsAt <= $this->clock->now()) {
                    throw new VersionConflict();
                }
            }
        }
        // Conflicts are permitted in a draft, but returned for explicit resolution before publication.
        $conflicts = $this->conflicts($proposal['id'], $data);
        $warnings = $this->windowWarnings($data, $period['data']);
        $result = $this->write($proposal['id'], $previous, $data);
        return ['state' => $result, 'conflicts' => $conflicts, 'warnings' => $warnings];
    }

    private function signature(array $proposal): string
    {
        return hash_hmac('sha256', wp_json_encode($proposal, JSON_THROW_ON_ERROR), wp_salt('auth'));
    }

    private function write(int $id, array $previous, array $data, string $event = 'saved', bool $lifecycle = false): array
    {
        if (!$lifecycle && get_post_status($id) !== 'draft') {
            throw new RuntimeException(__('Ta perioden tilbake til kladd før du endrer publiserte kurs.', 'reginor-lite'), 409);
        }
        Mutation::touch($id);
        $next = $this->envelope($data, $previous);
        $next['event'] = $event;
        if (strlen(serialize($next)) > 2 * 1024 * 1024) {
            throw new RuntimeException(__('Historikken har nådd lagringsgrensen. Ingen data er overskrevet; kontakt administrator.', 'reginor-lite'));
        }
        if (!update_post_meta($id, ContentTypes::META, wp_slash($next), $previous)) {
            throw new VersionConflict();
        }
        return $next;
    }

    private function envelope(array $data, ?array $previous = null): array
    {
        $history = $previous['history'] ?? [];
        if ($previous !== null) {
            unset($previous['history']);
            $history[] = $previous;
        }
        return ['version' => ($previous['version'] ?? 0) + 1, 'data' => $data,
            'actor_id' => get_current_user_id(), 'changed_at' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'history' => $history];
    }

    private function validate(string $kind, array $data, bool $pendingCourse = false): array
    {
        $data = StateSchema::validate($kind, $data, $pendingCourse);
        if ($kind === 'room') {
            $this->resource($data['venue_id'], 'venue');
        }
        if ($kind === 'period' && $data['default_room_id'] > 0) {
            $this->requireRoom($data['default_room_id']);
        }
        if ($kind === 'group') {
            if (!empty($data['level_id'])) { $this->resource($data['level_id'], 'level'); }
            PlanningBounds::group($data, $this->get($data['period_id'], 'period')['data']);
            if (!$pendingCourse) { $this->resource($data['course_id'], 'course'); }
            foreach (array_merge([$data], $data['sessions']) as $allocation) {
                if ($allocation['room_id'] > 0) {
                    $this->requireRoom($allocation['room_id']);
                }
                $types = apply_filters('rnl_instructor_post_types', get_option('rnl_instructor_types', []));
                foreach ($allocation['instructor_ids'] as $instructor) {
                    if (!in_array(get_post_type($instructor), $types, true) || get_post_status($instructor) !== 'publish') {
                        throw new InvalidArgumentException(__('Instruktøren må være en offentlig profil av en konfigurert innholdstype.', 'reginor-lite'));
                    }
                }
            }
        }
        return $data;
    }

    private function requireSelectableLevel(array $data, array $previous = []): void
    {
        $id = $data['level_id'] ?? 0;
        if ($id && $id !== ($previous['level_id'] ?? 0) && !$this->resource($id, 'level')['active']) {
            throw new InvalidArgumentException(__('Nivået er ikke tilgjengelig for nye valg. Velg et aktivt nivå, eller be administrator aktivere det under Kursinnhold og ressurser.', 'reginor-lite'));
        }
    }

    private function requireRoom(int $id): void
    {
        $room = $this->resource($id, 'room');
        $this->resource($room['venue_id'], 'venue');
    }

    private function assertVersion(array $state, int $expected): void
    {
        if ($state['version'] !== $expected) {
            throw new VersionConflict();
        }
    }

    private function requireCapability(string $capability, int $id = 0): void
    {
        if (!get_current_user_id() || !current_user_can($capability, $id)) {
            throw new RuntimeException(__('Du har ikke tilgang til dette kursoppsettet.', 'reginor-lite'), 403);
        }
    }

    /** Authenticated planning scope; public filtering must never use this enumeration. */
    public function groups(?int $periodId = null): array
    {
        $this->requireCapability(get_post_type_object(ContentTypes::type('group'))->cap->edit_others_posts);
        $ids = get_posts(['post_type' => ContentTypes::type('group'), 'post_status' => ['draft', 'pending', 'publish', 'private', 'future'],
            'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true]);
        $groups = [];
        foreach ($ids as $id) {
            $group = $this->get($id, 'group');
            $parent = get_post($group['data']['period_id']);
            if (!$parent || $parent->post_status === 'trash') {
                continue;
            }
            if ($periodId === null || $group['data']['period_id'] === $periodId) {
                $groups[$id] = $group;
            }
        }
        return $groups;
    }

    public function conflicts(?int $replacementId = null, ?array $replacement = null): array
    {
        $sessions = [];
        foreach ($this->groups() as $id => $group) {
            if ($id === $replacementId && $replacement !== null) {
                $group['data'] = $replacement;
            }
            $period = $this->get($group['data']['period_id'], 'period');
            if ($period['data']['cancelled'] || $group['data']['registration_status'] === 'cancelled') {
                continue;
            }
            foreach ($group['data']['sessions'] as $session) {
                $sessions[] = new SessionAllocation($id . '/' . $session['id'], StoredSession::time($session),
                    $session['room_id'] > 0 ? (string) $session['room_id'] : null,
                    array_map('strval', $session['instructor_ids']), $session['status'] === 'cancelled');
            }
        }
        // Import previews have no persisted group yet; include their allocations under ID 0.
        if ($replacementId === 0 && $replacement !== null && $replacement['registration_status'] !== 'cancelled'
            && !$this->get($replacement['period_id'], 'period')['data']['cancelled']) {
            foreach ($replacement['sessions'] as $session) {
                $sessions[] = new SessionAllocation('0/' . $session['id'], StoredSession::time($session),
                    $session['room_id'] > 0 ? (string) $session['room_id'] : null,
                    array_map('strval', $session['instructor_ids']), $session['status'] === 'cancelled');
            }
        }
        return (new ConflictValidator())->find($sessions);
    }

    public function periodModel(int $id): Period
    {
        $state = $this->get($id, 'period');
        $data = $state['data'];
        $starts = $ends = [];
        $hasGroup = false;
        foreach ($this->groups($id) as $groupId => $group) {
            if (get_post_status($groupId) !== 'publish') {
                continue;
            }
            $hasGroup = true;
            if ($group['data']['registration_status'] === 'cancelled') {
                continue;
            }
            foreach ($group['data']['sessions'] as $session) {
                if ($session['status'] === 'cancelled') {
                    continue;
                }
                $time = StoredSession::time($session);
                $starts[] = $time->startsAt;
                $ends[] = $time->endsAt;
            }
        }
        return new Period((string) $id, get_post_status($id),
            $data['visible_from'] ? new DateTimeImmutable($data['visible_from']) : null,
            $data['visible_until'] ? new DateTimeImmutable($data['visible_until']) : null,
            $starts ? min($starts) : null, $ends ? max($ends) : null, $hasGroup, $data['show_as_upcoming'], $data['cancelled']);
    }

    /** Period edits never silently rewrite groups; expose outstanding review work. */
    public function periodReview(int $id): array
    {
        $period = $this->get($id, 'period');
        $result = [];
        foreach ($this->groups($id) as $groupId => $group) {
            $result[$groupId] = [
                'needs_schedule_review' => $group['data']['period_version'] !== $period['version'],
                'warnings' => $this->windowWarnings($group['data'], $period['data']),
            ];
        }
        return $result;
    }

    private function windowWarnings(array $group, array $period): array
    {
        $outside = [];
        foreach ($group['sessions'] as $session) {
            if ($session['status'] === 'cancelled') { continue; }
            if (($period['visible_from'] !== null && $session['starts_at'] < $period['visible_from'])
                || ($period['visible_until'] !== null && $session['ends_at'] >= $period['visible_until'])) {
                $outside[] = $session['date'];
            }
        }
        if (!$outside) { return []; }
        sort($outside);
        $local = static fn (string $utc): string => (new DateTimeImmutable($utc))->setTimezone(new \DateTimeZone($period['timezone']))->format('d.m.Y H:i');
        return [sprintf(
            /* translators: 1: number of sessions, 2: earliest affected date, 3: latest affected date, 4: visibility start, 5: visibility end, 6: timezone. */
            __('%1$d kurskvelder (første berørte dato: %2$s, siste: %3$s) ligger helt eller delvis utenfor tidsrommet kursoversikten vises på nettsiden: %4$s – %5$s (%6$s). Dette er informasjon og blokkerer ikke lagring eller publisering. Kursdatoene beholdes. Endre «Synlig fra» eller «Synlig til» i kursperioden bare hvis oversikten skal vises tidligere eller senere.', 'reginor-lite'),
            count($outside), $outside[0], $outside[count($outside) - 1],
            $period['visible_from'] ? $local($period['visible_from']) : __('ingen fast start', 'reginor-lite'),
            $period['visible_until'] ? $local($period['visible_until']) : __('ingen fast slutt', 'reginor-lite'), $period['timezone']
        )];
    }
}
