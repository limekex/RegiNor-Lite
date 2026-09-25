<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use RegiNor\Lite\Domain\Capacity\CapacityView;
use RegiNor\Lite\Domain\Capacity\Retry;
use RegiNor\Lite\Domain\Capacity\Snapshot;
use RegiNor\Lite\Domain\Publication\Clock;
use RegiNor\Lite\Domain\Publication\SystemClock;
use RuntimeException;

/** One private atomic row per group: configuration, durable job and last good observation. */
final class CapacityStore
{
    public const META = '_rnl_capacity';
    public const HOOK = 'rnl_capacity_tick';

    public function __construct(private readonly Clock $clock = new SystemClock()) {}

    public static function boot(): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            // Keep early scheduling possible; translate the label only after init.
            $schedules['rnl_minute'] = ['interval' => 60, 'display' => did_action('init') ? __('RegiNor kapasitetskontroll', 'reginor-lite') : 'RegiNor kapasitetskontroll']; return $schedules;
        });
        add_action('init', static function (): void {
            register_post_meta('rnl_group', self::META, ['type' => 'object', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false']);
            // Resume configured demonstrations after plugin reactivation; no job for unconfigured courses.
            if (!wp_next_scheduled(self::HOOK) && get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'],
                'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => self::META, 'suppress_filters' => true])) {
                try { self::schedule(); } catch (RuntimeException) { /* Settings/manual check surfaces scheduling failures. */ }
            }
        }, 30);
        add_action(self::HOOK, static function (): void { (new self())->runDue(); });
    }

    private static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            $result = wp_schedule_event(time() + 60, 'rnl_minute', self::HOOK, [], true);
            if (is_wp_error($result) || !$result) { throw new RuntimeException(__('Automatisk kontroll kunne ikke startes. Kontakt administrator.', 'reginor-lite')); }
        }
    }

    private function authorize(int $id): void
    {
        if (!current_user_can('rnl_edit_groups') || !current_user_can('edit_post', $id)) { throw new RuntimeException(__('Du har ikke tilgang til dette kurset.', 'reginor-lite'), 403); }
        if (get_post_type($id) !== 'rnl_group' || !in_array(get_post_status($id), ['draft', 'publish'], true)) { throw new RuntimeException(__('Velg et kurs som ikke er slettet.', 'reginor-lite'), 404); }
    }

    private function read(int $id): ?array
    {
        wp_cache_delete($id, 'post_meta');
        $rows = get_post_meta($id, self::META, false);
        if (!$rows) { return null; }
        if (count($rows) !== 1 || !is_array($rows[0]) || ($rows[0]['provider'] ?? '') !== 'demo'
            || !isset(DemoCapacityAdapter::scenarios()[$rows[0]['scenario'] ?? ''])) {
            throw new RuntimeException(__('Kapasitetsoppsettet må kontrolleres av administrator.', 'reginor-lite'));
        }
        return $rows[0];
    }

    private function write(int $id, ?array $old, array $next): void
    {
        Mutation::touch($id);
        $ok = $old === null ? add_post_meta($id, self::META, wp_slash($next), true)
            : update_post_meta($id, self::META, wp_slash($next), $old);
        if (!$ok) { throw new RuntimeException(__('Kontrollen kunne ikke lagres. Prøv igjen.', 'reginor-lite'), 409); }
    }

    public function inspect(int $id): ?array
    {
        $this->authorize($id); return $this->read($id);
    }

    /** Administrator-only, optimistic version check; no live-provider switch exists. */
    public function configure(int $id, int $version, string $scenario): void
    {
        $this->authorize($id);
        if (!current_user_can('manage_options')) { throw new RuntimeException(__('Bare administrator kan endre prøveoppsettet.', 'reginor-lite'), 403); }
        if ($scenario !== 'off' && !isset(DemoCapacityAdapter::scenarios()[$scenario])) { throw new \InvalidArgumentException(__('Velg en av de viste prøvesituasjonene.', 'reginor-lite')); }
        Mutation::run(function () use ($id, $version, $scenario): void {
            $this->authorize($id); $old = $this->read($id);
            if (($old['version'] ?? 0) !== $version) { throw new RuntimeException(__('Oppsettet er endret. Last siden på nytt.', 'reginor-lite'), 409); }
            if ($scenario === 'off') {
                if ($old !== null && !delete_post_meta($id, self::META, $old)) { throw new RuntimeException(__('Prøven kunne ikke stoppes.', 'reginor-lite')); }
                Mutation::touch($id); return;
            }
            self::schedule();
            $now = $this->clock->now()->getTimestamp();
            $this->write($id, $old, ['version' => $version + 1, 'provider' => 'demo', 'scenario' => $scenario,
                'configured_at' => $now, 'configured_by' => get_current_user_id(), 'snapshot' => null,
                'last_attempt_at' => null, 'last_success_at' => null, 'error' => null, 'failures' => 0,
                'next_attempt_at' => $now, 'requested_at' => $now, 'not_before' => $now]);
        });
    }

    /** Enqueue only; manual requests cannot bypass retry backoff or perform an external call. */
    public function request(int $id): void
    {
        $this->authorize($id);
        Mutation::run(function () use ($id): void {
            $this->authorize($id); $old = $this->read($id);
            if ($old === null) { throw new RuntimeException(__('Administrator må først velge et prøveoppsett.', 'reginor-lite')); }
            $now = $this->clock->now()->getTimestamp();
            if ($now < max($old['not_before'], $old['requested_at'] + 60)) { throw new RuntimeException(__('Kontrollen er allerede bestilt, eller venter på neste tillatte forsøk. Prøv igjen senere.', 'reginor-lite'), 429); }
            self::schedule();
            $next = $old; $next['version']++; $next['requested_at'] = $now; $next['next_attempt_at'] = $now;
            $this->write($id, $old, $next);
        });
    }

    /** Demo work is bounded and has no I/O. The lock also covers saving its complete result. */
    public function runDue(?int $onlyGroup = null): int
    {
        return Mutation::run(function () use ($onlyGroup): int {
            $now = $this->clock->now()->getTimestamp(); $processed = 0;
            $ids = get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1,
                'fields' => 'ids', 'meta_key' => self::META, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true]
                + ($onlyGroup !== null ? ['post__in' => [$onlyGroup]] : []));
            foreach ($ids as $id) {
                $old = $this->read($id);
                if ($old === null || $old['next_attempt_at'] > $now) { continue; }
                $next = $old; $next['version']++; $next['last_attempt_at'] = $now;
                // Persist the attempt first: a crash cannot leave old success presented as a fresh result.
                $next['error'] = 'interrupted'; $next['not_before'] = $now + 60; $next['next_attempt_at'] = $now + 60;
                $this->write($id, $old, $next); $old = $next;
                $result = (new DemoCapacityAdapter())->fetch($old['scenario'], $now);
                $error = $result['error'] ?? null;
                if ($error === null) {
                    try {
                        $snapshot = Snapshot::accept($result['snapshot'], $old['snapshot'], $now);
                    } catch (\InvalidArgumentException) { $error = 'invalid_snapshot'; }
                }
                $next = $old; $next['version']++; $next['error'] = $error;
                if ($error === null) {
                    $next['snapshot'] = $snapshot; $next['last_success_at'] = $now; $next['failures'] = 0;
                    $next['not_before'] = $now + 60; $next['next_attempt_at'] = $now + 300;
                } else {
                    $next['failures'] = min(20, $old['failures'] + 1);
                    $next['not_before'] = $now + Retry::delay($next['failures'], random_int(0, 30), $result['retry_after'] ?? null);
                    $next['next_attempt_at'] = $next['not_before'];
                }
                $this->write($id, $old, $next);
                if (++$processed >= 25) { break; }
            }
            return $processed;
        });
    }

    /** Demo data is never eligible for public availability, even if its metadata is altered. */
    public static function publicView(int $id, int $now): array
    {
        return CapacityView::project(null, $now);
    }
}
