<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Domain\Scheduling\ScheduleGenerator;
use RegiNor\Lite\Domain\Scheduling\StoredSession;
use function RegiNor\Lite\translate as __;

/** One checked event becomes one local draft. Preview has no writes; confirmation is transactional. */
trait CourseImport
{
    public function newGroupDefaults(int $periodId): array
    {
        $this->requireCapability(get_post_type_object(ContentTypes::type('group'))->cap->create_posts);
        $period = $this->get($periodId, 'period');
        if (get_post_status($periodId) !== 'draft') { throw new \RuntimeException(__('Ta perioden tilbake til kladd før du legger til kurs.', 'reginor-lite'), 409); }
        $p = $period['data'];
        return ['title' => '', 'period_id' => $periodId, 'course_id' => 0, 'period_version' => $period['version'],
            'timezone' => $p['timezone'], 'start_date' => $p['start_date'], 'first_date' => null, 'latest_date' => null,
            'session_count' => $p['default_session_count'],
            'room_id' => $p['default_room_id'], 'instructor_ids' => [], 'price_minor' => $p['default_price_minor'],
            'price_basis' => $p['default_price_basis'], 'currency' => 'NOK', 'price_terms' => '',
            'registration_url' => '', 'registration_scope' => 'group', 'registration_status' => 'unknown', 'breaks' => [], 'sessions' => []];
    }

    public function previewImport(int $periodId, int $version, int $courseId, array $fields, array $mapping, ?array $newCourse = null, string $receipt = '', string $descriptionChoice = 'auto'): array
    {
        LetsRegMapping::authorizeImport();
        $period = $this->get($periodId, 'period'); $this->assertVersion($period, $version);
        if ($receipt !== '') { LetsRegImportSource::assertMapping($receipt, $mapping); }
        else { LetsRegMapping::assertCurrent($mapping); }
        if ($newCourse !== null) {
            foreach (['title' => __('Navn på kursbeskrivelsen mangler. Skriv et navn, for eksempel Salsa nybegynner.', 'reginor-lite'),
                'dance_style' => __('Dansestil mangler. LetsReg oppgir ikke denne som et eget felt. Skriv for eksempel Salsa eller Bachata.', 'reginor-lite')] as $key => $message) {
                if (!isset($newCourse[$key]) || is_string($newCourse[$key]) && sanitize_text_field($newCourse[$key]) === '') {
                    throw new \InvalidArgumentException($message);
                }
            }
            $newCourse = $this->validate('course', $newCourse);
        }
        $data = $this->groupData($periodId, $courseId, $fields, $newCourse);
        if (!$data['room_id']) { throw new \InvalidArgumentException(__('Sal mangler. LetsReg bestemmer ikke lokal sal. Velg salen der kurset skal holdes under Undervisning.', 'reginor-lite')); }
        $data['letsreg_mapping'] = $mapping;
        $this->assertNoImportDuplicate($periodId, $mapping);
        $planning = $data;
        if (($period['data']['end_date'] ?? null) !== null) { $planning['latest_date'] = min($planning['latest_date'] ?? $period['data']['end_date'], $period['data']['end_date']); }
        // Import records a complete new series, including past dates. It does not replan saved history.
        $data['sessions'] = [];
        $changes = [];
        $started = 0;
        $now = $this->clock->now();
        foreach ((new ScheduleGenerator())->preview(StateSchema::request($planning, $period['data']['breaks'])) as $time) {
            $session = StoredSession::create(wp_generate_uuid4(), $time, $data['room_id'], $data['instructor_ids']);
            $data['sessions'][] = $session;
            $changes[] = ['id' => $session['id'], 'before' => null, 'after' => $session];
            if ($time->startsAt <= $now) { ++$started; }
        }
        $proposal = $this->proposal(0, ['version' => 0], $data, $period, $changes, []);
        unset($proposal['token']);
        if ($started > 0) {
            $proposal['warnings'][] = sprintf(
                /* translators: 1: total sessions, 2: sessions whose start time has passed, 3: future sessions, 4: time of check in course timezone. */
                __('Kursrekken har allerede startet. Av totalt %1$d undervisningskvelder har %2$d passert starttidspunktet og %3$d starter senere, regnet per %4$s. Hele planen importeres fra første kursdato, også de tidligere datoene. Dette hindrer ikke import og bekrefter ikke oppmøte eller gjennomføring.', 'reginor-lite'),
                count($data['sessions']), $started, count($data['sessions']) - $started,
                $now->setTimezone(new \DateTimeZone($data['timezone']))->format('d.m.Y H:i') . ' (' . $data['timezone'] . ')'
            );
        }
        $proposal['operation'] = 'import';
        $proposal['expires_at'] = time() + 900;
        $proposal['resources'] = $this->importResources($data);
        $proposal['new_course'] = $newCourse;
        $proposal['description_choice'] = $descriptionChoice;
        $proposal['description_plan'] = $newCourse !== null ? ImportedDescriptions::plan($mapping, $newCourse, $descriptionChoice) : null;
        $proposal['source_event'] = ($receipt !== '' ? LetsRegImportSource::read($receipt) : LetsRegMapping::source())['event'];
        if (($proposal['description_plan']['action'] ?? '') === 'conflict') {
            $proposal['issues'][] = match ($proposal['description_plan']['reason']) {
                'local' => __('Beskrivelsen er redigert lokalt etter import, eller mangler et sikkert tidligere importgrunnlag. Automatisk overskriving er stoppet for å bevare lokal tekst. Velg «Bruk eksisterende lokal beskrivelse», eller «Opprett separat hvis teksten er forskjellig». Ønskede tekstendringer kan flettes manuelt av administrator.', 'reginor-lite'),
                'ambiguous' => __('Flere forskjellige lokale beskrivelser er knyttet til dette LetsReg-arrangementet. Vi kan ikke velge hvilken som skal erstattes. Velg konkret beskrivelse under «Bruk eksisterende lokal beskrivelse», eller opprett en separat beskrivelse.', 'reginor-lite'),
                'access' => __('Beskrivelsen deles med kurs du ikke har rettighet til å endre. Be administrator vurdere oppdateringen, eller opprett en separat beskrivelse for det nye kurset.', 'reginor-lite'),
                default => __('Navn, nivå, dansestil, partnerinformasjon eller en stor del av beskrivelsesteksten avviker fra sist import. Sammenlign feltene nedenfor. Velg «Oppdater eksisterende også ved større avvik» eller «Opprett separat hvis teksten er forskjellig», og forhåndsvis på nytt.', 'reginor-lite'),
            };
        }
        $proposal['receipt'] = $receipt;
        $proposal['token'] = $this->signature($proposal);
        return $proposal;
    }

    public function confirmImport(array $proposal): int
    {
        LetsRegMapping::authorizeImport();
        $token = $proposal['token'] ?? ''; unset($proposal['token']);
        if (!is_string($token) || !hash_equals($this->signature($proposal), $token) || ($proposal['operation'] ?? '') !== 'import'
            || ($proposal['actor_id'] ?? 0) !== get_current_user_id()) { throw new \RuntimeException(__('Forhåndsvisningen er ugyldig. Lag en ny forhåndsvisning.', 'reginor-lite'), 403); }
        return Mutation::run(function () use ($proposal): int {
            $this->requireCapability(get_post_type_object(ContentTypes::type('group'))->cap->create_posts);
            if ($proposal['expires_at'] <= time()) { throw new \RuntimeException(__('Forhåndsvisningen er utløpt. Kontroller kursutkastet på nytt.', 'reginor-lite'), 409); }
            $data = $proposal['data']; $period = $this->get($data['period_id'], 'period');
            $this->assertVersion($period, $proposal['period_version']);
            if (get_post_status($data['period_id']) !== 'draft') { throw new \RuntimeException(__('Perioden må være en kladd for å legge til nye kurs.', 'reginor-lite'), 409); }
            if ($proposal['issues']) { throw new \InvalidArgumentException(__('Rett punktene i forhåndsvisningen før du oppretter utkastet.', 'reginor-lite')); }
            if (!empty($proposal['receipt'])) { LetsRegImportSource::assertMapping($proposal['receipt'], $data['letsreg_mapping']); }
            else { LetsRegMapping::assertCurrent($data['letsreg_mapping']); }
            $this->assertNoImportDuplicate($data['period_id'], $data['letsreg_mapping']);
            if ($this->importResources($data) !== $proposal['resources']) { throw new VersionConflict(); }
            if (isset($proposal['new_course'])) {
                $incoming = $this->validate('course', $proposal['new_course']);
                $plan = ImportedDescriptions::plan($data['letsreg_mapping'], $incoming, $proposal['description_choice'] ?? 'auto');
                $reviewed = $proposal['description_plan'] ?? null;
                // Within a batch an identical description may have just been created by another item.
                if ($plan !== $reviewed && !(($reviewed['action'] ?? '') === 'create' && $plan['action'] === 'reuse')) { throw new VersionConflict(); }
                if ($plan['action'] === 'conflict') { throw new VersionConflict(); }
                $data['course_id'] = $plan['action'] === 'create' ? $this->insert('course', $incoming) : $plan['id'];
                if ($plan['action'] === 'update') {
                    $previousDescription = get_post_meta($plan['id'], ContentTypes::META, true);
                    $this->write($plan['id'], $previousDescription, $incoming, 'saved', true);
                    $this->refreshDescriptionCalendars($plan['id']);
                }
                ImportedDescriptions::remember($data['course_id'], $data['letsreg_mapping'], $incoming, in_array($plan['action'], ['create','update'], true));
            }
            $data = $this->validate('group', $data);
            PlanningBounds::group($data, $period['data'], true);
            // Earlier dates in a new import are intentional; existing sessions are never rewritten here.
            // Resource conflicts remain draft work and are checked afresh by the existing publication gate.
            $id = $this->insert('group', $data);
            LetsRegChanges::baseline($id, $data['letsreg_mapping'], $proposal['source_event']);
            return $id;
        }, true);
    }

    private function importResources(array $data): array
    {
        $room = $this->resourceState($data['room_id'], 'room');
        $resources = [$data['room_id'] => $room['version'], $room['data']['venue_id'] => $this->resourceState($room['data']['venue_id'], 'venue')['version']];
        if ($data['course_id']) { $resources[$data['course_id']] = $this->resourceState($data['course_id'], 'course')['version']; }
        return $resources;
    }

    /** Exact reviewed proposals, one period, one transaction. Any failure rolls back every new object. */
    public function confirmImportBatch(int $periodId, array $proposals): array
    {
        LetsRegMapping::authorizeImport();
        if (!array_is_list($proposals) || !$proposals || count($proposals) > 20) {
            throw new \InvalidArgumentException(__('Velg mellom 1 og 20 kurs per import.', 'reginor-lite'));
        }
        return Mutation::run(function () use ($periodId, $proposals): array {
            $ids = [];
            foreach ($proposals as $proposal) {
                if (!is_array($proposal) || ($proposal['data']['period_id'] ?? 0) !== $periodId || empty($proposal['receipt'])) {
                    throw new \RuntimeException(__('Alle kurs må ha et kontrollert importgrunnlag og tilhøre valgt periode.', 'reginor-lite'), 403);
                }
                try { $ids[] = $this->confirmImport($proposal); }
                catch (\RuntimeException | \InvalidArgumentException $error) {
                    $title = is_string($proposal['data']['title'] ?? null) ? sanitize_text_field($proposal['data']['title']) : __('Ukjent kurs', 'reginor-lite');
                    throw new \RuntimeException(sprintf(/* translators: 1: course title; 2: reason creation failed. */ __('«%1$s»: %2$s Ingen kurs i importlisten er lagret.', 'reginor-lite'), $title, $error->getMessage()), $error->getCode());
                }
            }
            return $ids;
        }, true);
    }

    private function assertNoImportDuplicate(int $periodId, array $mapping): void
    {
        // Include the trash: restoring a previously imported course must not create a hidden duplicate.
        foreach ($this->groups($periodId) + $this->listing('group', true) as $row) {
            $data = $row['data']; $existing = $data['letsreg_mapping'] ?? null;
            if ($data['period_id'] !== $periodId || !$existing) { continue; }
            if ($existing['affiliate_id'] === $mapping['affiliate_id'] && $existing['organizer_id'] === $mapping['organizer_id']
                && $existing['event_id'] === $mapping['event_id'] && array_intersect(array_column($existing['categories'], 'id'), array_column($mapping['categories'], 'id'))) {
                throw new \RuntimeException(sprintf(/* translators: %s: existing local course name. */ __('Disse LetsReg-kategoriene er allerede koblet til «%s» i perioden. Åpne eller gjenopprett det kurset i stedet.', 'reginor-lite'), $data['title']), 409);
            }
        }
    }
}
