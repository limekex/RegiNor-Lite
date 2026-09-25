<?php
/** Local-only diagnostics exercised against the real workflow and rollback tests. */
use RegiNor\Lite\Infrastructure\Mutation;
use RegiNor\Lite\Infrastructure\MutationTrace;

if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') { throw new RuntimeException('Kun lokale WP-CLI-tester.'); }
$traceLog = tempnam(sys_get_temp_dir(), 'rnl-trace-');
$previousLog = ini_get('error_log');
ini_set('error_log', $traceLog);
define('RNL_TRACE_MUTATIONS', true);
define('RNL_TRACE_SITE_ID', get_current_blog_id());
try {
    global $wpdb;
    $connection = (int) $wpdb->get_var('SELECT CONNECTION_ID()');
    $queriesBefore = $wpdb->num_queries;
    MutationTrace::begin();
    MutationTrace::phase('operation');
    MutationTrace::finish();
    if ($queriesBefore !== $wpdb->num_queries) { throw new RuntimeException('Diagnostikk utfører ekstra databasekall.'); }
    $value = Mutation::run(static fn () => Mutation::run(static fn () => 'unchanged'), true);
    if ($value !== 'unchanged') { throw new RuntimeException('Nøstet operasjon mistet returverdien.'); }
    $beforeWorkflow = file_get_contents($traceLog);
    preg_match_all('/RNL-MUTATION (\{[^\n]+\})/', $beforeWorkflow, $matches);
    $preRecords = array_map(static fn ($line) => json_decode($line, true), $matches[1]);
    if (count(array_unique(array_column($preRecords, 'trace_id'))) !== 2) { throw new RuntimeException('Nøsting opprettet et eget spor.'); }

    // Existing tests cover successful publish/draft plus failed status writes and full rollback.
    (static function (): void { require __DIR__ . '/wordpress-workflow.php'; })();
    preg_match_all('/RNL-MUTATION (\{[^\n]+\})/', file_get_contents($traceLog), $matches);
    $records = array_map(static fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
    foreach ($records as $record) {
        if ($record['db_connection'] !== $connection) { throw new RuntimeException('Diagnostikken identifiserer ikke riktig databaseforbindelse.'); }
    }
    $phases = array_column($records, 'phase'); $events = array_column($records, 'event');
    foreach (['lifecycle.publish', 'lifecycle.draft', 'metadata.write', 'metadata.done', 'status.write', 'status.done', 'committed', 'rolled_back', 'lock.released'] as $phase) {
        if (!in_array($phase, $phases, true)) { throw new RuntimeException('Mangler spor: ' . $phase); }
    }
    if (!in_array('finished_with_error', $events, true) || !in_array('finished', $events, true)) { throw new RuntimeException('Resultat mangler i spor.'); }
    if (str_contains(implode('', $matches[1]), 'Sprint 3') || str_contains(implode('', $matches[1]), 'Testbeskrivelse')) { throw new RuntimeException('Kursinnhold havnet i spor.'); }
    $failedStatus = array_filter($records, static fn ($r) => $r['event'] === 'failed' && $r['phase'] === 'status.write' && $r['object_id'] > 0 && $r['transaction']);
    if (!$failedStatus) { throw new RuntimeException('Opprinnelig feilpunkt er borte fra sporet etter rollback.'); }
    WP_CLI::success('Diagnostikk: riktig forbindelse, ingen ekstra SQL, nøsting, lagring og rollback verifisert.');
} finally {
    MutationTrace::finish();
    ini_set('error_log', $previousLog);
    unlink($traceLog);
}
