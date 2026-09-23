<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

/** Private measurement events, retained for 30 days; no user/account/IP or full URLs. */
final class JourneyStore
{
    public static function table(): string { global $wpdb; return $wpdb->prefix . 'rnl_journey_events'; }
    public static function install(): void
    {
        if (get_option('rnl_journey_schema') === '1') { return; }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collation = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            event_id char(36) NOT NULL,
            journey char(64) NOT NULL,
            created_at datetime NOT NULL,
            stage varchar(20) NOT NULL,
            course_id bigint(20) unsigned NOT NULL DEFAULT 0,
            page_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(100) NOT NULL DEFAULT '',
            medium varchar(100) NOT NULL DEFAULT '',
            campaign varchar(100) NOT NULL DEFAULT '',
            content varchar(100) NOT NULL DEFAULT '',
            detail text NOT NULL,
            PRIMARY KEY  (event_id),
            KEY journey (journey),
            KEY created_at (created_at)
        ) $collation;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) { throw new \RuntimeException('Journey storage unavailable.'); }
        update_option('rnl_journey_schema', '1', false);
    }
    public static function hash(string $id): string { return hash_hmac('sha256', $id, wp_salt('auth')); }
    public static function purge(): void
    {
        if (get_option('rnl_journey_schema') !== '1') { return; }
        global $wpdb; $table = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < %s", gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)));
    }
    public static function forget(string $journey): void
    {
        global $wpdb; $lock = 'rnl-journey:' . md5(DB_NAME . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $lock)) !== 1) { throw new \RuntimeException('Measurement unavailable.'); }
        try {
            $hash = self::hash($journey);
            set_transient('rnl_revoked_' . $hash, 1, DAY_IN_SECONDS);
            $wpdb->delete(self::table(), ['journey' => $hash]);
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    public static function record(array $event): bool
    {
        global $wpdb; $table = self::table();
        // Separate short lock; measurement never holds the course-planning writer lock.
        $lock = 'rnl-journey:' . md5(DB_NAME . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $lock)) !== 1) { return false; }
        try {
            $journey = self::hash($event['journey_id']);
            if (get_transient('rnl_revoked_' . $journey)) { return false; }
            if ($wpdb->get_var($wpdb->prepare("SELECT event_id FROM $table WHERE event_id = %s", $event['event_id']))) { return true; }
            if ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE journey = %s", $journey)) >= 200
                || (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE created_at >= %s", gmdate('Y-m-d H:i:s', time() - 60))) >= 1000) { return false; }
            $c = $event['campaign'];
            return $wpdb->insert($table, ['event_id' => $event['event_id'], 'journey' => $journey, 'created_at' => gmdate('Y-m-d H:i:s'),
                'stage' => $event['stage'], 'course_id' => $event['course_id'], 'page_id' => $event['page_id'],
                'source' => $c['utm_source'], 'medium' => $c['utm_medium'], 'campaign' => $c['utm_campaign'], 'content' => $c['utm_content'],
                'detail' => wp_json_encode(['campaign' => $c, 'click_ids' => $event['click_ids'],
                    'letsreg' => $event['stage'] === 'letsreg_click' ? SalesHistory::clickBinding($event['course_id']) : null])]) !== false;
        } finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    public static function report(): array
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException('Forbidden.', 403); }
        if (get_option('rnl_journey_schema') !== '1') { return ['stages' => [], 'campaigns' => [], 'courses' => []]; }
        self::purge(); global $wpdb; $table = self::table();
        return ['stages' => $wpdb->get_results("SELECT stage, COUNT(*) AS events, COUNT(DISTINCT journey) AS journeys FROM $table GROUP BY stage", ARRAY_A),
            'campaigns' => $wpdb->get_results("SELECT source, medium, campaign, content, COUNT(DISTINCT journey) AS journeys,
                COUNT(DISTINCT CASE WHEN stage = 'course_view' THEN journey END) AS views,
                COUNT(DISTINCT CASE WHEN stage = 'letsreg_click' THEN journey END) AS clicks
                FROM $table GROUP BY source, medium, campaign, content ORDER BY journeys DESC LIMIT 50", ARRAY_A),
            'courses' => $wpdb->get_results("SELECT course_id,
                COUNT(DISTINCT CASE WHEN stage = 'course_view' THEN journey END) AS views,
                COUNT(DISTINCT CASE WHEN stage = 'letsreg_click' THEN journey END) AS clicks
                FROM $table WHERE course_id > 0 GROUP BY course_id ORDER BY views DESC LIMIT 50", ARRAY_A)];
    }
}
