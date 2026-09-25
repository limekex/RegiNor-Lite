<?php
/** WP-CLI --require fixture: observe translations before WordPress init. */

$GLOBALS['rnl_test_early_translations'] = [];
WP_CLI::add_hook('after_wp_config_load', static function (): void {
    require_once ABSPATH . 'wp-includes/plugin.php';
    add_filter('gettext', static function (string $translated, string $text, string $domain): string {
        if ($domain === 'reginor-lite' && !did_action('init')) {
            $GLOBALS['rnl_test_early_translations'][] = $text;
        }
        return $translated;
    }, 10, 3);
    // Other plugins can inspect/schedule cron jobs while plugins are loading.
    add_action('plugins_loaded', static function (): void {
        $GLOBALS['rnl_test_early_schedules'] = wp_get_schedules();
    }, PHP_INT_MAX);
});
