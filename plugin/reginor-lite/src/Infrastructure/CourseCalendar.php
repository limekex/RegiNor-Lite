<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\PublicSite;
use RegiNor\Lite\Domain\Calendar\ICalendar;
use function RegiNor\Lite\translate as __;

/** Minimal public calendar history, separate from course versions and private audit data. */
final class CourseCalendar
{
    public const META = '_rnl_calendar';
    public const ID = '_rnl_calendar_id';

    public static function language(string $language): bool
    {
        return $language === 'default' || isset(WebIdentity::languages()[$language]);
    }

    public static function inLanguage(string $language, callable $read): mixed
    {
        $old = apply_filters('wpml_current_language', null);
        $target = $language === 'default' ? apply_filters('wpml_default_language', $old) : $language;
        $locale = $language === 'default' ? get_option('WPLANG', '') : (WebIdentity::languages()[$language]['default_locale'] ?? '');
        $switched = $locale && switch_to_locale($locale);
        do_action('wpml_switch_language', $target);
        try { return $read(); } finally { do_action('wpml_switch_language', $old); if ($switched) { restore_previous_locale(); } }
    }

    /** Called inside the publication transaction, after all course statuses are final. */
    public static function published(Catalog $catalog, int $periodId): void
    {
        foreach (['default', ...array_keys(WebIdentity::languages())] as $language) {
            self::inLanguage($language, static function () use ($catalog, $language, $periodId): void {
                foreach ($catalog->read()['groups'] as $group) { if ($group['period_id'] === $periodId) { self::save($group, $language); } }
            });
        }
    }

    private static function identity(int $id): string
    {
        $identity = get_post_meta($id, self::ID, true);
        if (is_string($identity) && preg_match('/^[a-f0-9-]{36}$/D', $identity)) { return $identity; }
        do {
            $identity = wp_generate_uuid4();
            $exists = get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish', 'private', 'pending', 'trash'], 'fields' => 'ids', 'posts_per_page' => 1,
                'meta_key' => self::ID, 'meta_value' => $identity, 'suppress_filters' => true]);
        } while ($exists);
        Mutation::touch($id);
        if (!add_post_meta($id, self::ID, $identity, true)) { throw new \RuntimeException(__('Kalenderadressen kunne ikke lagres. Prøv igjen.', 'reginor-lite')); }
        return $identity;
    }

    private static function save(array $group, string $language): array
    {
        if (!Mutation::active()) { throw new \LogicException('Calendar writes require the course lock.'); }
        $id = (int) $group['id']; $identity = self::identity($id);
        $old = get_post_meta($id, self::META, true); $state = is_array($old) ? $old : [];
        $previous = $state[$language] ?? $state['default'] ?? [];
        $url = PublicSite::url($id);
        $events = []; $now = gmdate(DATE_ATOM);
        foreach ($group['sessions'] as $session) {
            $cancelled = $session['status'] === 'cancelled' || $group['status'] === 'cancelled';
            $description = $cancelled ? __('Avlyst kurskveld.', 'reginor-lite') : __('Se kursprofilen for oppdaterte kursopplysninger.', 'reginor-lite');
            if ($session['status'] === 'moved') { $description .= ' ' . __('Endret kurskveld.', 'reginor-lite'); }
            if ($session['reason']) { $description .= ' ' . $session['reason']; }
            $event = ['title' => $group['title'], 'start' => $session['starts_at'], 'end' => $session['ends_at'], 'cancelled' => $cancelled,
                'location' => implode(' · ', array_filter([$session['room'], $session['venue'], $session['address']])), 'description' => $description, 'url' => $url];
            $before = $previous[$session['id']] ?? null;
            $events[$session['id']] = self::revision($event, $before, $now);
        }
        // Keep removed, previously public sessions as cancellations, never expose new draft dates.
        foreach ($previous as $sessionId => $event) {
            if (isset($events[$sessionId])) { continue; }
            $next = array_diff_key($event, array_flip(['sequence', 'modified']));
            $next['cancelled'] = true; $next['url'] = $url; $next['title'] = $group['title'];
            $next['description'] = __('Denne kurskvelden er tatt ut av kursplanen og er avlyst.', 'reginor-lite');
            $events[$sessionId] = self::revision($next, $event, $now);
        }
        ksort($events);
        uasort($events, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));
        $state[$language] = $events;
        if (strlen(serialize($state)) > 2 * 1024 * 1024) { throw new \RuntimeException(__('Kalenderhistorikken har nådd lagringsgrensen. Kontakt administrator.', 'reginor-lite')); }
        if ($state !== $old) {
            Mutation::touch($id);
            if (!update_post_meta($id, self::META, wp_slash($state))) { throw new \RuntimeException(__('Kalenderoppdateringen kunne ikke lagres. Prøv igjen.', 'reginor-lite')); }
        }
        return ['identity' => $identity, 'events' => $events];
    }

    private static function revision(array $event, ?array $before, string $now): array
    {
        if ($before && array_diff_key($before, array_flip(['sequence', 'modified'])) === $event) { return $before; }
        return $event + ['sequence' => $before ? $before['sequence'] + 1 : 0, 'modified' => $now];
    }

    /** Anonymous read rechecks publication inside the same lock as snapshot generation. */
    public static function read(int $id, string $language): ?array
    {
        if (!self::language($language)) { return null; }
        return Mutation::run(static fn () => self::inLanguage($language, static function () use ($id, $language): ?array {
            if (!PublicSite::pageId()) { return null; }
            $catalog = (new Catalog())->read(); $group = $catalog['groups'][$id] ?? null;
            if (!$group) { return null; }
            $snapshot = self::save($group, $language);
            $name = $group['title'] . ' · ' . $catalog['periods'][$group['period_id']]['title'];
            return $snapshot + ['name' => $name, 'ics' => ICalendar::render($name, $snapshot['identity'], $snapshot['events'])];
        }), true);
    }

    public static function find(string $identity): ?int
    {
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $identity)) { return null; }
        $ids = get_posts(['post_type' => 'rnl_group', 'post_status' => 'publish', 'posts_per_page' => 2, 'fields' => 'ids',
            'meta_key' => self::ID, 'meta_value' => $identity, 'suppress_filters' => true]);
        return count($ids) === 1 ? (int) $ids[0] : null;
    }

    public static function url(string $identity, string $language): string
    {
        // A query endpoint survives slug/permalink changes and cannot take over an existing WP section.
        return add_query_arg(['rnl_calendar' => $identity, 'rnl_calendar_language' => $language], home_url('/'));
    }
}
