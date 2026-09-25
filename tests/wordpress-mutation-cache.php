<?php
/** Local-only regression: save hooks must reuse request-local reads during transactions. */
use RegiNor\Lite\Infrastructure\Mutation;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokale WP-CLI-tester.'); }
global $wpdb, $wp_object_cache;
if (wp_using_ext_object_cache() || get_class($wp_object_cache) !== 'WP_Object_Cache') { throw new RuntimeException('Prøven krever WordPress sin lokale objektcache.'); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$id = 0; $hook = null; $counts = []; $suspended = wp_suspend_cache_addition();
$option = 'rnl_cache_probe_' . wp_generate_uuid4();
$missing = $option . '_new';
try {
    add_option($option, 'before', '', true);
    $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'RegiNor synthetic cache probe']);
    if (is_wp_error($id) || !$id) { throw new RuntimeException('Kunne ikke opprette testobjekt.'); }
    update_post_meta($id, 'rnl_cache_probe', 'before');
    $hook = static function ($postId) use ($id, $option, &$counts): void {
        if ((int) $postId !== $id) { return; }
        global $wpdb;
        // A cache invalidation in one save callback must not disable all later priming.
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete($id, 'post_meta');
        $before = $wpdb->num_queries;
        for ($i = 0; $i < 50; ++$i) {
            get_option($option);
            get_post_meta($id, 'rnl_cache_probe', true);
        }
        $counts[] = $wpdb->num_queries - $before;
    };
    add_action('save_post', $hook, 100);
    Mutation::run(static function () use ($id): void {
        Mutation::touch($id);
        wp_update_post(['ID' => $id, 'post_status' => 'private']);
    }, true);
    $assert(count($counts) === 1, 'Lagringshooken ble ikke kjørt som vanlig.');
    $assert($counts[0] <= 3, '100 gjentatte lesinger i lagringshook brukte ' . $counts[0] . ' SQL-kall; forventet høyst 3.');
    $assert(get_post_status($id) === 'private', 'Status ble ikke lagret.');

    // Reads populated before rollback must not survive in post, meta, options or query caches.
    $rollback = new RuntimeException('Synthetic rollback');
    try {
        Mutation::run(static function () use ($id, $option, $missing, $rollback, $assert): void {
            Mutation::touch($id);
            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
            update_post_meta($id, 'rnl_cache_probe', 'uncommitted');
            update_option($option, 'uncommitted');
            get_option($missing); // Prime negative cache before adding the option.
            add_option($missing, 'uncommitted', '', false);
            $assert(get_option($option) === 'uncommitted' && get_option($missing) === 'uncommitted', 'Prøven leste ikke endringer før rollback.');
            $assert(get_post_meta($id, 'rnl_cache_probe', true) === 'uncommitted', 'Metadataprøven mangler endringen.');
            get_posts(['post_type' => 'post', 'post_status' => 'draft', 'include' => [$id]]);
            throw $rollback;
        }, true);
        throw new RuntimeException('Forventet rollback.');
    } catch (RuntimeException $error) { $assert($error === $rollback, 'Opprinnelig unntak ble erstattet.'); }
    $assert(get_post_status($id) === 'private', 'Tilbakerullet status ligger fortsatt i cache.');
    $assert(get_post_meta($id, 'rnl_cache_probe', true) === 'before', 'Tilbakerullet metadata ligger fortsatt i cache.');
    $assert(get_option($option) === 'before' && get_option($missing) === false, 'Tilbakerullede options ligger fortsatt i cache.');
    $assert(get_posts(['post_type' => 'post', 'post_status' => 'draft', 'include' => [$id]]) === [], 'Tilbakerullet spørringsresultat ligger fortsatt i cache.');
    $assert(!Mutation::active() && wp_suspend_cache_addition() === $suspended, 'Intern tilstand ble ikke gjenopprettet.');

    // Respect a suspension owned by another caller.
    wp_suspend_cache_addition(true);
    Mutation::run(static function () use ($assert): void { $assert(wp_suspend_cache_addition(), 'En annens cachesperre ble fjernet.'); }, true);
    $assert(wp_suspend_cache_addition(), 'En annens cachesperre ble ikke bevart.');
    wp_suspend_cache_addition($suspended);

    // External backends retain their previous policy; never flush a shared cache.
    wp_cache_set($option, 'keep', 'rnl_probe');
    wp_using_ext_object_cache(true);
    try {
        Mutation::run(static function () use ($assert): void { $assert(wp_suspend_cache_addition(), 'Ekstern cache mistet eksisterende beskyttelse.'); }, true);
        $assert(wp_cache_get($option, 'rnl_probe') === 'keep', 'Ekstern cache ble tømt.');
    } finally { wp_using_ext_object_cache(false); }
    WP_CLI::success('Cache/transaksjon: ' . $checks . ' kontroller. 100 hook-lesinger: ' . $counts[0] . ' SQL-kall.');
} finally {
    wp_suspend_cache_addition($suspended);
    if ($hook) { remove_action('save_post', $hook, 100); }
    if ($id) { wp_delete_post($id, true); }
    delete_option($option); delete_option($missing);
    wp_cache_delete($option, 'rnl_probe');
}
