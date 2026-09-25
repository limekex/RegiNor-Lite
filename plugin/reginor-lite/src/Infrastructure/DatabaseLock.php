<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Connection-owned locks: a timeout is different from a failed database query. */
final class DatabaseLock
{
    public static function name(bool $calendar = false): string
    {
        global $wpdb;
        return ($calendar ? 'rnl:tec:' : 'rnl:') . md5(DB_NAME . ':' . $wpdb->prefix);
    }

    public static function acquire(string $name, int $seconds): bool
    {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, $seconds));
        if ((string) $result === '1') { return true; }
        if ((string) $result === '0' && $wpdb->last_error === '') { return false; }
        // Raw SQL/errors can contain installation details; WordPress logs them separately.
        throw new \RuntimeException(__('Databasen kunne ikke sikre lagringen. Endringen er ikke startet. Last siden på nytt og prøv igjen. Hvis feilen fortsetter, må administrator kontrollere databasetilkoblingen og feilloggen. Kontrollkode: RNL-DB-LOCK.', 'reginor-lite'), 503);
    }

    public static function release(string $name): void
    {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}
