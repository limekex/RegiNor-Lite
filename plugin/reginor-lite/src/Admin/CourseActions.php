<?php

declare(strict_types=1);

namespace RegiNor\Lite\Admin;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RuntimeException;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\StateSchema;
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Domain\LocalDateTime;

/** One server-side command dispatcher; the form nonce is checked before any command. */
final class CourseActions
{
    public function __construct(private readonly CourseRepository $repo = new CourseRepository()) {}

    public function handle(array $input): array
    {
        if (!current_user_can('rnl_edit_periods') || !isset($input['_wpnonce']) || !is_string($input['_wpnonce'])
            || !wp_verify_nonce($input['_wpnonce'], 'rnl_course_command')) {
            throw new RuntimeException(__('Du mangler tilgang, eller skjemaet er utløpt. Last siden på nytt.', 'reginor-lite'), 403);
        }
        $action = self::scalar($input, 'command');
        $id = self::integer($input['id'] ?? '0');
        $version = self::integer($input['version'] ?? '0');
        $data = $input['data'] ?? [];
        if (!is_array($data)) { throw new InvalidArgumentException(__('Ugyldig skjema.', 'reginor-lite')); }
        switch ($action) {
            case 'save_course_description':
                if (!current_user_can('manage_options')) { throw new RuntimeException(__('Bare administrator kan endre den felles kursbeskrivelsen.', 'reginor-lite'), 403); }
                return Mutation::run(function () use ($id, $version, $input, $data): array {
                    $group = $this->repo->get($id, 'group');
                    if ($group['version'] !== $version) { throw new \RegiNor\Lite\Infrastructure\VersionConflict(); }
                    $courseId = $group['data']['course_id'];
                    $course = $this->repo->get($courseId, 'course');
                    if (array_diff(array_keys($data), ['description', 'level_description', 'dance_style', 'partner_info', 'price_terms'])) { throw new InvalidArgumentException(__('Dette skjemaet lagrer bare kursets beskrivelsestekster.', 'reginor-lite')); }
                    $texts = ['description' => self::scalar($data, 'description'), 'level_description' => self::scalar($data, 'level_description')];
                    foreach (['dance_style', 'partner_info', 'price_terms'] as $field) { if (array_key_exists($field, $data)) { $texts[$field] = self::scalar($data, $field); } }
                    $this->repo->saveCourseTexts($id, $version, self::integer($input['course_version'] ?? ''), $texts);
                    return ['id' => $group['data']['period_id'], 'group' => $id, 'message' => __('Beskrivelsestekstene er lagret.', 'reginor-lite')];
                }, true);
            case 'save':
                $kind = self::scalar($input, 'kind');
                if (!in_array($kind, ['period', 'course', 'venue', 'room', 'level'], true)) { throw new InvalidArgumentException(__('Ugyldig innholdstype.', 'reginor-lite')); }
                $previous = $id ? $this->repo->get($id, $kind) : null;
                $parsed = self::parse($kind, $data, $previous['data'] ?? []);
                if ($id) { $this->repo->update($id, $version, $parsed); }
                else { $id = $this->repo->create($kind, $parsed); }
                return ['id' => $kind === 'period' ? $id : 0, 'message' => __('Oppsettet er lagret.', 'reginor-lite')];
            case 'add_group':
                $period = $this->repo->get($id, 'period');
                if ($period['version'] !== $version) { throw new \RegiNor\Lite\Infrastructure\VersionConflict(); }
                // Creation and the parent version check share the same writer lock.
                return Mutation::run(function () use ($id, $version, $input, $data): array {
                    if ($this->repo->get($id, 'period')['version'] !== $version) { throw new \RegiNor\Lite\Infrastructure\VersionConflict(); }
                    $group = $this->repo->createGroup($id, self::integer($input['course_id'] ?? ''), [
                        'weekday' => self::integer($data['weekday'] ?? ''), 'start_time' => self::scalar($data, 'start_time'), 'end_time' => self::scalar($data, 'end_time')]);
                    return ['id' => $id, 'group' => $group, 'message' => __('Kurset er opprettet. Fyll ut oppsettet og forhåndsvis datoene.', 'reginor-lite')];
                });
            case 'preview_group':
                $state = $this->repo->get($id, 'group');
                if (!array_key_exists('start_date', $data)) {
                    $data['start_date'] = $this->repo->get($state['data']['period_id'], 'period')['data']['start_date'];
                }
                $parsed = self::parse('group', $data, $state['data']);
                foreach (['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping'] as $key) { unset($parsed[$key]); }
                return ['id' => $state['data']['period_id'], 'group' => $id, 'proposal' => $this->repo->previewGroup($id, $version, $parsed)];
            case 'preview_letsreg':
                \RegiNor\Lite\Infrastructure\LetsRegMapping::authorize();
                $state = $this->repo->get($id, 'group');
                $mode = self::scalar($input, 'mapping_action');
                if (!in_array($mode, ['link', 'remove'], true)) { throw new InvalidArgumentException(__('Ukjent koblingshandling.', 'reginor-lite')); }
                $mapping = null;
                if ($mode === 'link') {
                    if (!is_array($data['categories'] ?? null)) { throw new InvalidArgumentException(__('Velg priskategorier for kurset.', 'reginor-lite')); }
                    $mapping = \RegiNor\Lite\Infrastructure\LetsRegMapping::build(self::integer($input['event_id'] ?? ''), self::scalar($input, 'verification_id'), $data['categories']);
                }
                return ['id' => $state['data']['period_id'], 'group' => $id, 'proposal' => $this->repo->previewLetsRegMapping($id, $version, $mapping)];
            case 'session':
                $group = $this->repo->get($id, 'group');
                $changes = ['date' => self::scalar($data, 'date'), 'start_time' => self::scalar($data, 'start_time'),
                    'end_time' => self::scalar($data, 'end_time'), 'status' => self::scalar($data, 'status'), 'reason' => self::scalar($data, 'reason'),
                    'room_id' => self::integer($data['room_id'] ?? ''), 'instructor_ids' => self::ids($data['instructor_ids'] ?? [])];
                return ['id' => $group['data']['period_id'], 'group' => $id, 'proposal' => $this->repo->previewSessionChange($id, $version, self::scalar($input, 'session_id'), $changes)];
            case 'replacement':
                $group = $this->repo->get($id, 'group');
                return ['id' => $group['data']['period_id'], 'group' => $id, 'proposal' => $this->repo->previewReplacement($id, $version, self::scalar($input, 'session_id'), self::scalar($data, 'date'), self::scalar($data, 'reason'))];
            case 'review':
                $group = $this->repo->get($id, 'group');
                return ['id' => $group['data']['period_id'], 'group' => $id, 'proposal' => $this->repo->previewReview($id, $version)];
            case 'confirm':
                $proposal = self::decode(self::scalar($input, 'proposal'));
                $result = $this->repo->confirm($proposal);
                return ['id' => $result['state']['data']['period_id'], 'group' => $proposal['id'], 'message' => __('Endringen er bekreftet og lagret.', 'reginor-lite')];
            case 'preview_publication':
                return ['id' => $id, 'publication' => $this->repo->previewPublication($id, $version, isset($input['windows']), isset($input['outside']), isset($input['late_sales']))];
            case 'publish':
                $proposal = self::decode(self::scalar($input, 'proposal'));
                $this->repo->publish($proposal);
                return ['id' => $proposal['id'], 'message' => __('Perioden er publisert. Kursene vises på den valgte kurssiden innenfor synlighetsvinduet.', 'reginor-lite')];
            case 'lifecycle':
                $target = self::scalar($input, 'target');
                $this->repo->lifecycle($id, $version, self::decode(self::scalar($input, 'groups')), $target);
                return ['id' => $target === 'trash' ? 0 : $id, 'message' => __('Periodens status er endret.', 'reginor-lite')];
            case 'group_lifecycle':
                $target = self::scalar($input, 'target');
                if (!in_array($target, ['trash', 'restore'], true)) { throw new InvalidArgumentException(__('Ugyldig kursstatus.', 'reginor-lite')); }
                $state = $this->repo->groupLifecycle($id, $version, self::integer($input['period_version'] ?? ''), $target === 'restore');
                return ['id' => $state['data']['period_id'], 'message' => __('Kursets status er endret.', 'reginor-lite')];
            case 'copy':
                $newId = $this->repo->copyPeriod($id, $version, self::scalar($data, 'title'), self::scalar($data, 'start_date'));
                return ['id' => $newId, 'message' => __('Ny kladd er opprettet. Fyll ut og bekreft nye vinduer og påmeldingslenker.', 'reginor-lite')];
            case 'instructor_types':
                if (!current_user_can('manage_options')) { throw new RuntimeException(__('Bare administrator kan endre profiloppsettet.', 'reginor-lite'), 403); }
                $types = $input['types'] ?? [];
                if (!is_array($types)) { throw new InvalidArgumentException(__('Ugyldige profiltyper.', 'reginor-lite')); }
                foreach ($types as $type) {
                    if (!is_string($type) || !isset(get_post_types(['public' => true])[$type]) || str_starts_with($type, 'rnl_')) { throw new InvalidArgumentException(__('Velg en eksisterende offentlig profiltype.', 'reginor-lite')); }
                }
                Mutation::run(static fn () => update_option('rnl_instructor_types', array_values(array_unique($types)), false));
                return ['id' => 0, 'message' => __('Tillatte instruktørtyper er lagret.', 'reginor-lite')];
            default: throw new InvalidArgumentException(__('Ukjent handling.', 'reginor-lite'));
        }
    }

    public static function scalar(array $data, string $key): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) { throw new InvalidArgumentException(__('Et skjemafelt mangler eller har feil format: ', 'reginor-lite') . $key); }
        return trim($data[$key]);
    }

    public static function integer(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) { throw new InvalidArgumentException(__('Forventet heltall.', 'reginor-lite')); }
        if (!preg_match('/^\d{1,10}$/D', (string) $value)) { throw new InvalidArgumentException(__('Forventet et positivt heltall.', 'reginor-lite')); }
        return (int) $value;
    }

    public static function ids(mixed $values): array
    {
        if (!is_array($values)) { throw new InvalidArgumentException(__('Ugyldig utvalg.', 'reginor-lite')); }
        return array_map(self::integer(...), array_values($values));
    }

    public static function decode(string $encoded): array
    {
        $json = base64_decode($encoded, true);
        if ($json === false || strlen($json) > 2 * 1024 * 1024) { throw new InvalidArgumentException(__('Ugyldig forhåndsvisning.', 'reginor-lite')); }
        $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) { throw new InvalidArgumentException(__('Ugyldig forhåndsvisning.', 'reginor-lite')); }
        return $value;
    }

    public static function parse(string $kind, array $raw, array $previous, bool $pendingCourse = false): array
    {
        $properties = StateSchema::data($kind)['properties'];
        $protected = $kind === 'group' ? ['period_id', 'course_id', 'period_version', 'sessions', 'letsreg_mapping'] : [];
        if (array_diff(array_keys($raw), array_diff(array_keys($properties), $protected))) { throw new InvalidArgumentException(__('Skjemaet inneholder felt som ikke kan redigeres.', 'reginor-lite')); }
        $data = [];
        foreach ($properties as $key => $schema) {
            if (!array_key_exists($key, $raw) && !in_array($key, StateSchema::data($kind)['required'], true)) { $data[$key] = $previous[$key] ?? ($schema['default'] ?? $schema['enum'][0] ?? null); continue; }
            if (in_array($key, $protected, true)) { $data[$key] = $previous[$key]; continue; }
            if ($key === 'breaks') {
                $data[$key] = [];
                if (!is_array($raw[$key] ?? [])) { throw new InvalidArgumentException(__('Ugyldige opphold.', 'reginor-lite')); }
                $known = array_column($previous['breaks'] ?? [], 'id');
                foreach ($raw[$key] ?? [] as $break) {
                    if (!is_array($break)) { throw new InvalidArgumentException(__('Ugyldig opphold.', 'reginor-lite')); }
                    if (isset($break['remove'])) { continue; }
                    $from = self::scalar($break, 'from'); $until = self::scalar($break, 'until'); $reason = self::scalar($break, 'reason');
                    if ($from === '' && $until === '' && $reason === '') { continue; }
                    $uuid = self::scalar($break, 'id');
                    if ($uuid !== '' && !in_array($uuid, $known, true)) { throw new InvalidArgumentException(__('Oppholdet tilhører ikke dette objektet.', 'reginor-lite')); }
                    $data[$key][] = ['id' => $uuid ?: wp_generate_uuid4(), 'from' => $from, 'until' => $until ?: $from, 'reason' => $reason];
                }
            } elseif (in_array($key, ['latitude', 'longitude'], true)) {
                $value = str_replace(',', '.', self::scalar($raw, $key));
                if ($value !== '' && !preg_match('/^-?\d{1,3}(?:\.\d{1,10})?$/D', $value)) { throw new InvalidArgumentException(__('Oppgi en gyldig koordinat, for eksempel 59.913900.', 'reginor-lite')); }
                $data[$key] = $value === '' ? null : (float) $value;
            } elseif ($key === 'instructor_ids') { $data[$key] = self::ids($raw[$key] ?? []);
            } elseif (in_array($key, ['appearance_custom', 'featured', 'dropin_enabled', 'calendar_enabled', 'price_from'], true)) { $data[$key] = in_array($raw[$key] ?? false, [true, 1, '1'], true);
            } elseif (($schema['type'] ?? '') === 'boolean') { $data[$key] = isset($raw[$key]);
            } elseif (str_ends_with($key, 'price_minor')) {
                $price = str_replace(',', '.', self::scalar($raw, $key));
                if ($key === 'dropin_price_minor' && $price === '') { $data[$key] = null; continue; }
                if (!preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/D', $price, $parts)) { throw new InvalidArgumentException(__('Pris må være kroner med høyst to desimaler.', 'reginor-lite')); }
                $data[$key] = (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
            } elseif (($schema['type'] ?? '') === 'integer') { $data[$key] = self::integer($raw[$key] ?? '');
            } else {
                $value = self::scalar($raw, $key);
                if (in_array($key, ['visible_from', 'visible_until', 'sales_from', 'sales_until', 'registration_from', 'registration_until'], true)) {
                    if ($value === '') { $data[$key] = null; continue; }
                    if (!preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})$/D', $value, $parts)) { throw new InvalidArgumentException(__('Ugyldig lokalt tidspunkt.', 'reginor-lite')); }
                    $data[$key] = LocalDateTime::at($parts[1], $parts[2], self::scalar($raw, 'timezone'))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
                } else { $data[$key] = $value === '' && is_array($schema['type']) && in_array('null', $schema['type'], true) ? null : $value; }
            }
        }
        return StateSchema::validate($kind, $data, $pendingCourse);
    }
}
