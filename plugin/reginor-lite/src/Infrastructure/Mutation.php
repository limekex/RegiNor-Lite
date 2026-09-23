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
        global $wpdb;
        $name = 'rnl:' . md5(DB_NAME . ':' . $wpdb->prefix);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $name)) !== 1) {
            throw new RuntimeException(__('En annen kursendring pågår. Prøv igjen.', 'reginor-lite'), 409);
        }
        self::$depth++;
        $started = false;
        $cacheSuspended = wp_suspend_cache_addition();
        try {
            if ($transaction) {
                foreach ([$wpdb->posts, $wpdb->postmeta] as $table) {
                    $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                    if (strtoupper((string) $engine) !== 'INNODB') {
                        throw new RuntimeException(__('Samlet publisering krever InnoDB-lagring. Kontakt administrator.', 'reginor-lite'));
                    }
                }
                if ($wpdb->query('START TRANSACTION') === false) {
                    throw new RuntimeException(__('Kunne ikke starte samlet lagring.', 'reginor-lite'));
                }
                $started = true;
                wp_suspend_cache_addition(true);
            }
            $result = $operation();
            if ($started && $wpdb->query('COMMIT') === false) {
                throw new RuntimeException(__('Kunne ikke fullføre samlet lagring.', 'reginor-lite'));
            }
            return $result;
        } catch (\Throwable $error) {
            if ($started) { $wpdb->query('ROLLBACK'); }
            throw $error;
        } finally {
            foreach (array_keys(self::$touched) as $id) {
                clean_post_cache($id);
                wp_cache_delete($id, 'post_meta');
            }
            self::$touched = [];
            wp_suspend_cache_addition($cacheSuspended);
            self::$depth--;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
