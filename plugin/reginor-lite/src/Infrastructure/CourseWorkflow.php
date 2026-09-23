<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RuntimeException;
use RegiNor\Lite\Domain\Scheduling\StoredSession;
use RegiNor\Lite\Domain\Scheduling\ScheduledTime;
use RegiNor\Lite\Domain\LocalDateTime;

/** Authenticated workflows sharing the repository's validation and atomic writer. */
trait CourseWorkflow
{
    public function resource(int $id, string $kind): array
    {
        return $this->resourceState($id, $kind)['data'];
    }

    /** Read-only resource snapshot for selection and optimistic import checks. */
    private function resourceState(int $id, string $kind): array
    {
        $this->requireCapability('rnl_select_resources');
        if (!in_array($kind, ['course', 'room', 'venue', 'level'], true) || get_post_type($id) !== ContentTypes::type($kind)
            || !in_array(get_post_status($id), ['draft', 'publish'], true)) {
            throw new RuntimeException(__('Oppføringen finnes ikke i det godkjente oppslaget.', 'reginor-lite'), 404);
        }
        wp_cache_delete($id, 'post_meta');
        $rows = get_post_meta($id, ContentTypes::META, false);
        $state = $rows[0] ?? null;
        if (count($rows) !== 1 || !is_array($state) || is_wp_error(rest_validate_value_from_schema($state, StateSchema::envelope($kind)))) { throw new RuntimeException(__('Oppføringen mangler data.', 'reginor-lite')); }
        // No authors or history; the public lookup returns presentation fields only.
        return ['version' => $state['version'], 'data' => StateSchema::validate($kind, $state['data'])];
    }

    public function listing(string $kind, bool $trash = false): array
    {
        $this->requireCapability(in_array($kind, ['course', 'venue', 'room', 'level'], true) ? 'rnl_select_resources' : 'rnl_edit_others_' . $kind . 's');
        $rows = [];
        foreach (get_posts(['post_type' => ContentTypes::type($kind), 'post_status' => $trash ? 'trash' : ['draft', 'publish'],
            'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'suppress_filters' => true]) as $id) {
            $rows[$id] = in_array($kind, ['course', 'venue', 'room', 'level'], true)
                ? ['data' => $this->resource($id, $kind)] : $this->get($id, $kind, $trash);
        }
        return $rows;
    }

    public function instructors(): array
    {
        $this->requireCapability('rnl_select_resources');
        $types = array_values(array_filter((array) apply_filters('rnl_instructor_post_types', get_option('rnl_instructor_types', [])), 'post_type_exists'));
        if (!$types) { return []; }
        return array_map(static fn ($p): array => ['id' => $p->ID, 'title' => get_the_title($p)],
            get_posts(['post_type' => $types, 'post_status' => 'publish', 'posts_per_page' => -1, 'suppress_filters' => true]));
    }

    /** A replacement retains the cancelled occurrence and creates a separate, manually protected occurrence. */
    public function previewReplacement(int $id, int $version, string $cancelledId, string $date, string $reason): array
    {
        $previous = $this->get($id, 'group');
        $this->assertVersion($previous, $version);
        $data = $previous['data'];
        $matches = array_values(array_filter($data['sessions'], static fn ($s) => $s['id'] === $cancelledId && $s['status'] === 'cancelled'));
        if (!$matches || trim($reason) === '') { throw new InvalidArgumentException(__('Velg en avlyst økt og forklar erstatningskvelden.', 'reginor-lite')); }
        $active = count(array_filter($data['sessions'], static fn ($s) => $s['status'] !== 'cancelled'));
        if ($active >= $data['session_count']) { throw new InvalidArgumentException(__('Kurset har allerede avtalt antall undervisningskvelder.', 'reginor-lite')); }
        $source = $matches[0];
        $time = new ScheduledTime(LocalDateTime::at($date, $source['start_time'], $source['timezone']), LocalDateTime::at($date, $source['end_time'], $source['timezone']));
        if ($time->startsAt <= $this->clock->now()) { throw new InvalidArgumentException(__('Erstatningskvelden må ligge i fremtiden.', 'reginor-lite')); }
        $session = StoredSession::create(wp_generate_uuid4(), $time, $source['room_id'], $source['instructor_ids']);
        $session['status'] = 'moved';
        $session['reason'] = __('Erstatning for ', 'reginor-lite') . $source['date'] . ': ' . $reason;
        $data['sessions'][] = $session;
        $data = $this->validate('group', $data);
        return $this->proposal($id, $previous, $data, $this->get($data['period_id'], 'period'), [['id' => $session['id'], 'before' => null, 'after' => $session]], []);
    }

    /** Review existing manual decisions without regenerating the ordinary schedule. */
    public function previewReview(int $id, int $version): array
    {
        $previous = $this->get($id, 'group');
        $this->assertVersion($previous, $version);
        $data = $previous['data'];
        $period = $this->get($data['period_id'], 'period');
        $data['period_version'] = $period['version'];
        $issues = [];
        foreach ($data['sessions'] as $s) {
            if ($s['status'] === 'cancelled' || StoredSession::time($s)->startsAt <= $this->clock->now()) { continue; }
            foreach (array_merge($period['data']['breaks'], $data['breaks']) as $b) {
                if ($b['from'] <= $s['date'] && $s['date'] <= $b['until']) { $issues[] = __('En beholdt økt treffer et opphold. Flytt eller avlys økten først.', 'reginor-lite'); }
            }
        }
        return $this->proposal($id, $previous, $data, $period, [], $issues);
    }

    // outside/lateSales remain in signed proposals for compatibility; neither is an approval gate.
    public function previewPublication(int $id, int $version, bool $windows = false, bool $outside = false, bool $lateSales = false): array
    {
        $period = $this->get($id, 'period');
        $this->assertVersion($period, $version);
        $this->requireCapability('rnl_publish_periods');
        $this->requireCapability('rnl_publish_groups');
        $p = $this->validate('period', $period['data']);
        $errors = $warnings = $versions = $resources = [];
        $fieldErrors = [];
        if (get_post_status($id) !== 'draft') { $errors[] = __('Perioden må være kladd før publisering.', 'reginor-lite'); }
        foreach (['visible_from', 'visible_until', 'sales_from', 'sales_until'] as $field) {
            if ($p[$field] === null) { $errors[] = __('Fyll ut både synlighetsvindu og påmeldingsvindu.', 'reginor-lite'); break; }
        }
        if (!$windows) { $errors[] = __('Bekreft synlighets- og påmeldingsvinduene.', 'reginor-lite'); }
        $groups = $this->groups($id);
        if (!$groups) { $errors[] = __('Perioden må ha minst ett kurs.', 'reginor-lite'); }
        foreach ($groups as $groupId => $state) {
            $versions[$groupId] = $state['version'];
            $g = $this->validate('group', $state['data']);
            try { PlanningBounds::group($g, $p, true); }
            catch (InvalidArgumentException $error) { $errors[] = $error->getMessage(); }
            $prefix = $g['title'] . ': ';
            if (get_post_status($groupId) !== 'draft') { $errors[] = $prefix . __('kurset må være kladd.', 'reginor-lite'); }
            if ($g['period_version'] !== $period['version']) { $errors[] = $prefix . __('gjennomgå planen etter periodeendringen.', 'reginor-lite'); }
            $course = $this->resource($g['course_id'], 'course');
            $resources['course:' . $g['course_id']] = $course;
            $missing = [];
            if (!empty($g['level_id'])) { $resources['level:' . $g['level_id']] = $this->resource($g['level_id'], 'level'); }
            if (RichText::plain($course['description']) === '') { $missing['description'] = __('«Kursbeskrivelse» er tom. Beskriv hva deltakerne lærer, for eksempel grunnsteg og enkle turer i salsa.', 'reginor-lite'); }
            if (RichText::plain($course['level_description']) === '') { $missing['level_description'] = __('«Nivå og forkunnskaper» er tom. Forklar hvilken erfaring som kreves, for eksempel «Du bør ha fullført nybegynnerkurset».', 'reginor-lite'); }
            if ($g['registration_status'] === 'unknown') { $missing['registration_status'] = __('«Påmeldingsstatus» står på «Må avklares». Velg en status under Pris og påmelding, for eksempel «Påmelding tilgjengelig» eller «Åpner senere».', 'reginor-lite'); }
            if ($g['registration_status'] === 'automatic' && empty($g['letsreg_mapping'])) { $missing['registration_status'] = __('Automatisk påmeldingsstatus krever en LetsReg-kobling. Velg arrangement og priskategorier under Påmelding hos LetsReg, eller velg en manuell status.', 'reginor-lite'); }
            foreach ($missing as $field => $message) {
                $errors[] = $prefix . $message;
                $fieldErrors[] = ['group_id' => $groupId, 'field' => $field, 'message' => $prefix . $message];
            }
            if (in_array($g['registration_status'], ['automatic', 'external', 'available', 'waiting'], true) && $g['registration_url'] === '') { $errors[] = $prefix . __('påmeldingslenke mangler.', 'reginor-lite'); }
            if (!in_array($g['registration_status'], ['external', 'dropin'], true) && $g['registration_url'] !== '' && !in_array(strtolower((string) wp_parse_url($g['registration_url'], PHP_URL_HOST)),
                \RegiNor\Lite\Infrastructure\RegistrationDomains::allowed(), true)) { $errors[] = $prefix . __('påmeldingslenken må bruke et godkjent LetsReg-domene.', 'reginor-lite'); }
            if ($g['registration_url'] !== '' && wp_parse_url($g['registration_url'], PHP_URL_PORT) !== null && wp_parse_url($g['registration_url'], PHP_URL_PORT) !== 443) { $errors[] = $prefix . __('påmeldingslenken bruker feil port.', 'reginor-lite'); }
            $active = array_values(array_filter($g['sessions'], static fn ($s) => $s['status'] !== 'cancelled'));
            // Explicit cancellations without replacements are allowed; untouched, missing plans are not.
            if (!$g['sessions'] || count($active) + count(array_filter($g['sessions'], static fn ($s) => $s['status'] === 'cancelled')) < $g['session_count'] || count($active) > $g['session_count']) {
                $errors[] = $prefix . __('bekreft en komplett øktplan.', 'reginor-lite');
            }
            foreach ($active as $s) {
                if (!$s['room_id']) { $errors[] = $prefix . __('hver kurskveld trenger en sal.', 'reginor-lite'); }
                if ($s['room_id']) {
                    $room = $this->resource($s['room_id'], 'room');
                    $resources['room:' . $s['room_id']] = $room;
                    $resources['venue:' . $room['venue_id']] = $this->resource($room['venue_id'], 'venue');
                }
                foreach ($s['instructor_ids'] as $instructor) { $resources['instructor:' . $instructor] = get_the_title($instructor); }
                if ($s['date'] > ($g['latest_date'] ?? '9999-12-31')) { $errors[] = $prefix . __('en økt ligger etter absolutt siste dato.', 'reginor-lite'); }
                if (StoredSession::time($s)->startsAt > $this->clock->now()) {
                    foreach (array_merge($p['breaks'], $g['breaks']) as $b) {
                        if ($b['from'] <= $s['date'] && $s['date'] <= $b['until']) { $errors[] = $prefix . __('en økt treffer et opphold.', 'reginor-lite'); }
                    }
                }
                if (($p['visible_from'] !== null && $s['starts_at'] < $p['visible_from']) || ($p['visible_until'] !== null && $s['ends_at'] >= $p['visible_until'])) {
                    $warnings[] = $prefix . __('en økt ligger utenfor synlighetsvinduet.', 'reginor-lite');
                }
            }
            if ($active && $p['sales_until'] !== null && $p['sales_until'] > min(array_column($active, 'starts_at'))) {
                $warnings[] = $prefix . __('påmeldingen stenger etter kursstart.', 'reginor-lite');
            }
        }
        foreach ($this->conflicts() as $conflict) {
            if (isset($groups[(int) explode('/', $conflict['first'])[0]]) || isset($groups[(int) explode('/', $conflict['second'])[0]])) {
                $errors[] = __('Uavklart sal- eller instruktørkollisjon: ', 'reginor-lite') . $this->allocationLabel($conflict['first']) . ' / ' . $this->allocationLabel($conflict['second']);
            }
        }
        $proposal = ['operation' => 'publish', 'id' => $id, 'version' => $version, 'groups' => $versions,
            'resources' => $resources, 'windows' => $windows, 'outside' => $outside, 'late_sales' => $lateSales,
            'errors' => array_values(array_unique($errors)), 'field_errors' => $fieldErrors, 'warnings' => array_values(array_unique($warnings)), 'actor_id' => get_current_user_id()];
        $proposal['token'] = $this->signature($proposal);
        return $proposal;
    }

    private function allocationLabel(string $reference): string
    {
        [$groupId, $sessionId] = explode('/', $reference, 2);
        $data = $this->get((int) $groupId, 'group')['data'];
        foreach ($data['sessions'] as $session) {
            if ($session['id'] === $sessionId) { return sprintf(/* translators: 1: course title, 2: date, 3: start time. */ __('%1$s den %2$s kl. %3$s', 'reginor-lite'), $data['title'], $session['date'], $session['start_time']); }
        }
        return $data['title'];
    }

    public function publish(array $proposal): array
    {
        return Mutation::run(function () use ($proposal): array {
            $token = $proposal['token'] ?? '';
            unset($proposal['token']);
            if (!is_string($token) || !hash_equals($this->signature($proposal), $token) || ($proposal['operation'] ?? '') !== 'publish'
                || ($proposal['actor_id'] ?? 0) !== get_current_user_id()) { throw new RuntimeException(__('Ugyldig publiseringsforslag.', 'reginor-lite'), 403); }
            $fresh = $this->previewPublication($proposal['id'], $proposal['version'], $proposal['windows'], $proposal['outside'], $proposal['late_sales']);
            if (!hash_equals($token, $fresh['token'])) { throw new VersionConflict(); }
            if ($fresh['errors']) { throw new InvalidArgumentException(implode(' ', $fresh['errors'])); }
            return $this->changeLifecycle($proposal['id'], $proposal['version'], $proposal['groups'], 'publish');
        }, true);
    }

    public function lifecycle(int $id, int $version, array $groupVersions, string $target): array
    {
        if (!in_array($target, ['draft', 'trash', 'restore'], true)) { throw new InvalidArgumentException(__('Ugyldig handling.', 'reginor-lite')); }
        return Mutation::run(fn () => $this->changeLifecycle($id, $version, $groupVersions, $target), true);
    }

    private function changeLifecycle(int $id, int $version, array $groupVersions, string $target): array
    {
        $period = $this->get($id, 'period', $target === 'restore');
        $this->assertVersion($period, $version);
        $this->requireCapability('rnl_publish_periods');
        $groups = $target === 'restore' ? array_filter($this->listing('group', true), static fn ($s) => $s['data']['period_id'] === $id && ($s['event'] ?? '') === 'trashed') : $this->groups($id);
        $actual = array_map(static fn ($s) => $s['version'], $groups);
        ksort($actual); ksort($groupVersions);
        if ($actual !== $groupVersions) { throw new VersionConflict(); }
        $from = get_post_status($id);
        if (($target === 'trash' && $from !== 'draft') || ($target === 'restore' && $from !== 'trash') || ($target === 'draft' && $from !== 'publish')) {
            throw new RuntimeException(__('Periodens status er endret. Last siden på nytt.', 'reginor-lite'), 409);
        }
        if ($target === 'trash' && !current_user_can('manage_options')) {
            foreach (array_merge([$period], array_values($groups)) as $state) {
                foreach (array_merge($state['history'], [$state]) as $snapshot) {
                    if (($snapshot['event'] ?? '') === 'published') { throw new RuntimeException(__('Historiske perioder skal avpubliseres, ikke slettes av kursansvarlig.', 'reginor-lite'), 403); }
                }
            }
        }
        $event = match ($target) { 'publish' => 'published', 'trash' => 'trashed', 'restore' => 'restored', default => 'unpublished' };
        $status = $target === 'restore' ? 'draft' : $target;
        $period = $this->write($id, $period, $period['data'], $event, true);
        foreach ($groups as $groupId => $state) {
            $this->requireCapability('edit_post', $groupId);
            $this->requireCapability('rnl_publish_groups');
            $data = $state['data'];
            // Lifecycle-only changes do not invalidate an already reviewed plan.
            if ($data['period_version'] === $version) { $data['period_version'] = $period['version']; }
            $this->write($groupId, $state, $data, $event, true);
            $this->setStatus($groupId, $status);
        }
        $this->setStatus($id, $status);
        if ($status === 'publish') { CourseCalendar::published(new \RegiNor\Lite\Frontend\Catalog($this->clock), $id); }
        return $period;
    }

    private function setStatus(int $id, string $status): void
    {
        Mutation::touch($id);
        $result = wp_update_post(['ID' => $id, 'post_status' => $status], true);
        if (is_wp_error($result) || !$result) { throw new RuntimeException(__('Statusendringen feilet. Hele operasjonen er rullet tilbake.', 'reginor-lite')); }
    }

    public function groupLifecycle(int $id, int $version, int $periodVersion, bool $restore): array
    {
        return Mutation::run(function () use ($id, $version, $periodVersion, $restore): array {
            $group = $this->get($id, 'group', $restore);
            $this->assertVersion($group, $version);
            $period = $this->get($group['data']['period_id'], 'period');
            $this->assertVersion($period, $periodVersion);
            if (get_post_status($group['data']['period_id']) !== 'draft' || get_post_status($id) !== ($restore ? 'trash' : 'draft')
                || ($restore && ($group['event'] ?? '') !== 'group_trashed')) {
                throw new RuntimeException(__('Bare enkeltkurs i en kladdperiode kan flyttes til eller fra papirkurven.', 'reginor-lite'), 409);
            }
            if (!current_user_can('manage_options')) {
                foreach (array_merge($group['history'], [$group]) as $snapshot) {
                    if (($snapshot['event'] ?? '') === 'published') { throw new RuntimeException(__('Et tidligere publisert kurs skal avlyses, ikke slettes av kursansvarlig.', 'reginor-lite'), 403); }
                }
            }
            $state = $this->write($id, $group, $group['data'], $restore ? 'group_restored' : 'group_trashed', true);
            $this->setStatus($id, $restore ? 'draft' : 'trash');
            return $state;
        }, true);
    }

    public function salesStatus(int $groupId): string
    {
        $group = $this->get($groupId, 'group')['data'];
        $period = $this->get($group['period_id'], 'period')['data'];
        $visible = (new \RegiNor\Lite\Domain\Publication\PublicationService($this->clock))->isGroupPublic($this->periodModel($group['period_id']), get_post_status($groupId));
        return RegistrationState::resolve($period, $group, $this->clock->now(), $visible)['status'];
    }

    public function copyPeriod(int $id, int $version, string $title, string $startDate): int
    {
        return Mutation::run(function () use ($id, $version, $title, $startDate): int {
            $source = $this->get($id, 'period');
            $this->assertVersion($source, $version);
            $data = array_replace($source['data'], ['title' => $title, 'start_date' => $startDate, 'end_date' => null,
                'visible_from' => null, 'visible_until' => null, 'sales_from' => null, 'sales_until' => null,
                'show_as_upcoming' => false, 'cancelled' => false, 'breaks' => []]);
            $newId = $this->create('period', $data);
            foreach ($this->groups($id) as $state) {
                $g = $state['data'];
                foreach (['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping'] as $key) { unset($g[$key]); }
                $g = array_replace($g, ['start_date' => $startDate, 'first_date' => null, 'latest_date' => null, 'breaks' => [],
                    'registration_url' => '', 'registration_status' => 'unknown', 'registration_from' => null, 'registration_until' => null]);
                $groupId = $this->insert('group', $this->groupData($newId, $state['data']['course_id'], $g, null, true));
                $this->confirm($this->previewGroup($groupId, 1, []));
            }
            return $newId;
        }, true);
    }
}
