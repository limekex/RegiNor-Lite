<?php
/** Local wp-env MU loader. Not part of the installable plugin. Never put secrets in this file. */
if (!defined('ABSPATH') || wp_get_environment_type() !== 'local') { return; }

(static function (): void {
    // Docker mounts this outside /var/www/html, for both Apache and WP-CLI.
    $path = '/var/www/rnl-letsreg.json';
    if (!is_readable($path)) { return; }
    $raw = file_get_contents($path, false, null, 0, 16385);
    if (!is_string($raw) || strlen($raw) > 16384) { return; }
    $config = json_decode($raw, true, 4);
    if (!is_array($config)) { return; }
    foreach (['AFFILIATE_ID', 'ORGANIZER_ID', 'USERNAME', 'PASSWORD', 'CLIENT_ID'] as $key) {
        $name = 'RNL_LETSREG_' . $key;
        $value = $config[$name] ?? '';
        if ((is_string($value) || is_int($value)) && !str_contains((string) $value, "\0")) {
            putenv($name . '=' . $value);
        }
    }
})();
