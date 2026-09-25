<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

/** Opt-in operational diagnostics. Never records payloads, SQL or exception messages. */
final class MutationTrace
{
    private static ?array $trace = null;
    private static bool $registered = false;
    private const PHASES = [
        'begin', 'lock.acquire', 'lock.acquired', 'transaction.check', 'transaction.start',
        'operation', 'lifecycle.validate', 'lifecycle.publish', 'lifecycle.draft',
        'lifecycle.trash', 'lifecycle.restore', 'lifecycle.done',
        'metadata.write', 'metadata.done', 'status.write', 'status.done',
        'commit', 'committed', 'rollback', 'rolled_back',
        'cache.runtime_clear', 'cache.clean', 'cache.cleaned', 'lock.release', 'lock.released',
    ];

    public static function begin(): void
    {
        if (!defined('RNL_TRACE_MUTATIONS') || RNL_TRACE_MUTATIONS !== true || self::$trace !== null) { return; }
        try {
            global $wpdb;
            $site = get_current_blog_id();
            if (defined('RNL_TRACE_SITE_ID') && (int) RNL_TRACE_SITE_ID !== $site) { return; }
            $now = hrtime(true);
            self::$trace = ['trace_id' => bin2hex(random_bytes(8)), 'site_id' => $site, 'pid' => getmypid(),
                'phase' => 'begin', 'object_id' => 0, 'transaction' => false,
                'started' => $now, 'last' => $now, 'failed' => false,
                'queries_before' => (int) ($wpdb->num_queries ?? 0)];
            if (!self::$registered) {
                register_shutdown_function([self::class, 'shutdown']);
                self::$registered = true;
            }
            self::emit('progress');
        } catch (\Throwable) { self::$trace = null; }
    }

    public static function phase(string $phase, int $id = 0): void
    {
        if (self::$trace === null || !in_array($phase, self::PHASES, true)) { return; }
        self::$trace['phase'] = $phase;
        self::$trace['object_id'] = max(0, $id);
        self::emit('progress');
    }

    public static function transaction(bool $active): void
    {
        if (self::$trace !== null) { self::$trace['transaction'] = $active; }
    }

    public static function failed(): void
    {
        if (self::$trace === null || self::$trace['failed']) { return; }
        self::$trace['failed'] = true;
        self::emit('failed');
    }

    public static function finish(): void
    {
        if (self::$trace === null) { return; }
        self::emit(self::$trace['failed'] ? 'finished_with_error' : 'finished');
        self::$trace = null;
    }

    /** Best effort: a killed worker cannot write a shutdown record. */
    public static function shutdown(): void
    {
        if (self::$trace === null) { return; }
        $error = error_get_last();
        $fatal = $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        self::emit($fatal ? 'fatal' : 'interrupted', $fatal ? (int) $error['type'] : 0);
        self::$trace = null;
    }

    private static function emit(string $event, int $errorType = 0): void
    {
        if (self::$trace === null) { return; }
        try {
            global $wpdb, $wp_object_cache;
            $connection = 0;
            try {
                $dbh = $wpdb->dbh ?? null;
                if ($dbh instanceof \mysqli) { $connection = $dbh->thread_id; }
            } catch (\Throwable) { /* Connection may already be closed. Do not reconnect. */ }
            $now = hrtime(true);
            $record = [
                'trace_id' => self::$trace['trace_id'], 'site_id' => self::$trace['site_id'],
                'pid' => self::$trace['pid'], 'db_connection' => $connection,
                'event' => $event, 'phase' => self::$trace['phase'], 'object_id' => self::$trace['object_id'],
                'transaction' => self::$trace['transaction'],
                'elapsed_ms' => round(($now - self::$trace['started']) / 1000000, 1),
                'since_previous_ms' => round(($now - self::$trace['last']) / 1000000, 1),
                'error_type' => $errorType,
                'db_queries' => max(0, (int) ($wpdb->num_queries ?? 0) - self::$trace['queries_before']),
                'object_cache' => !function_exists('wp_using_ext_object_cache') ? 'unknown'
                    : (wp_using_ext_object_cache() ? 'external'
                        : (is_object($wp_object_cache) && get_class($wp_object_cache) === \WP_Object_Cache::class ? 'runtime' : 'nonstandard')),
                'cache_addition_suspended' => function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition(),
            ];
            self::$trace['last'] = $now;
            error_log('RNL-MUTATION ' . json_encode($record, JSON_THROW_ON_ERROR));
        } catch (\Throwable) { /* Diagnostics must never change the write outcome. */ }
    }
}
