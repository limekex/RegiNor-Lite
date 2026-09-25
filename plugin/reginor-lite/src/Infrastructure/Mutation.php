<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use RuntimeException;

/** Serializes all repository writers, including writes in other periods sharing resources. */
final class Mutation
{
    private static int $depth = 0;
    private static array $touched = [];

    public static function active(): bool { return self::$depth > 0; }

    public static function touch(int $id): void { self::$touched[$id] = true; }

    public static function run(callable $operation, bool $transaction = false): mixed
    {
        if (self::$depth > 0) {
            return $operation();
        }
        MutationTrace::begin();
        try {
            return self::execute($operation, $transaction);
        } catch (\Throwable $error) {
            MutationTrace::failed();
            throw $error;
        } finally {
            MutationTrace::finish();
        }
    }

    private static function execute(callable $operation, bool $transaction): mixed
    {
        global $wpdb, $wp_object_cache;
        $name = DatabaseLock::name();
        MutationTrace::phase('lock.acquire');
        if (!DatabaseLock::acquire($name, 3)) {
            throw new RuntimeException(__('En annen kursendring pågår. Prøv igjen.', 'reginor-lite'), 409);
        }
        MutationTrace::phase('lock.acquired');
        self::$depth++;
        $started = false;
        $runtimeCache = null;
        $cacheSuspended = wp_suspend_cache_addition();
        try {
            if ($transaction) {
                MutationTrace::phase('transaction.check');
                foreach ([$wpdb->posts, $wpdb->postmeta] as $table) {
                    $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                    if (strtoupper((string) $engine) !== 'INNODB') {
                        throw new RuntimeException(__('Samlet publisering krever InnoDB-lagring. Kontakt administrator.', 'reginor-lite'));
                    }
                }
                MutationTrace::phase('transaction.start');
                if ($wpdb->query('START TRANSACTION') === false) {
                    throw new RuntimeException(__('Kunne ikke starte samlet lagring.', 'reginor-lite'));
                }
                $started = true;
                MutationTrace::transaction(true);
                // Core's cache is private to this request. Disabling its priming makes
                // every repeated option/meta read in save hooks hit the database again.
                // Retain the old policy for external/nonstandard cache implementations.
                if (!wp_using_ext_object_cache() && is_object($wp_object_cache)
                    && get_class($wp_object_cache) === \WP_Object_Cache::class) {
                    $runtimeCache = $wp_object_cache;
                } else {
                    wp_suspend_cache_addition(true);
                }
            }
            MutationTrace::phase('operation');
            $result = $operation();
            if ($started) {
                MutationTrace::phase('commit');
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException(__('Kunne ikke fullføre samlet lagring.', 'reginor-lite'));
                }
                MutationTrace::transaction(false);
                MutationTrace::phase('committed');
            }
            return $result;
        } catch (\Throwable $error) {
            MutationTrace::failed();
            if ($started) {
                MutationTrace::phase('rollback');
                $rolledBack = $wpdb->query('ROLLBACK');
                if ($rolledBack !== false) {
                    MutationTrace::transaction(false);
                    MutationTrace::phase('rolled_back');
                }
            }
            throw $error;
        } finally {
            try {
                if ($runtimeCache !== null) {
                    // Discard transaction-local reads, including options/query results
                    // after rollback. Call only the captured core in-memory cache;
                    // never flush Redis, Memcached or page caches shared with other requests.
                    MutationTrace::phase('cache.runtime_clear');
                    $runtimeCache->flush();
                }
                foreach (array_keys(self::$touched) as $id) {
                    MutationTrace::phase('cache.clean', $id);
                    clean_post_cache($id);
                    wp_cache_delete($id, 'post_meta');
                    MutationTrace::phase('cache.cleaned', $id);
                }
            } finally {
                self::$touched = [];
                wp_suspend_cache_addition($cacheSuspended);
                self::$depth--;
                MutationTrace::phase('lock.release');
                DatabaseLock::release($name);
                MutationTrace::phase('lock.released');
            }
        }
    }
}
