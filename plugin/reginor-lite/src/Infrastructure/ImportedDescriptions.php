<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Import provenance is private metadata, separate from translated editorial content. */
final class ImportedDescriptions
{
    public const META = '_rnl_imported_description';
    public static function normalized(array $data): array
    {
        $normalized = [];
        foreach (['title','description','dance_style','level_description','partner_info'] as $key) {
            $normalized[$key] = trim(str_replace(["\r\n", "\r"], "\n", $data[$key] ?? ''));
        }
        $normalized['audience'] = $data['audience'] ?? 'mixed';
        return $normalized;
    }
    public static function same(array $a, array $b): bool { return self::normalized($a) === self::normalized($b); }
    public static function major(array $a, array $b): bool
    {
        foreach (['dance_style', 'level_description', 'partner_info'] as $key) { if (($a[$key] ?? '') !== ($b[$key] ?? '')) { return true; } }
        // A conservative review hint, never a similarity-based merge of different events.
        $left = preg_split('/\s+/u', mb_strtolower(trim($a['description'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $right = preg_split('/\s+/u', mb_strtolower(trim($b['description'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        $union = array_unique(array_merge($left, $right));
        return ($a['title'] ?? '') !== ($b['title'] ?? '') || ($union && count(array_intersect(array_unique($left), array_unique($right))) / count($union) < 0.65);
    }
    public static function references(int $course): array
    {
        $ids = [];
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft','publish','trash'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            $state = get_post_meta($id, ContentTypes::META, true);
            if (($state['data']['course_id'] ?? 0) === $course) { $ids[] = (int) $id; }
        }
        sort($ids); return $ids;
    }
    public static function remember(int $id, array $mapping, array $data, bool $replace = false): void
    {
        $stored = get_post_meta($id, self::META, true);
        if (!is_array($stored)) { $stored = ['origins' => [], 'data' => $data]; }
        if ($replace) { $stored['data'] = $data; }
        $stored['origins'][LetsRegChanges::key($mapping)] = true;
        Mutation::touch($id);
        if (!update_post_meta($id, self::META, wp_slash($stored)) && get_post_meta($id, self::META, true) !== $stored) { throw new \RuntimeException(__('Importgrunnlaget kunne ikke lagres. Prøv igjen.', 'reginor-lite'), 503); }
    }
    public static function plan(array $mapping, array $incoming, string $choice = 'auto'): array
    {
        if (!in_array($choice, ['auto','update','separate'], true)) { throw new \InvalidArgumentException(__('Velg hvordan eksisterende beskrivelse skal behandles.', 'reginor-lite')); }
        $key = LetsRegChanges::key($mapping); $sameOrigin = []; $exact = [];
        foreach (get_posts(['post_type' => 'rnl_course', 'post_status' => ['draft','publish'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC']) as $id) {
            $meta = get_post_meta($id, self::META, true); $state = get_post_meta($id, ContentTypes::META, true);
            if (!is_array($state) || is_wp_error(rest_validate_value_from_schema($state, StateSchema::envelope('course')))) { continue; }
            if (!is_array($meta)) {
                // Older imports have no provenance. Reuse exact content, but never infer permission to overwrite it.
                $origins = [];
                foreach (self::references((int) $id) as $reference) {
                    $group = get_post_meta($reference, ContentTypes::META, true);
                    if (!empty($group['data']['letsreg_mapping'])) { $origins[LetsRegChanges::key($group['data']['letsreg_mapping'])] = true; }
                }
                if (!$origins) { continue; }
                $meta = ['origins' => $origins, 'data' => null];
            }
            // Exact cross-event reuse is allowed only within the same organizer/affiliate.
            $prefix = $mapping['affiliate_id'] . ':' . $mapping['organizer_id'] . ':';
            if (!array_filter(array_keys($meta['origins']), static fn ($origin) => str_starts_with($origin, $prefix))) { continue; }
            $candidate = ['id' => (int) $id, 'version' => $state['version'], 'before' => $state['data'], 'references' => self::references((int) $id)];
            if (self::same($state['data'], $incoming)) { $exact[] = $candidate; }
            if (isset($meta['origins'][$key])) { $sameOrigin[] = $candidate + ['local_changes' => !is_array($meta['data']) || !self::same($meta['data'], $state['data'])]; }
        }
        if ($exact) { return ['action' => 'reuse'] + $exact[0]; }
        if ($choice === 'separate' || !$sameOrigin) { return ['action' => 'create', 'id' => 0]; }
        if (count($sameOrigin) !== 1) { return ['action' => 'conflict', 'id' => 0, 'reason' => 'ambiguous']; }
        $candidate = $sameOrigin[0]; $major = self::major($candidate['before'], $incoming);
        if ($candidate['local_changes'] || ($major && $choice !== 'update')) { return ['action' => 'conflict', 'reason' => $candidate['local_changes'] ? 'local' : 'major'] + $candidate; }
        // Updating a shared description requires access to every affected local course.
        foreach ($candidate['references'] as $id) { if (!current_user_can('edit_post', $id)) { return ['action' => 'conflict', 'reason' => 'access'] + $candidate; } }
        return ['action' => 'update', 'major' => $major] + $candidate;
    }
}
