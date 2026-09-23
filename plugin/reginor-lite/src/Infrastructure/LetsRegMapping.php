<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Private course identity mapping. It never represents verified capacity or a public offer. */
final class LetsRegMapping
{
    public static function schema(): array
    {
        $object = static fn (array $properties): array => ['type' => 'object', 'properties' => $properties,
            'required' => array_keys($properties), 'additionalProperties' => false];
        $id = ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647];
        $name = ['type' => 'string', 'maxLength' => 500];
        $schema = $object(['affiliate_id' => $id, 'organizer_id' => $id, 'event_id' => $id, 'event_name' => $name,
            'checked_at' => ['type' => 'integer', 'minimum' => 1],
            'verification_id' => ['type' => 'string', 'pattern' => '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$'],
            'categories' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 200, 'items' => $object([
                'id' => $id, 'name' => $name, 'role' => ['type' => 'string', 'enum' => ['leader', 'follower', 'open']],
                'registration' => ['type' => 'string', 'enum' => ['single', 'pair']],
                'participants_per_selection' => ['type' => 'integer', 'enum' => [1]],
            ])],
        ]);
        $schema['type'] = ['object', 'null'];
        $schema['default'] = null;
        return $schema;
    }

    public static function canUse(): bool
    {
        return current_user_can('manage_options') || (current_user_can('rnl_link_letsreg') && current_user_can('rnl_edit_groups'));
    }

    public static function authorize(): void
    {
        if (!self::canUse()) { throw new \RuntimeException(__('Du har ikke tilgang til å søke etter eller koble kurs til LetsReg.', 'reginor-lite'), 403); }
    }

    public static function canImport(): bool
    {
        return current_user_can('manage_options') || (self::canUse() && current_user_can('rnl_import_letsreg') && current_user_can('rnl_create_groups'));
    }

    public static function authorizeImport(): void
    {
        if (!self::canImport()) { throw new \RuntimeException(__('Du har ikke tilgang til å importere kurs fra LetsReg.', 'reginor-lite'), 403); }
    }

    public static function source(): ?array
    {
        self::authorize();
        $state = LetsRegConnection::inspectCourse();
        if ($state['state'] !== 'verified' || !isset($state['event'], $state['verification_id'])) { return null; }
        return ['affiliate_id' => $state['affiliate_id'], 'organizer_id' => $state['organizer_id'],
            'event_id' => $state['event']['id'], 'event_name' => $state['event']['name'],
            'checked_at' => $state['last_success_at'], 'verification_id' => $state['verification_id'],
            'event' => $state['event']];
    }

    /** Choices contain only category IDs and explicitly chosen role/form; names and owner come from the checked source. */
    public static function build(int $eventId, string $verificationId, array $choices): array
    {
        self::authorize(); $source = self::source();
        if (!$source || $source['event_id'] !== $eventId || !hash_equals($source['verification_id'], $verificationId)) {
            throw new \RuntimeException(__('Arrangementskontrollen er utløpt eller endret. Velg arrangementet på nytt under «Påmelding hos LetsReg» i kursoppsettet, og kontroller kategoriene.', 'reginor-lite'), 409);
        }
        return self::fromSource($source, $choices);
    }

    /** Used only with a current source or a verified, user-bound import receipt. */
    public static function fromSource(array $source, array $choices): array
    {
        self::authorize();
        if (!$source['event']['active'] || $source['event']['isCancelled']) {
            throw new \InvalidArgumentException(__('Velg et aktivt arrangement som ikke er avlyst hos LetsReg.', 'reginor-lite'));
        }
        if (count($choices) > 200) { throw new \InvalidArgumentException(__('For mange priskategorier.', 'reginor-lite')); }
        $known = array_column($source['event']['prices'], null, 'id'); $selected = [];
        foreach ($choices as $id => $choice) {
            if (!is_int($id) || !isset($known[$id]) || !is_array($choice) || array_diff(array_keys($choice), ['role', 'registration'])
                || !in_array($choice['role'] ?? null, ['', 'leader', 'follower', 'open'], true)
                || !in_array($choice['registration'] ?? null, ['single', 'pair'], true)) {
                throw new \InvalidArgumentException(__('Velg rolle og påmeldingsform for kategorier fra det kontrollerte arrangementet.', 'reginor-lite'));
            }
            if ($choice['role'] === '') { continue; }
            if (!$known[$id]['active']) { throw new \InvalidArgumentException(__('En valgt priskategori er inaktiv hos LetsReg. Velg en aktiv kategori.', 'reginor-lite')); }
            $selected[$id] = ['id' => $id, 'name' => $known[$id]['name'], 'role' => $choice['role'],
                'registration' => $choice['registration'], 'participants_per_selection' => 1];
        }
        if (!$selected) { throw new \InvalidArgumentException(__('Ingen priskategori er valgt. Velg Fører, Følger eller Uten rollefordeling under Rolle for minst én kategori som gjelder kurset. «Ikke med på dette kurset» utelater kategorien fra koblingen.', 'reginor-lite')); }
        ksort($selected, SORT_NUMERIC);
        unset($source['event']);
        return $source + ['categories' => array_values($selected)];
    }

    /** Called again inside the course transaction; no network access. */
    public static function assertCurrent(?array $mapping): void
    {
        self::authorize();
        if ($mapping === null) { return; }
        $choices = [];
        foreach ($mapping['categories'] as $category) { $choices[$category['id']] = array_intersect_key($category, array_flip(['role', 'registration'])); }
        if (self::build($mapping['event_id'], $mapping['verification_id'], $choices) !== $mapping) {
            throw new \RuntimeException(__('LetsReg-grunnlaget er endret. Lag en ny forhåndsvisning av koblingen.', 'reginor-lite'), 409);
        }
    }

    public static function validate(?array $mapping): ?array
    {
        if ($mapping === null) { return null; }
        $ids = array_column($mapping['categories'], 'id');
        if (count($ids) !== count(array_unique($ids))) { throw new \InvalidArgumentException(__('En priskategori kan bare velges én gang per kurs.', 'reginor-lite')); }
        $mapping['event_name'] = sanitize_text_field($mapping['event_name']);
        foreach ($mapping['categories'] as &$category) { $category['name'] = sanitize_text_field($category['name']); }
        return $mapping;
    }
}
