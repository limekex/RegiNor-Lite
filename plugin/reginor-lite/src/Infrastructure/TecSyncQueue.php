<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;

/** Schedule calendar writes outside the visitor/editor request. No external calls. */
final class TecSyncQueue
{
    public const PERIODIC = 'rnl_tec_sync_periodic';
    public const SOON = 'rnl_tec_sync_soon';
    public const STATUS = 'rnl_tec_sync_status';

    public static function boot(): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            // Other plugins may inspect cron before init; keep the interval available
            // without triggering WordPress' just-in-time translation loader early.
            $schedules['rnl_tec_five_minutes'] = ['interval' => 300, 'display' => did_action('init') ? __('RegiNor kalenderkontroll', 'reginor-lite') : 'RegiNor kalenderkontroll'];
            return $schedules;
        });
        add_action('init', [self::class, 'ensure'], 40);
        add_action(self::PERIODIC, [TecBridge::class, 'sync']);
        add_action(self::SOON, [TecBridge::class, 'sync']);
    }

    public static function ensure(): void
    {
        if (!TecBridge::available()) { return; }
        if (wp_get_scheduled_event(self::PERIODIC)) { self::recoverScheduleError(self::PERIODIC); return; }
        self::scheduled(wp_schedule_event(time() + 30, 'rnl_tec_five_minutes', self::PERIODIC, [], true), self::PERIODIC);
    }

    public static function request(): void
    {
        if (!TecBridge::available()) { return; }
        if (wp_get_scheduled_event(self::SOON)) { self::recoverScheduleError(self::SOON); return; }
        $result = wp_schedule_single_event(time() + 5, self::SOON, [], true);
        if (self::scheduled($result, self::SOON) && (((array) get_option(self::STATUS, []))['state'] ?? '') !== 'running') {
            update_option(self::STATUS, ['state' => 'queued', 'queued_at' => time()], false);
        }
    }

    private static function scheduled(mixed $result, string $hook): bool
    {
        // Another request may have scheduled the same job after our initial read.
        // Only a verifiable existing job turns an error into a successful enqueue.
        if ($result === true || wp_get_scheduled_event($hook)) {
            self::recoverScheduleError($hook);
            return true;
        }
        $code = is_wp_error($result) ? $result->get_error_code() : 'no_result';
        $code = in_array($code, ['could_not_set', 'pre_schedule_event_false', 'schedule_event_false', 'duplicate_event', 'invalid_schedule', 'invalid_timestamp', 'no_result'], true) ? $code : 'provider_error';
        $reason = match ($code) {
            'could_not_set' => __('WordPress kunne ikke lagre cron-køen. Dette kan skyldes databasefeil eller samtidige oppdateringer; kontroller feilloggen ved dette tidspunktet.', 'reginor-lite'),
            'pre_schedule_event_false', 'schedule_event_false' => __('En utvidelse eller et filter avviste køleggingen.', 'reginor-lite'),
            'duplicate_event' => __('WordPress meldte at jobben allerede finnes, men RegiNor kunne ikke bekrefte den i køen.', 'reginor-lite'),
            'invalid_schedule' => __('WordPress fant ikke kalenderjobbens gjentakelsesintervall.', 'reginor-lite'),
            default => __('WordPress eller en utvidelse avviste køleggingen. Administrator må undersøke feilloggen.', 'reginor-lite'),
        };
        $message = sprintf(/* translators: 1: explanation, 2: fixed cron hook, 3: safe error code. */ __('Kalenderkontrollen kunne ikke legges i WordPress-cron (ikke Action Scheduler). %1$s Jobb: %2$s. Kontrollkode: RNL-TEC-SCHEDULE/%3$s. Denne meldingen gjelder kalenderkøen, ikke om kursendringen ble lagret.', 'reginor-lite'), $reason, $hook, $code);
        update_option(self::STATUS, ['state' => 'error', 'finished_at' => time(), 'message' => $message, 'schedule_hook' => $hook, 'schedule_code' => $code], false);
        return false;
    }

    private static function recoverScheduleError(string $hook): void
    {
        $state = (array) get_option(self::STATUS, []);
        if (($state['schedule_hook'] ?? '') === $hook && !empty($state['schedule_code'])) {
            update_option(self::STATUS, ['state' => 'queued', 'queued_at' => time()], false);
        }
    }

    public static function started(): void
    {
        update_option(self::STATUS, ['state' => 'running', 'started_at' => time(), 'finished_at' => 0, 'message' => ''], false);
    }

    public static function finished(?string $error): void
    {
        $previous = (array) get_option(self::STATUS, []);
        update_option(self::STATUS, ['state' => $error ? 'error' : 'done', 'started_at' => (int) ($previous['started_at'] ?? 0), 'finished_at' => time(), 'message' => $error ?? ''], false);
    }

    public static function message(): ?string
    {
        $state = (array) get_option(self::STATUS, []);
        if (($state['state'] ?? '') === 'error') { return (string) ($state['message'] ?? ''); }
        if (($state['state'] ?? '') === 'queued') {
            return time() - (int) ($state['queued_at'] ?? 0) > 600
                ? __('Kalenderoppdateringen har ventet i over ti minutter. Administrator må kontrollere at automatiske jobber kjører på nettstedet. Kursperioden kan fortsatt redigeres. Kontrollkode: RNL-TEC-DELAYED.', 'reginor-lite')
                : __('Kalenderoppdateringen ligger i kø og utføres i bakgrunnen. Du kan fortsette å redigere kursperioden.', 'reginor-lite');
        }
        if (($state['state'] ?? '') === 'running') {
            return time() - (int) ($state['started_at'] ?? 0) > 180
                ? __('Siste kalenderkontroll er ikke registrert som fullført. Kursperioden kan fortsatt redigeres. Administrator må kontrollere automatiske jobber og serverens feillogg. Kontrollkode: RNL-TEC-INCOMPLETE.', 'reginor-lite')
                : __('Kalenderkontrollen er startet i bakgrunnen. Du kan fortsatt redigere kursperioden.', 'reginor-lite');
        }
        return null;
    }

    public static function stop(): void
    {
        wp_clear_scheduled_hook(self::PERIODIC);
        wp_clear_scheduled_hook(self::SOON);
    }
}
