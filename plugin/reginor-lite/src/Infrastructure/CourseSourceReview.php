<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

trait CourseSourceReview
{
    /** Text-only editing on a published course; no lifecycle or scheduling transition. */
    public function saveCourseTexts(int $id, int $version, int $descriptionVersion, array $texts): void
    {
        $this->requireCapability('manage_options');
        Mutation::run(function () use ($id, $version, $descriptionVersion, $texts): void {
            $group = $this->get($id, 'group'); $this->assertVersion($group, $version);
            if (array_diff(array_keys($texts), ['description', 'level_description', 'dance_style', 'partner_info', 'price_terms'])) {
                throw new \InvalidArgumentException(__('Dette skjemaet lagrer bare kursets beskrivelsestekster.', 'reginor-lite'));
            }
            $courseId = $group['data']['course_id']; $course = $this->get($courseId, 'course');
            $this->assertVersion($course, $descriptionVersion);
            $terms = $texts['price_terms'] ?? null; unset($texts['price_terms']);
            $incoming = $this->validate('course', array_replace($course['data'], $texts));
            foreach (ImportedDescriptions::references($courseId) as $ref) {
                $this->requireCapability('edit_post', $ref);
                if (get_post_status($ref) === 'publish' && (RichText::plain($incoming['description']) === '' || RichText::plain($incoming['level_description']) === '')) {
                    throw new \InvalidArgumentException(__('Beskrivelsen brukes av et publisert kurs. Kursbeskrivelse og nivåforklaring kan ikke være tomme.', 'reginor-lite'));
                }
            }
            $this->write($courseId, $course, $incoming, 'saved', true);
            if ($terms !== null && $terms !== $group['data']['price_terms']) {
                $this->write($id, $group, $this->validate('group', array_replace($group['data'], ['price_terms' => $terms])), 'saved', true);
            }
            $this->refreshDescriptionCalendars($courseId);
        }, true);
    }
    /** A reviewed source updates description fields, never schedule, price or publication. */
    public function reviewLetsRegSource(int $id, int $version, int $descriptionVersion, string $hash, string $operation): void
    {
        LetsRegMapping::authorize();
        if (!in_array($operation, ['keep','description','separate','price_terms'], true)) { throw new \InvalidArgumentException(__('Velg hvordan endringen skal behandles.', 'reginor-lite')); }
        Mutation::run(function () use ($id, $version, $descriptionVersion, $hash, $operation): void {
            $group = $this->get($id, 'group'); $this->assertVersion($group, $version);
            $data = $group['data']; $source = LetsRegChanges::assertReview($id, $data, $hash);
            $description = $this->resourceState($data['course_id'], 'course');
            if ($description['version'] !== $descriptionVersion) { throw new VersionConflict(); }
            if ($operation === 'keep') { LetsRegChanges::baseline($id, $data['letsreg_mapping'], $source); return; }
            if (!isset($source['description']) || trim($source['description']) === '') { throw new \RuntimeException(__('LetsReg har ikke levert en gyldig, utfylt beskrivelse. Lokal tekst er beholdt.', 'reginor-lite'), 409); }
            $template = \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::parse($source['description']);
            if ($template['mode'] === 'invalid') { throw new \RuntimeException(__('Beskrivelsesmalen hos LetsReg er ufullstendig. Rett overskriftene hos LetsReg og hent på nytt, eller behold lokal beskrivelse. Ingen tekst er endret.', 'reginor-lite'), 409); }
            if ($operation === 'price_terms') {
                if ($template['mode'] !== 'structured') { throw new \RuntimeException(__('LetsReg-teksten mangler en fullstendig mal med Prisvilkår og tillegg. Lokal tekst er beholdt.', 'reginor-lite'), 409); }
                $data['price_terms'] = RichText::clean($template['fields']['price_terms']);
                $this->write($id, $group, $this->validate('group', $data), 'saved', true);
                $this->refreshDescriptionCalendars($data['course_id']);
                // Do not acknowledge unrelated provider text or changes.
                return;
            }
            $incoming = $this->validate('course', array_replace($description['data'], \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::courseFields($template)));
            if (!ImportedDescriptions::same($description['data'], $incoming)) {
                if ($operation === 'description') {
                    $provenance = get_post_meta($data['course_id'], ImportedDescriptions::META, true);
                    if (!current_user_can('edit_post', $data['course_id']) && (!LetsRegMapping::canImport() || empty($provenance['origins'][LetsRegChanges::key($data['letsreg_mapping'])]))) {
                        throw new \RuntimeException(__('Denne beskrivelsen er ikke registrert som importert fra arrangementet. Administrator kan kontrollere og oppdatere den.', 'reginor-lite'), 403);
                    }
                    if (is_array($provenance) && !ImportedDescriptions::same($provenance['data'], $description['data'])) {
                        throw new \RuntimeException(__('Beskrivelsen er redigert lokalt etter import. Kopier ønskede endringer inn manuelt, eller opprett en separat beskrivelse for dette kurset. Lokal tekst er beholdt.', 'reginor-lite'), 409);
                    }
                    foreach (ImportedDescriptions::references($data['course_id']) as $ref) { $this->requireCapability('edit_post', $ref); }
                    $previous = get_post_meta($data['course_id'], ContentTypes::META, true);
                    $this->write($data['course_id'], $previous, $incoming, 'saved', true);
                    ImportedDescriptions::remember($data['course_id'], $data['letsreg_mapping'], $incoming, true);
                    $this->refreshDescriptionCalendars($data['course_id']);
                } else {
                    LetsRegMapping::authorizeImport();
                    $data['course_id'] = $this->insert('course', $incoming);
                    ImportedDescriptions::remember($data['course_id'], $data['letsreg_mapping'], $incoming, true);
                    $this->write($id, $group, $this->validate('group', $data), 'saved', true);
                    $this->refreshDescriptionCalendars($data['course_id']);
                }
            }
            // Other provider changes remain pending until the user has reviewed them too.
            $review = LetsRegChanges::inspect($id, $data);
            $accepted = $review['previous'] ?? $source;
            $accepted['description'] = $source['description'];
            if (isset($source['lastUpdate'])) { $accepted['lastUpdate'] = $source['lastUpdate']; }
            LetsRegChanges::baseline($id, $data['letsreg_mapping'], $accepted);
        }, true);
    }

    private function refreshDescriptionCalendars(int $description): void
    {
        $periods = [];
        foreach (ImportedDescriptions::references($description) as $id) {
            if (get_post_status($id) !== 'publish') { continue; }
            $state = get_post_meta($id, ContentTypes::META, true); $periods[$state['data']['period_id']] = true;
        }
        foreach (array_keys($periods) as $period) { CourseCalendar::published(new \RegiNor\Lite\Frontend\Catalog($this->clock), (int) $period); }
    }
}
