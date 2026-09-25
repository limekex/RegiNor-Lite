<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\PublicSite;
use function RegiNor\Lite\translate as __;

/** One opt-in, all-day calendar span per period. RegiNor remains the source of truth. */
final class TecBridge
{
    public const OWNER = '_rnl_tec_period_id';
    public const EVENT = '_rnl_tec_event_id';
    public const ERROR = '_rnl_tec_error';
    private static bool $writing = false;
    private static bool $reading = false;
    private static bool $pending = false;
    private static ?array $allowed = null;
    private static ?string $syncProblem = null;

    public static function available(): bool { return function_exists('tribe_events') && post_type_exists('tribe_events'); }

    public static function boot(): void
    {
        add_filter('post_row_actions', [\RegiNor\Lite\Admin\CalendarSettings::class, 'eventActions'], 20, 2);
        TecSyncQueue::boot();
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, static function ($metaId, $id, $key): void { if ($key === ContentTypes::META) { self::$pending = true; self::$allowed = null; } }, 10, 3);
        }
        add_action('before_delete_post', static function ($id): void { if (in_array(get_post_type($id), ContentTypes::TYPES, true)) { self::$pending = true; self::$allowed = null; } });
        add_action('updated_option', static function ($name): void { if ($name === 'rnl_course_page_id') { self::$pending = true; self::$allowed = null; } });
        add_action('transition_post_status', static function ($new, $old, $post): void { if (in_array($post->post_type, ContentTypes::TYPES, true) || ($post->post_type === 'page' && (int) get_option('rnl_course_page_id') === (int) $post->ID)) { self::$pending = true; self::$allowed = null; } }, 10, 3);
        add_action('shutdown', [self::class, 'queuePending'], 20);
        add_filter('post_type_link', static function ($url, $post) { return $post->post_type === 'tribe_events' ? (self::periodUrl((int) $post->ID) ?: $url) : $url; }, 20, 2);
        add_filter('tribe_get_event_link', static function ($link, $id, $full) {
            $url = self::periodUrl((int) $id); if (!$url) { return $link; }
            return $full ? '<a href="' . esc_url($url) . '">' . esc_html(get_the_title($id)) . '</a>' : $url;
        }, 20, 3);
        add_filter('tribe_events_views_v2_should_cache_html', static fn ($cache) => self::owned() ? false : $cache);
        add_filter('tribe_events_views_v2_view_cached_html', static fn ($html) => self::owned() ? false : $html);
        add_filter('tribe_ical_template_event_ids', static function ($ids) {
            if (!is_array($ids)) { return $ids; }
            $blocked = self::blocked();
            return array_values(array_filter($ids, static fn ($id) => !in_array(self::eventId((int) $id), $blocked, true)));
        });
        // TEC 6.17.5 can expose truthy all-day metadata while its exporter checks the literal 'yes'.
        // Keep our period spans unambiguously all-day in exported calendars, with an exclusive end.
        add_filter('tribe_ical_feed_item', static function ($item, $post) {
            if (!self::owner((int) $post->ID)) { return $item; }
            $start = substr((string) get_post_meta($post->ID, '_EventStartDate', true), 0, 10);
            $end = substr((string) get_post_meta($post->ID, '_EventEndDate', true), 0, 10);
            try {
                \RegiNor\Lite\Domain\LocalDateTime::date($start); \RegiNor\Lite\Domain\LocalDateTime::date($end);
                $item['DTSTART'] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $start);
                $item['DTEND'] = 'DTEND;VALUE=DATE:' . (new \DateTimeImmutable($end, new \DateTimeZone('UTC')))->modify('+1 day')->format('Ymd');
            } catch (\Throwable) { /* Leave malformed external data to TEC's existing error handling. */ }
            return $item;
        }, 20, 2);
        add_filter('map_meta_cap', static function ($caps, $cap, $user, $args) {
            if (!self::$writing && in_array($cap, ['edit_post', 'delete_post'], true) && self::owner((int) ($args[0] ?? 0))) { return ['do_not_allow']; }
            return $caps;
        }, 20, 4);
        // Keep stale published projections hidden even if a sync write fails.
        add_filter('posts_where', static function ($where) {
            if (self::$writing || self::$reading || (is_admin() && current_user_can('rnl_edit_periods'))) { return $where; }
            $blocked = self::blocked(); if (!$blocked) { return $where; }
            global $wpdb; return $where . " AND {$wpdb->posts}.ID NOT IN (" . implode(',', $blocked) . ')';
        }, 100);
        add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
            $type = get_post_type_object('tribe_events');
            $coreRoute = preg_quote(($type->rest_namespace ?? 'wp/v2') . '/' . (($type->rest_base ?? '') ?: 'tribe_events'), '#');
            if (preg_match('#^/(?:tribe/events/v1/events|' . $coreRoute . ')/(\d+)(?:/|$)#', $request->get_route(), $match) && in_array(self::eventId((int) $match[1]), self::blocked(), true)) { return new \WP_Error('rnl_hidden_period', __('Kursperioden er ikke tilgjengelig nå.', 'reginor-lite'), ['status' => 404]); }
            return $result;
        }, 10, 3);
        add_filter('rest_post_dispatch', static function ($response, $server, $request) {
            if (str_starts_with($request->get_route(), '/tribe/') && self::owned() && $response instanceof \WP_REST_Response) { CachePolicy::response($response); }
            return $response;
        }, 20, 3);
        add_action('template_redirect', static function (): void {
            if (!self::owned()) { return; }
            if (is_singular('tribe_events') || is_post_type_archive('tribe_events') || isset($_GET['ical']) || (function_exists('tribe_is_event_query') && tribe_is_event_query())) { PublicSite::noCache(); }
            if (is_singular('tribe_events')) {
                $id = get_queried_object_id(); $url = self::periodUrl($id);
                if ($url && !in_array(self::eventId($id), self::blocked(), true)) { wp_safe_redirect($url, 302); exit; }
            }
        }, 0);
    }

    /** TEC Pro may return an occurrence ID rather than the persistent WordPress post ID. */
    public static function eventId(int $id): int
    {
        return $id > 0 ? (int) apply_filters('tec_events_custom_tables_v1_normalize_occurrence_id', $id) : 0;
    }
    public static function owner(int $id): int
    {
        $id = self::eventId($id);
        return get_post_type($id) === 'tribe_events' ? (int) get_post_meta($id, self::OWNER, true) : 0;
    }
    public static function periodUrl(int $id): string
    {
        $period = self::owner($id);
        return $period ? PublicSite::url(null, ['rnl_period' => $period]) : '';
    }
    /** Raw identity lookup, independent of filters and public status; includes orphaned projections. */
    public static function owned(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT p.ID, m.meta_value FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = 'tribe_events' AND m.meta_key = %s ORDER BY p.ID", self::OWNER), ARRAY_A);
        $result = []; foreach ($rows as $row) { $result[(int) $row['ID']] = (int) $row['meta_value']; } return $result;
    }
    private static function state(int $id): array
    {
        $raw = get_post_meta($id, ContentTypes::META, true);
        if (get_post_type($id) !== ContentTypes::TYPES['period'] || !is_array($raw) || !is_array($raw['data'] ?? null)) { return []; }
        try { return StateSchema::validate('period', $raw['data']); } catch (\Throwable) { return []; }
    }
    private static function eligible(array $catalog): array
    {
        $result = []; if (!PublicSite::url()) { return $result; }
        foreach ($catalog['periods'] as $id => $public) {
            $data = self::state((int) $id);
            if (empty($data['calendar_enabled']) || !empty($data['cancelled'])) { continue; }
            $end = $data['end_date'] ?? ($public['last'] ? (new \DateTimeImmutable($public['last']))->setTimezone(new \DateTimeZone($data['timezone']))->format('Y-m-d') : null);
            if (!$end || $end < $data['start_date']) { continue; }
            $result[(int) $id] = ['data' => $data, 'end' => $end];
        }
        return $result;
    }
    private static function blocked(): array
    {
        $owned = self::owned(); if (!$owned) { return []; }
        if (self::$allowed === null) {
            self::$reading = true;
            try { self::$allowed = array_keys(self::eligible((new Catalog())->read())); }
            catch (\Throwable) { self::$allowed = []; }
            finally { self::$reading = false; }
        }
        $blocked = [];
        foreach ($owned as $id => $period) {
            if (!in_array($period, self::$allowed, true) || self::eventId((int) get_post_meta($period, self::EVENT, true)) !== $id) { $blocked[] = $id; }
        }
        return $blocked;
    }
    public static function queuePending(): void
    {
        if (self::$pending) { self::$pending = false; TecSyncQueue::request(); }
    }
    public static function sync(): void
    {
        if (!self::available() || self::$writing || Mutation::active()) { return; }
        self::$syncProblem = null;
        $lock = DatabaseLock::name(true);
        try {
            if (!DatabaseLock::acquire($lock, 0)) {
                self::$syncProblem = __('En kalenderoppdatering pågår. Du kan fortsatt redigere kursperioden. En senere bakgrunnskontroll prøver igjen.', 'reginor-lite');
                return;
            }
        } catch (\RuntimeException $error) { self::$syncProblem = $error->getMessage(); TecSyncQueue::finished(self::$syncProblem); return; }
        self::$writing = true; self::$pending = false; self::$allowed = null;
        $language = null; $restoreLanguage = false; $switchedLocale = false; $failed = false; $catalog = [];
        try {
            TecSyncQueue::started();
            $language = apply_filters('wpml_current_language', null); $defaultLanguage = apply_filters('wpml_default_language', null);
            if ($defaultLanguage && $language !== $defaultLanguage) {
                $restoreLanguage = true; do_action('wpml_switch_language', $defaultLanguage);
            }
            $switchedLocale = switch_to_locale((string) (get_option('WPLANG') ?: 'en_US'));
            // Serialize the source snapshot with publication, but never hold the course
            // writer lock while TEC/Pro or third-party save hooks update the projection.
            $sourceLock = DatabaseLock::name();
            if (!DatabaseLock::acquire($sourceLock, 0)) {
                self::$syncProblem = __('Kalenderen venter til en kursendring er ferdig lagret. En senere bakgrunnskontroll prøver igjen.', 'reginor-lite');
                return;
            }
            try { $catalog = (new Catalog())->read(); $eligible = self::eligible($catalog); $owned = self::owned(); }
            finally { DatabaseLock::release($sourceLock); }
            // Hide first: withdrawal must not depend on successful creation of another event.
            foreach ($owned as $event => $period) {
                if (!isset($eligible[$period]) || (int) array_search($period, $owned, true) !== $event) {
                    if (get_post_status($event) === 'publish') { self::save($event, ['status' => 'draft']); }
                }
            }
            foreach ($eligible as $period => $item) {
                $phase = 'prepare';
                try {
                    $d = $item['data']; $url = PublicSite::url(null, ['rnl_period' => $period]);
                    // Persist source-language content; the linked RegiNor page handles WPML presentation.
                    $content = '<p>' . esc_html(__('Kursperiode med undervisning på faste kursdager. Se kursoversikten for tider, kursfrie dager og påmelding.', 'reginor-lite')) . '</p><p><a href="' . esc_url($url) . '">' . esc_html(__('Se alle kursene i perioden', 'reginor-lite')) . '</a></p>';
                    $args = ['title' => sprintf(/* translators: %s: course period name. */ __('Kursperiode: %s', 'reginor-lite'), $d['title']), 'content' => $content,
                        'status' => 'publish', 'start_date' => $d['start_date'] . ' 00:00:00', 'end_date' => $item['end'] . ' 23:59:59', 'timezone' => $d['timezone'],
                        'all_day' => 'yes', 'url' => $url, 'show_map' => false, 'show_map_link' => false];
                    $defaults = TecDefaults::read();
                    $event = (int) array_search($period, $owned, true); $hash = hash('sha256', wp_json_encode([$args, $defaults]));
                    if (!$event) {
                        $phase = 'create';
                        $created = tribe_events()->set_args(array_replace($args, ['status' => 'draft', self::OWNER => $period, 'author' => (int) get_post_field('post_author', $period)]))->create();
                        if (!$created instanceof \WP_Post) { throw new \RuntimeException('TEC create failed'); }
                        $event = self::eventId((int) $created->ID);
                        if (!$event || get_post_type($event) !== 'tribe_events') { throw new \RuntimeException('TEC event identity missing'); }
                        if (self::owner($event) !== $period) { update_post_meta($event, self::OWNER, $period); }
                    }
                    update_post_meta($period, self::EVENT, $event);
                    if (get_post_meta($event, '_rnl_tec_hash', true) !== $hash || get_post_status($event) !== 'publish') {
                        $phase = 'save'; self::save($event, $args);
                        $phase = 'defaults'; TecDefaults::apply($event, $defaults); update_post_meta($event, '_rnl_tec_hash', $hash);
                    }
                    delete_post_meta($period, self::ERROR);
                } catch (\Throwable $error) {
                    update_post_meta($period, self::ERROR, match ($phase) {
                        'create' => __('TEC kunne ikke opprette arrangementet. Perioden er offentlig og klar for kalenderdeling. Administrator må kontrollere TEC/TEC Pro-versjoner og feilloggen før nytt forsøk.', 'reginor-lite'),
                        'save' => $error->getCode() === 53114 ? $error->getMessage() : __('TEC avbrøt oppdateringen av kalenderoppføringen. Kursperioden er beholdt. En senere bakgrunnskontroll prøver igjen.', 'reginor-lite'),
                        'defaults' => __('Arrangementet er lagret, men kalenderkategori eller bilde kunne ikke oppdateres. Kontroller valgene under Nettsidevisning → Arrangementskalender.', 'reginor-lite'),
                        default => __('Kalenderoppføringen kunne ikke klargjøres. En senere bakgrunnskontroll prøver igjen. Hvis feilen fortsetter, må administrator undersøke feilloggen.', 'reginor-lite'),
                    });
                }
            }
        } catch (\Throwable $error) {
            $failed = true; // Read filters fail closed if reconciliation cannot determine visibility.
            self::$syncProblem = $error->getCode() === 503 ? $error->getMessage() : __('Kalendersynkroniseringen ble avbrutt under lesing eller skjuling av kalenderoppføringer. Administrator må kontrollere feilloggen. Dette er ikke en beskjed om at kursstart ligger frem i tid.', 'reginor-lite');
        } finally {
            try {
                if ($switchedLocale) { restore_previous_locale(); }
                if ($restoreLanguage) { do_action('wpml_switch_language', $language); }
            } catch (\Throwable) {
                $failed = true;
                self::$syncProblem = __('Kalenderoppdateringen kunne ikke gjenopprette språkinnstillingen. Hvis feilen fortsetter, må administrator kontrollere WPML og feilloggen.', 'reginor-lite');
            } finally {
                // Source changes may have committed while TEC was saving. Public reads
                // must evaluate current source visibility rather than the old snapshot.
                self::$allowed = $failed ? [] : null;
                self::$writing = false;
                try { TecSyncQueue::finished(self::$syncProblem); }
                finally { DatabaseLock::release($lock); }
                foreach (array_unique(array_merge(array_keys($catalog['periods'] ?? []), array_keys($catalog['groups'] ?? []))) as $id) {
                    wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta');
                }
            }
        }
    }
    private static function save(int $event, array $args): void
    {
        $event = self::eventId($event);
        if (!$event || !self::owner($event)) { throw new \RuntimeException('Not a RegiNor calendar event'); }
        // TEC documents unreliable ORM update return values (BTRIA-2310). Verify the
        // persisted fields, including the publish transition, instead of trusting the return shape.
        $result = null; $ormFailed = false;
        try {
            $result = tribe_events()->where('id', $event)->where('post_status', ['publish', 'draft', 'pending', 'private', 'future', 'trash'])->set_args($args)->save();
        } catch (\Throwable) { $ormFailed = true; }
        if (!self::writeDifferences($event, $args)) { return; }

        // The same TEC API used by its REST updater targets the persistent post directly.
        // No direct SQL/meta writes and no replacement event: TEC keeps ownership of date tables/hooks.
        $fallback = 'unavailable';
        if (is_callable(['Tribe__Events__API', 'updateEvent'])) {
            try {
                $updated = \Tribe__Events__API::updateEvent($event, self::eventApiArgs($args));
                $fallback = is_wp_error($updated) ? 'error' : (self::eventId((int) $updated) === $event ? 'returned_id' : 'unexpected_result');
            } catch (\Throwable) { $fallback = 'exception'; }
        }
        $different = self::writeDifferences($event, $args);
        if (!$different) { return; }
        $orm = $ormFailed ? 'exception' : ($result === null ? 'null' : (is_array($result) && !$result ? 'empty' : get_debug_type($result)));
        throw new \RuntimeException(sprintf(
            /* translators: 1: event ID, 2: fields still different after saving, 3: non-sensitive technical result codes. */
            __('TEC-oppføring #%1$d ble forsøkt oppdatert med begge lagringsmetodene, men disse feltene stemmer fortsatt ikke: %2$s. Kursperioden er beholdt. Oppgi denne meldingen ved feilsøking. Kontrollkode: %3$s.', 'reginor-lite'),
            $event, implode(', ', $different), 'orm=' . $orm . '; api=' . $fallback
        ), 53114);
    }

    private static function eventApiArgs(array $args): array
    {
        $mapped = [];
        foreach (['title' => 'post_title', 'content' => 'post_content', 'status' => 'post_status', 'timezone' => 'EventTimezone', 'url' => 'EventURL', 'all_day' => 'EventAllDay'] as $key => $target) {
            if (array_key_exists($key, $args)) { $mapped[$target] = $args[$key]; }
        }
        // The older date API reads the site's configured datepicker format, including d/m/Y.
        $formats = \Tribe__Date_Utils::datepicker_formats();
        $format = $formats[(int) tribe_get_option('datepickerFormat', 0)] ?? $formats[0];
        foreach (['start_date' => 'EventStartDate', 'end_date' => 'EventEndDate'] as $key => $target) {
            if (isset($args[$key])) { $mapped[$target] = (new \DateTimeImmutable($args[$key]))->format($format); }
        }
        foreach (['show_map' => 'EventShowMap', 'show_map_link' => 'EventShowMapLink'] as $key => $target) {
            if (array_key_exists($key, $args)) { $mapped[$target] = $args[$key] ? '1' : '0'; }
        }
        return $mapped;
    }

    /** Re-read after every attempt so a null return is neither an automatic success nor failure. */
    private static function writeDifferences(int $event, array $args): array
    {
        clean_post_cache($event); wp_cache_delete($event, 'post_meta');
        $post = get_post($event); $different = [];
        if (!$post || $post->post_type !== 'tribe_events' || !self::owner($event)) { return [__('arrangementsidentitet', 'reginor-lite')]; }
        foreach (['title' => ['post_title', __('tittel', 'reginor-lite')], 'content' => ['post_content', __('beskrivelse', 'reginor-lite')], 'status' => ['post_status', __('publiseringsstatus', 'reginor-lite')]] as $key => [$field, $label]) {
            if (array_key_exists($key, $args) && (string) $post->$field !== (string) $args[$key]) { $different[] = $label; }
        }
        foreach (['start_date' => ['_EventStartDate', __('startdato', 'reginor-lite')], 'end_date' => ['_EventEndDate', __('sluttdato', 'reginor-lite')], 'timezone' => ['_EventTimezone', __('tidssone', 'reginor-lite')], 'url' => ['_EventURL', __('kurslenke', 'reginor-lite')]] as $key => [$meta, $label]) {
            if (!array_key_exists($key, $args)) { continue; }
            $actual = (string) get_post_meta($event, $meta, true); $expected = (string) $args[$key];
            // All-day spans are inclusive calendar dates; TEC controls the day-boundary time.
            if (in_array($key, ['start_date', 'end_date'], true) && !empty($args['all_day'])) { $actual = substr($actual, 0, 10); $expected = substr($expected, 0, 10); }
            if ($actual !== $expected) { $different[] = $label; }
        }
        foreach (['all_day' => ['_EventAllDay', __('heldagsvalg', 'reginor-lite')], 'show_map' => ['_EventShowMap', __('kartvalg', 'reginor-lite')], 'show_map_link' => ['_EventShowMapLink', __('kartlenke', 'reginor-lite')]] as $key => [$meta, $label]) {
            if (array_key_exists($key, $args) && tribe_is_truthy(get_post_meta($event, $meta, true)) !== tribe_is_truthy($args[$key])) { $different[] = $label; }
        }
        return $different;
    }
    public static function status(int $period): string
    {
        $data = self::state($period);
        if (empty($data['calendar_enabled'])) { return ''; }
        if (!self::available()) { return __('Kalenderdeling er valgt. The Events Calendar må aktiveres for å vise perioden i kalenderen.', 'reginor-lite'); }
        if (get_post_status($period) !== 'publish') { return __('Kalenderoppføringen er skjult fordi kursperioden ikke er publisert. Publiser perioden for å dele den i kalenderen.', 'reginor-lite'); }
        if (!empty($data['cancelled'])) { return __('Kalenderoppføringen er skjult fordi kursperioden er avlyst.', 'reginor-lite'); }
        if (!PublicSite::pageId()) { return __('Kalenderoppføringen mangler en offentlig kursside å lenke til. Velg en publisert side uten passord under Nettsidevisning.', 'reginor-lite'); }
        if (get_post_field('post_password', $period) !== '') { return __('Kalenderoppføringen er skjult fordi kursperioden er passordbeskyttet.', 'reginor-lite'); }
        if (empty($data['visible_from']) || empty($data['visible_until'])) { return __('Kalenderoppføringen er skjult fordi perioden mangler Synlig fra eller Synlig til. Fyll ut begge datoene i periodeoppsettet.', 'reginor-lite'); }
        $now = new \DateTimeImmutable('now');
        if (new \DateTimeImmutable($data['visible_from']) > $now) {
            $date = wp_date('d.m.Y H:i', (new \DateTimeImmutable($data['visible_from']))->getTimestamp(), new \DateTimeZone($data['timezone']));
            return sprintf(/* translators: %s: visibility opening date and time in the period timezone. */ __('Kalenderoppføringen er skjult frem til Synlig fra: %s. Bruk en tidligere synlighetsdato hvis kursrekken skal vises nå; kursstart og salgsdatoer kan fortsatt ligge frem i tid.', 'reginor-lite'), $date);
        }
        if (new \DateTimeImmutable($data['visible_until']) <= $now) { return __('Kalenderoppføringen er skjult fordi Synlig til er passert. Kontroller synlighetsvinduet i periodeoppsettet.', 'reginor-lite'); }
        if (self::$syncProblem !== null) { return self::$syncProblem; }
        if (($background = TecSyncQueue::message()) !== null) { return $background; }
        $catalog = (new Catalog())->read();
        if (!isset($catalog['periods'][$period])) { return __('Kalenderoppføringen er skjult fordi perioden ikke har et offentlig tilgjengelig kurs. Kontroller publisering, kursøkter, beskrivelse, sal, sted og eventuelle instruktørreferanser.', 'reginor-lite'); }
        if (!isset(self::eligible($catalog)[$period])) { return __('Kalenderoppføringen mangler en gyldig sluttdato. Angi periodens sluttdato eller kontroller at en kursøkt gir en sluttdato etter periodens start.', 'reginor-lite'); }
        $error = get_post_meta($period, self::ERROR, true); if (is_string($error) && $error !== '') { return $error; }
        $event = self::eventId((int) get_post_meta($period, self::EVENT, true));
        return $event && get_post_status($event) === 'publish' && !in_array($event, self::blocked(), true)
            ? __('Perioden vises i arrangementskalenderen, med lenke til alle kursene.', 'reginor-lite')
            : __('Perioden er klar for kalenderdeling og venter på bekreftet bakgrunnskontroll. Hvis oppføringen fortsatt mangler etter noen minutter, må administrator kontrollere automatiske jobber og TEC-oppsettet.', 'reginor-lite');
    }
}
