<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore;
use RegiNor\Lite\Infrastructure\RegistrationState;
use function RegiNor\Lite\translate as __;

final class RegistrationStatusControl
{
    public static function labels(): array
    {
        return ['external' => __('Egen påmeldingslenke', 'reginor-lite'), 'dropin' => __('Kun drop-in', 'reginor-lite'), 'automatic' => __('Automatisk fra LetsReg', 'reginor-lite'), 'available' => __('Manuelt: påmelding tilgjengelig', 'reginor-lite'),
            'later' => __('Manuelt: åpner senere', 'reginor-lite'), 'full' => __('Manuelt: fullt', 'reginor-lite'),
            'waiting' => __('Manuelt: venteliste', 'reginor-lite'), 'closed' => __('Manuelt: stengt', 'reginor-lite'),
            'cancelled' => __('Manuelt: avlyst', 'reginor-lite'), 'unknown' => __('Må avklares', 'reginor-lite')];
    }
    public static function boot(): void
    {
        add_action('wp_ajax_rnl_registration_status', static function (): void {
            nocache_headers();
            try { wp_send_json_success(self::dispatch(wp_unslash($_POST))); }
            catch (\Throwable $e) { wp_send_json_error(['message' => $e->getMessage()], in_array($e->getCode(), [403, 404, 409], true) ? $e->getCode() : 400); }
        });
        add_action('admin_post_rnl_registration_status', static function (): void {
            try {
                self::dispatch(wp_unslash($_POST));
                wp_safe_redirect(add_query_arg(['page' => 'reginor-lite', 'period' => (int) $_POST['period'], 'step' => 'courses'], admin_url('admin.php'))); exit;
            } catch (\Throwable $e) { wp_die(esc_html($e->getMessage()), '', ['response' => 400, 'back_link' => true]); }
        });
    }
    public static function dispatch(array $input): array
    {
        if (!current_user_can('rnl_edit_groups')) { throw new \RuntimeException(__('Du har ikke tilgang til å endre kursstatus.', 'reginor-lite'), 403); }
        if (!is_string($input['nonce'] ?? null) || !wp_verify_nonce($input['nonce'], 'rnl_registration_status')) {
            throw new \RuntimeException(__('Siden er utløpt. Last den på nytt før du endrer status.', 'reginor-lite'), 403);
        }
        $repo = new CourseRepository(); $periodId = CourseActions::integer($input['period'] ?? '');
        $period = $repo->get($periodId, 'period')['data'];
        $operation = CourseActions::scalar($input, 'operation');
        if ($operation === 'read') {
            $groups = $repo->groups($periodId);
            // The open administration screen also advances the bounded queue when WP-Cron is delayed.
            LetsRegAvailabilityStore::runDue(array_keys($groups));
            $rows = [];
            foreach ($repo->groups($periodId) as $id => $state) { $rows[] = self::row($id, $state, $period); }
            return ['rows' => $rows];
        }
        if ($operation !== 'save') { throw new \InvalidArgumentException(__('Ukjent statushandling.', 'reginor-lite')); }
        $id = CourseActions::integer($input['id'] ?? ''); $state = $repo->get($id, 'group');
        if ($state['data']['period_id'] !== $periodId) { throw new \RuntimeException(__('Kurset tilhører en annen periode.', 'reginor-lite'), 403); }
        $state = $repo->saveRegistrationStatus($id, CourseActions::integer($input['version'] ?? ''), CourseActions::scalar($input, 'status'));
        return ['rows' => [self::row($id, $state, $period)]];
    }
    private static function row(int $id, array $state, array $period): array
    {
        $course = $state['data']; $now = new \DateTimeImmutable();
        $effective = RegistrationState::resolve($period, $course, $now)['status'];
        $labels = ['external' => __('Påmelding via egen lenke', 'reginor-lite'), 'dropin' => __('Kun drop-in – ingen forhåndspåmelding', 'reginor-lite'), 'available' => __('Påmelding tilgjengelig', 'reginor-lite'), 'later' => __('Åpner senere', 'reginor-lite'), 'full' => __('Fullt', 'reginor-lite'),
            'waiting' => __('Venteliste', 'reginor-lite'), 'closed' => __('Stengt', 'reginor-lite'), 'cancelled' => __('Avlyst', 'reginor-lite'), 'ended' => __('Avsluttet', 'reginor-lite'), 'unknown' => __('Må avklares', 'reginor-lite')];
        $automatic = $course['registration_status'] === 'automatic';
        $observation = $automatic ? LetsRegAvailabilityStore::inspect($course, $now->getTimestamp()) : null;
        $description = ($automatic ? __('Automatisk', 'reginor-lite') : __('Manuelt valg', 'reginor-lite')) . ' · ' . ($labels[$effective] ?? $effective);
        if ($automatic) {
            $description .= !empty($observation['fresh']) ? ' · ' . __('Hentet ', 'reginor-lite') . wp_date('H:i:s', $observation['checked_at'])
                : ' · ' . LetsRegAvailabilityStore::pendingExplanation($course);
        }
        if (get_post_status($id) !== 'publish') { $description .= ' · ' . __('Kladd – ikke publisert', 'reginor-lite'); }
        return ['id' => $id, 'version' => $state['version'], 'status' => $course['registration_status'], 'description' => $description];
    }
    public static function render(int $id, array $state, array $period): void
    {
        $row = self::row($id, $state, $period); $field = 'rnl-registration-' . $id;
        echo '<form class="rnl-status-control" data-rnl-status-row data-url="' . esc_url(admin_url('admin-ajax.php')) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        foreach (['action' => 'rnl_registration_status', 'operation' => 'save', 'nonce' => wp_create_nonce('rnl_registration_status'), 'period' => $state['data']['period_id'], 'id' => $id, 'version' => $state['version']] as $key => $value) {
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">';
        }
        echo '<label class="screen-reader-text" for="' . esc_attr($field) . '">' . esc_html(sprintf(/* translators: %s: course title. */ __('Påmeldingsstatus for %s', 'reginor-lite'), $state['data']['title'])) . '</label><select id="' . esc_attr($field) . '" name="status" aria-describedby="' . esc_attr($field . '-info') . '">';
        foreach (self::labels() as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($state['data']['registration_status'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><button type="submit" data-status-save class="rnl-button rnl-button-secondary">' . esc_html(__('Lagre status', 'reginor-lite')) . '</button><span class="rnl-status-description" id="' . esc_attr($field . '-info') . '" data-status-description>' . esc_html($row['description']) . '</span><span data-status-message role="status" aria-live="polite"></span></form>';
        if (!empty($state['data']['letsreg_mapping'])) {
            echo '<a class="rnl-small" href="' . esc_url(add_query_arg(['page' => 'rnl-capacity', 'group' => $id], admin_url('admin.php'))) . '">' . esc_html(__('Se plasser per kategori', 'reginor-lite')) . '</a>';
        }
    }
}
