<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

use RegiNor\Lite\Domain\Publication\LetsRegAvailability;
use function RegiNor\Lite\translate as __;

/** Account-bound observations, read without network I/O by every public surface. */
final class LetsRegAvailabilityStore
{
    public const HOOK = 'rnl_letsreg_availability_tick';
    public const TTL = 900;
    private static function key(array $identity, int $event): string { return 'rnl_av_' . hash('sha256', $identity['fingerprint'] . ':' . $event); }
    public static function capture(array $identity, int $event, array $observation): void
    {
        $key = self::key($identity, $event);
        if (empty($observation['valid']) || LetsRegAvailability::bounds($observation['window'] ?? [], 'UTC') === null) {
            self::failure($identity, $event); return;
        }
        $value = ['checked_at' => time(), 'expires_at' => time() + self::TTL, 'observation' => $observation];
        // This is last known provider state, not disposable cache. Never autoload it.
        if (!update_option($key, $value, false) && get_option($key) !== $value) { throw new \RuntimeException(__('LetsReg-statusen kunne ikke lagres.', 'reginor-lite'), 503); }
        if (!set_transient($key, $value, 86400) && get_transient($key) !== $value) { throw new \RuntimeException(__('LetsReg-statusen kunne ikke lagres.', 'reginor-lite'), 503); }
    }
    public static function failure(array $identity, int $event): void
    {
        LetsRegChanges::failure($identity, $event);
        $key = self::key($identity, $event); $old = get_transient($key) ?: get_option($key, []);
        $value = array_replace(is_array($old) ? $old : [], ['attempt_at' => time(), 'expires_at' => 0]);
        update_option($key, $value, false);
        set_transient($key, $value, 86400);
    }
    public static function inspect(array $course, int $now): array
    {
        $identity = LetsRegConnection::identity(); $mapping = $course['letsreg_mapping'] ?? null;
        $result = ['checked_at' => null, 'expires_at' => null, 'observation' => null, 'reason' => 'unavailable'];
        if (!$identity || !$mapping || $identity['affiliate_id'] !== $mapping['affiliate_id'] || $identity['organizer_id'] !== $mapping['organizer_id']) { return $result; }
        $stored = get_transient(self::key($identity, $mapping['event_id'])) ?: get_option(self::key($identity, $mapping['event_id']), null);
        $result['checked_at'] = is_array($stored) ? $stored['checked_at'] : null;
        $poll = get_option('rnl_letsreg_poll', []); $manual = get_option(LetsRegConnection::OPTION, []);
        foreach ([$poll, $manual] as $state) {
            if (($state['fingerprint'] ?? '') === $identity['fingerprint'] && in_array($state['error'] ?? null, ['authentication', 'permission', 'organization_mismatch'], true)) { $result['reason'] = $state['error']; }
        }
        if (!is_array($stored)) { return $result; }
        if (empty($stored['observation']) || ($stored['checked_at'] ?? PHP_INT_MAX) > $now) { return $result; }
        $fresh = ($stored['expires_at'] ?? 0) > $now && $result['reason'] === 'unavailable';
        return array_replace($result, $stored, ['fresh' => $fresh, 'reason' => $result['reason'] === 'unavailable' ? ($fresh ? '' : 'stale') : $result['reason']]);
    }
    public static function pendingExplanation(array $course): string
    {
        $reason = self::inspect($course, time())['reason'] ?? '';
        return match ($reason) {
            'authentication' => __('LetsReg avviste innloggingen. Administrator må kontrollere API-tilgangen under LetsReg-tilkobling.', 'reginor-lite'),
            'permission' => __('LetsReg avviste tilgangen. Administrator må kontrollere API-rettighetene under LetsReg-tilkobling.', 'reginor-lite'),
            'organization_mismatch' => __('API-oppsettet samsvarer ikke med arrangøren. Administrator må kontrollere LetsReg-tilkoblingen.', 'reginor-lite'),
            'stale' => __('Siste bekreftede status og kapasitet beholdes mens vi venter på en ny LetsReg-kontroll.', 'reginor-lite'),
            default => __('Venter på gyldig LetsReg-kontroll. Periodens salgsdatoer brukes ikke i automatisk modus.', 'reginor-lite'),
        };
    }
    public static function view(array $course, \DateTimeImmutable $now): array
    {
        $stored = self::inspect($course, $now->getTimestamp());
        $fresh = $stored['fresh'] ?? false;
        $view = LetsRegAvailability::project($stored['observation'], $course['letsreg_mapping'] ?? ['categories' => []], $course['timezone'], $now);
        if (!$fresh) { $view['capacity_known'] = false; }
        return $view + ['checked_at' => $stored['checked_at'], 'expires_at' => $fresh ? $stored['expires_at'] : null,
            'categories' => \RegiNor\Lite\Domain\Capacity\LetsRegCategoryCapacity::project($stored['observation'], $course['letsreg_mapping'] ?? ['categories' => []], $course['timezone'], $now)];
    }
    public static function boot(): void
    {
        add_action('admin_post_rnl_refresh_availability', static function (): void {
            if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
            check_admin_referer('rnl_refresh_availability');
            $id = \RegiNor\Lite\Admin\CourseActions::integer($_POST['id'] ?? '');
            $ok = false;
            try {
                $course = (new CourseRepository())->get($id, 'group')['data'];
                $mapping = $course['letsreg_mapping'] ?? null; $identity = LetsRegConnection::identity();
                if ($mapping && $identity && $mapping['affiliate_id'] === $identity['affiliate_id'] && $mapping['organizer_id'] === $identity['organizer_id']) {
                    $ok = LetsRegConnection::check($mapping['event_id'], null, true)['state'] === 'verified';
                }
            } catch (\Throwable) { /* Details remain on the connection status page; no provider data in URL. */ }
            wp_safe_redirect(add_query_arg(['page' => 'reginor-lite', 'group' => $id, 'rnl_status_check' => $ok ? 'ok' : 'error'], admin_url('admin.php')));
            exit;
        });
        add_action('init', static function (): void {
            if (!wp_next_scheduled(self::HOOK)) { wp_schedule_event(time() + 60, 'rnl_minute', self::HOOK); }
        }, 31);
        add_action(self::HOOK, [self::class, 'runDue']);
    }
    public static function runDue(?array $onlyGroups = null): void
    {
        if ($onlyGroups === []) { return; }
        $identity = LetsRegConnection::identity(); if (!$identity) { return; }
        $events = [];
        foreach (get_posts(['post_type' => 'rnl_group', 'post_status' => ['draft', 'publish'], 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true] + ($onlyGroups !== null ? ['post__in' => $onlyGroups] : [])) as $id) {
            $state = get_post_meta($id, ContentTypes::META, true); $course = $state['data'] ?? [];
            // Every linked course is checked for editorial changes, including manual registration status.
            if (empty($course['letsreg_mapping'])) { continue; }
            $mapping = $course['letsreg_mapping'];
            if ($mapping['affiliate_id'] !== $identity['affiliate_id'] || $mapping['organizer_id'] !== $identity['organizer_id']) { continue; }
            $stored = get_transient(self::key($identity, $mapping['event_id']));
            if (($stored['checked_at'] ?? 0) + 600 > time() && ($stored['expires_at'] ?? 0) > time() && !empty($stored['observation'])) { continue; }
            $events[$mapping['event_id']] = $stored['attempt_at'] ?? $stored['checked_at'] ?? 0;
        }
        asort($events); // Oldest observation first; shared events are fetched once.
        LetsRegConnection::pollBatch(array_slice(array_keys($events), 0, 3));
    }
    public static function summary(array $course): void
    {
        if (($course['registration_status'] ?? '') !== 'automatic') { return; }
        $view = self::view($course, new \DateTimeImmutable());
        echo '<p class="rnl-help">' . esc_html($view['expires_at']
            ? __('Påmeldingsstatus følger siste LetsReg-kontroll. Periodens salgsdatoer brukes ikke. Egne påmeldingsdatoer på kurset og kursets slutt gjelder fortsatt.', 'reginor-lite')
            : self::pendingExplanation($course)) . '</p>';
        if ($view['checked_at']) { echo '<p class="rnl-small">' . esc_html(__('Sist hentet fra LetsReg: ', 'reginor-lite') . wp_date('d.m.Y H:i:s', $view['checked_at'])) . '</p>'; }
        if (isset($_GET['rnl_status_check'])) {
            echo '<p role="status">' . esc_html($_GET['rnl_status_check'] === 'ok'
                ? __('LetsReg-kontrollen er oppdatert.', 'reginor-lite')
                : __('Kontrollen kunne ikke fullføres. Se LetsReg-tilkobling for tilgangsfeil eller ventetid, og kontroller at kurset er koblet til riktig konto.', 'reginor-lite')) . '</p>';
        }
        if (current_user_can('manage_options') && isset($_GET['group']) && is_scalar($_GET['group'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rnl_refresh_availability"><input type="hidden" name="id" value="' . (int) $_GET['group'] . '">';
            wp_nonce_field('rnl_refresh_availability');
            echo '<button class="rnl-button rnl-button-secondary" type="submit">' . esc_html(__('Kontroller påmeldingsstatus nå', 'reginor-lite')) . '</button></form>';
        }
    }
}
