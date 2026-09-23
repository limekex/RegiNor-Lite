<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Editorial source snapshots only. Financial/capacity counters never trigger content alerts. */
final class LetsRegChanges
{
    public const META = '_rnl_letsreg_reviewed';
    public static function key(array $mapping): string
    {
        return implode(':', [$mapping['affiliate_id'], $mapping['organizer_id'], $mapping['event_id']]);
    }
    public static function snapshot(array $event): array
    {
        $snapshot = array_intersect_key($event, array_flip(['name', 'description', 'event_url', 'startDate', 'endDate', 'registrationStartDate', 'registrationEndDate', 'active', 'published', 'isCancelled', 'lastUpdate', 'prices']));
        if (isset($snapshot['prices'])) { usort($snapshot['prices'], static fn ($a, $b) => $a['id'] <=> $b['id']); }
        ksort($snapshot);
        return $snapshot;
    }
    public static function hash(array $snapshot): string { return hash('sha256', wp_json_encode($snapshot)); }
    private static function cacheKey(array $identity, int $event): string { return 'rnl_content_' . hash('sha256', $identity['fingerprint'] . ':' . $event); }
    public static function capture(array $identity, int $event, array $data): void
    {
        if ($identity !== LetsRegConnection::identity()) { return; }
        set_transient(self::cacheKey($identity, $event), ['at' => time(), 'snapshot' => self::snapshot($data)], DAY_IN_SECONDS);
    }
    public static function current(array $mapping): ?array
    {
        $identity = LetsRegConnection::identity();
        if (!$identity || $identity['affiliate_id'] !== $mapping['affiliate_id'] || $identity['organizer_id'] !== $mapping['organizer_id']) { return null; }
        $data = get_transient(self::cacheKey($identity, $mapping['event_id']));
        return is_array($data) ? $data : null;
    }
    public static function failure(array $identity, int $event): void
    {
        $key = self::cacheKey($identity, $event); $value = get_transient($key);
        if (is_array($value)) { $value['failed'] = true; set_transient($key, $value, DAY_IN_SECONDS); }
    }
    public static function baseline(int $id, array $mapping, array $event): void
    {
        $value = ['key' => self::key($mapping), 'snapshot' => self::snapshot($event), 'at' => time(), 'actor' => get_current_user_id()];
        Mutation::touch($id);
        if (!update_post_meta($id, self::META, wp_slash($value)) && get_post_meta($id, self::META, true) !== $value) { throw new \RuntimeException(__('Kontrollgrunnlaget kunne ikke lagres. Prøv igjen.', 'reginor-lite'), 503); }
    }
    public static function inspect(int $id, array $group): array
    {
        $mapping = $group['letsreg_mapping'] ?? null;
        if (!$mapping) { return ['state' => 'unlinked', 'changes' => []]; }
        $baseline = get_post_meta($id, self::META, true);
        $previous = is_array($baseline) && ($baseline['key'] ?? '') === self::key($mapping) ? $baseline['snapshot'] : null;
        $latest = self::current($mapping);
        $reason = LetsRegAvailabilityStore::inspect($group, time())['reason'] ?? '';
        $fresh = $latest && empty($latest['failed']) && $latest['at'] <= time() && $latest['at'] + 900 > time()
            && !in_array($reason, ['authentication', 'permission', 'organization_mismatch'], true);
        $changes = [];
        if ($previous !== null && $latest) {
            foreach (array_unique(array_merge(array_keys($previous), array_keys($latest['snapshot']))) as $field) {
                if (($previous[$field] ?? null) !== ($latest['snapshot'][$field] ?? null)) { $changes[$field] = ['before' => $previous[$field] ?? null, 'after' => $latest['snapshot'][$field] ?? null]; }
            }
        }
        return ['state' => !$latest ? 'unchecked' : ($previous === null ? 'baseline' : ($changes ? 'changed' : 'unchanged')), 'changes' => $changes, 'latest' => $latest, 'previous' => $previous, 'fresh' => $fresh];
    }
    public static function assertReview(int $id, array $group, string $hash): array
    {
        $review = self::inspect($id, $group);
        if (empty($review['fresh']) || !hash_equals(self::hash($review['latest']['snapshot']), $hash)) {
            throw new \RuntimeException(__('LetsReg-grunnlaget er utløpt eller endret. Hent siste opplysninger og vurder forskjellene på nytt.', 'reginor-lite'), 409);
        }
        return $review['latest']['snapshot'];
    }
}
