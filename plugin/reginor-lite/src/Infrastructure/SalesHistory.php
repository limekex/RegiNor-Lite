<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Domain\Analytics\SalesObservation;
use function RegiNor\Lite\translate as __;

/** Private event-level totals. No orders, participants, tokens or guessed conversions. */
final class SalesHistory
{
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'rnl_sales_history'; }
    public static function install(): void
    {
        if (get_option('rnl_sales_history_schema') === '1') { return; }
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            account char(64) NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            observed_at bigint(20) unsigned NOT NULL,
            participants bigint(20) DEFAULT NULL,
            order_sum_minor bigint(20) DEFAULT NULL,
            bindings longtext NOT NULL,
            PRIMARY KEY  (account,event_id,observed_at),
            KEY observed_at (observed_at)
        ) $collation;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { throw new \RuntimeException(__('Historikken kunne ikke opprettes. Prøv igjen.', 'reginor-lite'), 503); }
        update_option('rnl_sales_history_schema', '1', false);
    }
    public static function boot(): void
    {
        add_action('rnl_journey_cleanup', [self::class, 'purge']);
        add_action('init', static function (): void {
            if (get_option('rnl_sales_history_schema') === '1' && !wp_next_scheduled('rnl_journey_cleanup')) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'rnl_journey_cleanup'); }
        });
    }
    public static function purge(): void
    {
        if (get_option('rnl_sales_history_schema') !== '1') { return; }
        global $wpdb; $table = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE observed_at < %d", time() - 30 * DAY_IN_SECONDS));
    }
    public static function binding(array $course): ?array
    {
        $identity = LetsRegConnection::identity(); $mapping = $course['letsreg_mapping'] ?? null;
        if (!$identity || !$mapping || $mapping['affiliate_id'] !== $identity['affiliate_id'] || $mapping['organizer_id'] !== $identity['organizer_id']) { return null; }
        $ids = array_column($mapping['categories'], 'id'); sort($ids, SORT_NUMERIC);
        return ['account' => $identity['fingerprint'], 'event' => $mapping['event_id'],
            'mapping' => hash('sha256', wp_json_encode([$identity['fingerprint'], $mapping['event_id'], $ids]))];
    }
    public static function clickBinding(int $id): ?array
    {
        if (get_post_type($id) !== 'rnl_group') { return null; }
        $state = get_post_meta($id, ContentTypes::META, true);
        return self::binding($state['data'] ?? []);
    }
    public static function capture(array $identity, int $eventId, array $observation): void
    {
        if (!get_option('rnl_sales_history_enabled', false) || get_option('rnl_sales_history_schema') !== '1' || LetsRegConnection::identity() !== $identity) { return; }
        $bindings = [];
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            $binding = self::clickBinding((int) $id);
            if ($binding && $binding['event'] === $eventId) { $bindings[$id] = $binding['mapping']; }
        }
        if (!$bindings) { return; }
        global $wpdb; $table = self::table(); $at = time();
        $last = $wpdb->get_var($wpdb->prepare("SELECT MAX(observed_at) FROM $table WHERE account=%s AND event_id=%d", $identity['fingerprint'], $eventId));
        // Keep at most one observation per minute, with its actual observation time.
        if ($last !== null && (int) $last + 60 > $at) { return; }
        // Do not let optional history failures interrupt the existing capacity/check workflow.
        $ok = $wpdb->insert(self::table(), ['account' => $identity['fingerprint'], 'event_id' => $eventId, 'observed_at' => $at,
            'participants' => $observation['participants'], 'order_sum_minor' => $observation['order_sum_minor'], 'bindings' => wp_json_encode($bindings)]);
        if ($ok === false) {
            $table = self::table();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT observed_at FROM $table WHERE account=%s AND event_id=%d AND observed_at=%d", $identity['fingerprint'], $eventId, $at));
            if (!$exists) { set_transient('rnl_sales_history_error', 1, HOUR_IN_SECONDS); }
        }
    }
    public static function report(int $courseId, int $minutes = 15): array
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Du har ikke tilgang til denne rapporten.', 'reginor-lite'), 403); }
        $course = (new CourseRepository())->get($courseId, 'group')['data'];
        $binding = self::binding($course); $now = time(); $since = $now - 30 * DAY_IN_SECONDS;
        $result = ['course' => $course, 'binding' => $binding, 'observations' => [], 'days' => [], 'campaigns' => [], 'shared' => [], 'clicks_complete' => true, 'history_complete' => true];
        if (!$binding) { return $result; }
        self::purge(); JourneyStore::purge(); global $wpdb; $table = self::table();
        $rows = get_option('rnl_sales_history_schema') === '1' ? $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE account=%s AND event_id=%d AND observed_at >= %d ORDER BY observed_at DESC LIMIT 50001", $binding['account'], $binding['event'], $since), ARRAY_A) : [];
        $result['history_complete'] = count($rows) <= 50000;
        $rows = array_reverse(array_slice($rows, 0, 50000));
        $clicks = []; $campaigns = [];
        if (get_option('rnl_journey_schema') === '1') {
            $journeyTable = JourneyStore::table();
            // Every linked course is included in candidate counts: one event can serve several courses.
            $events = $wpdb->get_results($wpdb->prepare("SELECT journey,created_at,course_id,source,medium,campaign,detail FROM $journeyTable WHERE stage='letsreg_click' AND created_at >= %s ORDER BY created_at ASC LIMIT 10001", gmdate('Y-m-d H:i:s', $since)), ARRAY_A);
            $result['clicks_complete'] = count($events) <= 10000;
            foreach ($events as $event) {
                $at = strtotime($event['created_at'] . ' UTC');
                if (!$at || $at > $now) { continue; }
                if ((int) $event['course_id'] === $courseId) {
                    $day = wp_date('Y-m-d', $at); $result['days'][$day]['visits'][$event['journey']] = true;
                    $key = wp_json_encode([$day, $event['source'], $event['medium'], $event['campaign']]);
                    $campaigns[$key] ??= ['day' => $day, 'source' => $event['source'], 'medium' => $event['medium'], 'campaign' => $event['campaign'], 'visits' => []];
                    $campaigns[$key]['visits'][$event['journey']] = true;
                }
                $linked = json_decode($event['detail'], true)['letsreg'] ?? null;
                if (!is_array($linked) || ($linked['account'] ?? '') !== $binding['account'] || ($linked['event'] ?? 0) !== $binding['event']) { continue; }
                $clicks[] = ['at' => $at, 'journey' => $event['journey'], 'source' => $event['source'], 'medium' => $event['medium'], 'campaign' => $event['campaign']];
            }
        }
        $previous = null;
        foreach ($rows as $index => $row) {
            $scope = json_decode($row['bindings'], true);
            if (($scope[$courseId] ?? '') !== $binding['mapping']) { $previous = null; continue; }
            $observation = ['at' => (int) $row['observed_at'], 'participants' => $row['participants'] === null ? null : (int) $row['participants'], 'order_sum_minor' => $row['order_sum_minor'] === null ? null : (int) $row['order_sum_minor']];
            if ($observation['at'] > $now) { continue; }
            $observation['change'] = SalesObservation::change($previous, $observation);
            $observation['coincidence'] = ($index >= count($rows) - 50 && $result['clicks_complete'] && $result['history_complete']) ? SalesObservation::coincidence($previous, $observation, $clicks, $minutes) : ['reason' => 'incomplete', 'journeys' => 0, 'sources' => []];
            $observation['from'] = $previous['at'] ?? null;
            $result['observations'][] = $observation;
            $result['days'][wp_date('Y-m-d', $observation['at'])]['observation'] = $observation;
            $previous = $observation;
        }
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true]) as $id) {
            $other = self::clickBinding((int) $id);
            if ($other && $other['event'] === $binding['event']) { $result['shared'][] = (int) $id; }
        }
        foreach ($result['days'] as &$day) { $day['clicks'] = count($day['visits'] ?? []); unset($day['visits']); } unset($day);
        foreach ($campaigns as $campaign) { $campaign['clicks'] = count($campaign['visits']); unset($campaign['visits']); $result['campaigns'][] = $campaign; }
        krsort($result['days']);
        return $result;
    }
}
