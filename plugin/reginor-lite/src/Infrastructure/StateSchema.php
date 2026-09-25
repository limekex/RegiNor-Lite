<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use InvalidArgumentException;
use RegiNor\Lite\Domain\LocalDateTime;
use RegiNor\Lite\Domain\Scheduling\BreakPeriod;
use RegiNor\Lite\Domain\Scheduling\ScheduleRequest;
use RegiNor\Lite\Domain\Scheduling\StoredSession;

final class StateSchema
{
    private static function object(array $properties): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false];
    }

    public static function data(string $kind): array
    {
        $text = ['type' => 'string', 'maxLength' => 10000];
        $title = ['type' => 'string', 'minLength' => 1, 'maxLength' => 250];
        $id = ['type' => 'integer', 'minimum' => 1];
        $optionalId = ['type' => 'integer', 'minimum' => 0];
        $ids = ['type' => 'array', 'items' => $id, 'uniqueItems' => true, 'maxItems' => 30];
        $date = ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
        $maybeDate = ['type' => ['string', 'null'], 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
        $instant = ['type' => ['string', 'null'], 'pattern' => '^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}Z$'];
        $uuid = ['type' => 'string', 'pattern' => '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$'];
        $breaks = ['type' => 'array', 'maxItems' => 100, 'items' => self::object([
            'id' => $uuid, 'from' => $date, 'until' => $date, 'reason' => $title,
        ])];
        $price = ['type' => 'integer', 'minimum' => 0, 'maximum' => 100000000];
        $basis = ['type' => 'string', 'enum' => ['person', 'pair']];
        $common = ['title' => $title];
        $fields = match ($kind) {
            'course' => ['description' => $text, 'level_description' => $text, 'dance_style' => $title, 'partner_info' => $text],
            'venue' => ['address' => $title],
            'level' => ['description' => $text, 'sort_order' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 999],
                'active' => ['type' => 'boolean']],
            'room' => ['venue_id' => $id],
            'period' => [
                'timezone' => $title, 'start_date' => $date, 'default_session_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 104],
                'default_room_id' => $optionalId, 'default_price_minor' => $price, 'default_price_basis' => $basis,
                'visible_from' => $instant, 'visible_until' => $instant, 'sales_from' => $instant, 'sales_until' => $instant,
                'show_as_upcoming' => ['type' => 'boolean'], 'cancelled' => ['type' => 'boolean'], 'breaks' => $breaks,
            ],
            'group' => [
                'period_id' => $id, 'course_id' => $id, 'period_version' => $id,
                'timezone' => $title, 'start_date' => $date, 'first_date' => $maybeDate, 'latest_date' => $maybeDate,
                'weekday' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                'start_time' => ['type' => 'string', 'pattern' => '^(?:[01][0-9]|2[0-3]):[0-5][0-9]$'],
                'end_time' => ['type' => 'string', 'pattern' => '^(?:[01][0-9]|2[0-3]):[0-5][0-9]$'],
                'session_count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 104],
                'room_id' => $optionalId, 'instructor_ids' => $ids, 'price_minor' => $price, 'price_basis' => $basis,
                'currency' => ['type' => 'string', 'enum' => ['NOK']], 'price_terms' => $text,
                'registration_url' => $text, 'registration_scope' => ['type' => 'string', 'enum' => ['group', 'period']],
                'registration_status' => ['type' => 'string', 'enum' => ['unknown', 'automatic', 'external', 'dropin', 'later', 'available', 'full', 'waiting', 'closed', 'cancelled']],
                'breaks' => $breaks,
                'sessions' => ['type' => 'array', 'maxItems' => 520, 'items' => self::object([
                    'id' => $uuid, 'original_date' => $date, 'date' => $date, 'start_time' => $title, 'end_time' => $title,
                    'timezone' => $title, 'starts_at' => ['type' => 'string'], 'ends_at' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['scheduled', 'moved', 'cancelled']], 'reason' => $text,
                    'room_id' => $optionalId, 'instructor_ids' => $ids,
                ])],
            ],
            default => throw new InvalidArgumentException(__('Ukjent RegiNor-innholdstype.', 'reginor-lite')),
        };
        $schema = self::object($common + $fields);
        if ($kind === 'course') {
            // Optional for already-created RegiNor descriptions; no ACF data is read or changed.
            $schema['properties']['audience'] = ['type' => 'string', 'enum' => ['mixed', 'beginner', 'experienced']];
        }
        if ($kind === 'period') { $schema['properties']['calendar_enabled'] = ['type' => 'boolean', 'default' => false]; $schema['properties']['end_date'] = $maybeDate; $schema['properties']['default_view'] = ['type' => 'string', 'enum' => ['list', 'week']]; }
        if ($kind === 'venue') {
            $schema['properties']['latitude'] = ['type' => ['number', 'null'], 'minimum' => -90, 'maximum' => 90];
            $schema['properties']['longitude'] = ['type' => ['number', 'null'], 'minimum' => -180, 'maximum' => 180];
        }
        if (in_array($kind, ['group', 'room'], true)) {
            $schema['properties'] += [
                'appearance_custom' => ['type' => 'boolean', 'default' => false],
                'appearance_color' => ['type' => 'string', 'default' => '#ffffff'],
                'appearance_source' => ['type' => 'string', 'default' => ''],
                'appearance_alpha' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'default' => 100],
                'appearance_tone' => ['type' => 'string', 'enum' => ['auto', 'dark', 'light']],
            ];
        }
        if ($kind === 'group') {
            $schema['properties'] += [
                'level_id' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'allow_early_start' => ['type' => 'boolean', 'default' => false],
                'letsreg_mapping' => LetsRegMapping::schema(),
                'price_from' => ['type' => 'boolean', 'default' => false],
                'registration_from' => $instant, 'registration_until' => $instant,
                'featured' => ['type' => 'boolean', 'default' => false],
                'dropin_enabled' => ['type' => 'boolean', 'default' => false],
                'dropin_price_minor' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100000000, 'default' => null],
            ];
        }
        return $schema;
    }

    public static function envelope(string $kind): array
    {
        $snapshot = self::object([
            'version' => ['type' => 'integer', 'minimum' => 1], 'data' => self::data($kind),
            'actor_id' => ['type' => 'integer', 'minimum' => 1], 'changed_at' => ['type' => 'string'],
        ]);
        // Optional for snapshots written before sprint 3.
        $snapshot['properties']['event'] = ['type' => 'string', 'enum' => ['saved', 'published', 'unpublished', 'trashed', 'restored', 'group_trashed', 'group_restored']];
        $envelope = self::object($snapshot['properties'] + ['history' => ['type' => 'array', 'items' => $snapshot]]);
        $envelope['required'] = array_merge($snapshot['required'], ['history']);
        return $envelope + ['context' => ['edit']];
    }

    public static function validate(string $kind, array $data, bool $pendingCourse = false): array
    {
        $schema = self::data($kind);
        // Import previews can reference a not-yet-created description. Stored schemas still require a real ID.
        if ($kind === 'group' && $pendingCourse) { $schema['properties']['course_id']['minimum'] = 0; }
        $check = rest_validate_value_from_schema($data, $schema, 'rnl_state.data');
        if (is_wp_error($check)) {
            throw new InvalidArgumentException(__('Ugyldige kursdata: ', 'reginor-lite') . $check->get_error_message());
        }
        $data = rest_sanitize_value_from_schema($data, $schema);
        if (is_wp_error($data)) {
            throw new InvalidArgumentException(__('Kursdata kunne ikke normaliseres.', 'reginor-lite'));
        }
        // Only the selected editorial fields allow the restricted rich-text vocabulary.
        foreach (['title', 'description', 'level_description', 'dance_style', 'partner_info', 'address', 'price_terms'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (($kind === 'course' && in_array($field, ['description', 'level_description', 'partner_info'], true)) || ($kind === 'group' && $field === 'price_terms'))
                    ? RichText::clean($data[$field]) : sanitize_textarea_field($data[$field]);
            }
        }
        if (trim($data['title']) === '' || ($kind === 'venue' && trim($data['address']) === '')) {
            throw new InvalidArgumentException(__('Navn og eventuell adresse kan ikke være tomme.', 'reginor-lite'));
        }
        if (isset($data['timezone'])) {
            LocalDateTime::timezone($data['timezone']);
            LocalDateTime::date($data['start_date']);
        }
        $breakIds = [];
        foreach ($data['breaks'] ?? [] as $i => $break) {
            new BreakPeriod($break['from'], $break['until']);
            if (isset($breakIds[$break['id']]) || trim(sanitize_text_field($break['reason'])) === '') {
                throw new InvalidArgumentException(__('Opphold trenger unik ID og forklaring.', 'reginor-lite'));
            }
            $breakIds[$break['id']] = true;
            $data['breaks'][$i]['reason'] = sanitize_text_field($break['reason']);
        }
        if ($kind === 'period') {
            if (($data['end_date'] ?? null) !== null) {
                LocalDateTime::date($data['end_date']);
                PlanningBounds::date($data['end_date'], $data);
            }
            PlanningBounds::breaks($data['breaks'], $data);
            foreach (['visible_from', 'visible_until', 'sales_from', 'sales_until'] as $field) {
                if ($data[$field] !== null) {
                    $time = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $data[$field], new \DateTimeZone('UTC'));
                    if (!$time || $time->format('Y-m-d\TH:i:s\Z') !== $data[$field]) {
                        throw new InvalidArgumentException(__('Tidsvinduet inneholder et ugyldig tidspunkt.', 'reginor-lite'));
                    }
                }
            }
            foreach ([['visible_from', 'visible_until'], ['sales_from', 'sales_until']] as [$from, $until]) {
                if ($data[$from] !== null && $data[$until] !== null && $data[$from] >= $data[$until]) {
                    throw new InvalidArgumentException(__('Slutten av tidsvinduet må være etter starten.', 'reginor-lite'));
                }
            }
        }
        if ($kind === 'group') {
            foreach (['registration_from', 'registration_until'] as $field) {
                if (($data[$field] ?? null) === null) { continue; }
                $time = is_string($data[$field]) ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $data[$field], new \DateTimeZone('UTC')) : false;
                if (!$time || $time->format('Y-m-d\TH:i:s\Z') !== $data[$field]) { throw new InvalidArgumentException(__('Påmeldingsdatoen er ugyldig.', 'reginor-lite')); }
            }
            if (!empty($data['registration_from']) && !empty($data['registration_until']) && $data['registration_from'] >= $data['registration_until']) {
                throw new InvalidArgumentException(__('Påmeldingen må stenge etter at den åpner.', 'reginor-lite'));
            }
        }
        if ($kind === 'venue' && (($data['latitude'] ?? null) === null) !== (($data['longitude'] ?? null) === null)) { throw new InvalidArgumentException(__('Velg et punkt i kartet, eller tøm begge koordinatfeltene.', 'reginor-lite')); }
        if (in_array($kind, ['group', 'room'], true)) {
            if (isset($data['appearance_color'])) { $data['appearance_color'] = Appearance::color($data['appearance_color']); }
            if (isset($data['appearance_source'])) { $data['appearance_source'] = ColorPalette::source($data['appearance_source']); }
        }
        if ($kind === 'group') {
            if (array_key_exists('letsreg_mapping', $data)) { $data['letsreg_mapping'] = LetsRegMapping::validate($data['letsreg_mapping']); }
            if ($data['registration_status'] === 'dropin') { $data['dropin_enabled'] = true; }
            if (($data['dropin_enabled'] ?? false) && ($data['dropin_price_minor'] ?? null) === null) { throw new InvalidArgumentException(__('Oppgi drop-in-pris per person og kurskveld. Bruk 0 hvis drop-in er gratis.', 'reginor-lite')); }
            self::request($data, []);
            $data['registration_url'] = \RegiNor\Lite\Domain\RegistrationUrl::normalize($data['registration_url']);
            $ids = $dates = [];
            foreach ($data['sessions'] as $i => $session) {
                $data['sessions'][$i]['reason'] = sanitize_textarea_field($session['reason']);
                StoredSession::time($data['sessions'][$i]);
                if (isset($ids[$session['id']]) || isset($dates[$session['original_date']])) {
                    throw new InvalidArgumentException(__('Økter trenger unike ID-er og opprinnelige datoer.', 'reginor-lite'));
                }
                $ids[$session['id']] = $dates[$session['original_date']] = true;
            }
        }
        $check = rest_validate_value_from_schema($data, $schema, 'rnl_state.data');
        if (is_wp_error($check)) {
            throw new InvalidArgumentException(__('Et obligatorisk felt er tomt etter tekstkontroll.', 'reginor-lite'));
        }
        return $data;
    }

    public static function request(array $group, array $periodBreaks): ScheduleRequest
    {
        $convert = static fn (array $b): BreakPeriod => new BreakPeriod($b['from'], $b['until']);
        return new ScheduleRequest($group['start_date'], $group['weekday'], $group['start_time'], $group['end_time'],
            $group['session_count'], array_map($convert, $periodBreaks), array_map($convert, $group['breaks']),
            $group['timezone'], $group['latest_date'], $group['first_date']);
    }
}
