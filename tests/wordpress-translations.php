<?php
/** Run with bootstrap-translations.php required before WordPress loads. */

if (!defined('WP_CLI') || !WP_CLI || !isset($GLOBALS['rnl_test_early_translations'], $GLOBALS['rnl_test_early_schedules'])) {
    throw new RuntimeException('Kjør via test:translations i lokalt wp-env.');
}

if ($GLOBALS['rnl_test_early_translations']) {
    WP_CLI::error('RegiNor oversetter før init: ' . implode(', ', array_unique($GLOBALS['rnl_test_early_translations'])));
}

$expected = ['rnl_minute' => 60, 'rnl_tec_five_minutes' => 300];
foreach ($expected as $key => $interval) {
    if (($GLOBALS['rnl_test_early_schedules'][$key]['interval'] ?? 0) !== $interval) {
        WP_CLI::error('Cron-intervall mangler før init: ' . $key);
    }
}

$translator = static function (string $translated, string $text, string $domain): string {
    return $domain === 'reginor-lite' ? 'TEST: ' . $text : $translated;
};
add_filter('gettext', $translator, 10, 3);
try {
    $schedules = wp_get_schedules();
    foreach ($expected as $key => $interval) {
        $early = $GLOBALS['rnl_test_early_schedules'][$key];
        if (($schedules[$key]['interval'] ?? 0) !== $interval || ($schedules[$key]['display'] ?? '') !== 'TEST: ' . $early['display']) {
            WP_CLI::error('Cron-intervall eller oversettelse etter init er feil: ' . $key);
        }
    }
} finally {
    remove_filter('gettext', $translator, 10);
}

WP_CLI::success('Ingen tidlig RegiNor-oversettelse; begge cron-intervaller finnes før init og oversettes etter init.');
