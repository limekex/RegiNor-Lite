<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use RegiNor\Lite\Domain\Capacity\CapacityView;
use RegiNor\Lite\Infrastructure\CapacityStore;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\DemoCapacityAdapter;

final class CapacityPage
{
    private static string $error = '';

    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('Kapasitet', 'reginor-lite'), __('Kapasitet', 'reginor-lite'), 'rnl_edit_groups', 'rnl-capacity', [self::class, 'render']);
        add_action('load-' . $hook, static function (): void {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
            if (!current_user_can('rnl_edit_groups')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
            check_admin_referer('rnl_capacity');
            try {
                self::command(wp_unslash($_POST));
                wp_safe_redirect(add_query_arg(['page' => 'rnl-capacity', 'group' => self::id($_POST['group'] ?? ''), 'updated' => 1], admin_url('admin.php')), 303); exit;
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                self::$error = $error->getMessage();
                status_header(in_array($error->getCode(), [403, 404, 409, 429], true) ? $error->getCode() : 400);
            }
        });
    }

    private static function id(mixed $raw, bool $zero = false): int
    {
        if (!is_string($raw) || !ctype_digit($raw) || strlen($raw) > 10 || (!$zero && (int) $raw < 1)) { throw new \InvalidArgumentException(__('Velg et kurs fra listen.', 'reginor-lite')); }
        return (int) $raw;
    }

    public static function command(array $post): void
    {
        $id = self::id($post['group'] ?? null); $store = new CapacityStore();
        if (($post['command'] ?? '') === 'check_capacity') { $store->request($id); return; }
        if (($post['command'] ?? '') !== 'configure_capacity' || !is_string($post['scenario'] ?? null)) { throw new \InvalidArgumentException(__('Velg en av handlingene på siden.', 'reginor-lite')); }
        $store->configure($id, self::id($post['version'] ?? null, true), $post['scenario']);
    }

    private static function time(?int $stamp): string
    {
        return $stamp === null ? __('Ikke kontrollert ennå', 'reginor-lite') : wp_date('d.m.Y H:i:s', $stamp);
    }

    private static function form(int $id, string $command): void
    {
        echo '<form method="post" action="' . esc_url(add_query_arg(['page' => 'rnl-capacity', 'group' => $id], admin_url('admin.php'))) . '">';
        wp_nonce_field('rnl_capacity');
        echo '<input type="hidden" name="group" value="' . $id . '"><input type="hidden" name="command" value="' . esc_attr($command) . '">';
    }

    public static function render(): void
    {
        if (!current_user_can('rnl_edit_groups')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
        $repo = new CourseRepository(); $groups = $repo->listing('group'); $periods = $repo->listing('period');
        $raw = $_POST['group'] ?? $_GET['group'] ?? '0';
        $id = is_string($raw) && ctype_digit($raw) ? (int) $raw : 0;
        echo ('<div class="wrap rnl-ui rnl-admin"><span class="rnl-eyebrow">' . esc_html(__('RegiNor Lite · Kapasitet', 'reginor-lite')) . '</span><h1>' . esc_html(__('Kontroller kapasitet', 'reginor-lite')) . '</h1><p>' . esc_html(__('Velg et kurs for å se siste kontroll av ledige plasser hos LetsReg.', 'reginor-lite')) . '</p><p class="rnl-notice">' . esc_html(__('Kapasitet vises per koblet kategori. LetsReg kontrollerer endelig tilgjengelighet ved påmelding.', 'reginor-lite')) . '</p>');
        if (self::$error !== '') { echo '<p class="rnl-notice" role="alert">' . esc_html(self::$error) . '</p>'; }
        elseif (isset($_GET['updated'])) { echo ('<p class="rnl-notice" role="status">' . esc_html(__('Valget er lagret. Bestilte kontroller vises når den automatiske kontrollen har kjørt. Oppdater siden for å se resultatet.', 'reginor-lite')) . '</p>'); }
        if (!$groups) { echo ('<section class="rnl-panel"><h2>' . esc_html(__('Opprett et kurs først', 'reginor-lite')) . '</h2><p>' . esc_html(__('Legg til en kurs i en kursperiode før du prøver kapasitet.', 'reginor-lite')) . '</p><a class="rnl-button" href="') . esc_url(admin_url('admin.php?page=reginor-lite')) . ('">' . esc_html(__('Gå til kursperioder →', 'reginor-lite')) . '</a></section></div>'); return; }
        echo ('<form method="get"><input type="hidden" name="page" value="rnl-capacity"><label for="rnl-capacity-group">' . esc_html(__('Hvilket kurs vil du kontrollere?', 'reginor-lite')) . '</label><select id="rnl-capacity-group" name="group"><option value="0">' . esc_html(__('Velg kurs', 'reginor-lite')) . '</option>');
        foreach ($groups as $groupId => $group) {
            $g = $group['data']; $period = $periods[$g['period_id']]['data']['title'] ?? __('Kursperiode', 'reginor-lite');
            $day = [1 => __('Mandag', 'reginor-lite'), __('Tirsdag', 'reginor-lite'), __('Onsdag', 'reginor-lite'), __('Torsdag', 'reginor-lite'), __('Fredag', 'reginor-lite'), __('Lørdag', 'reginor-lite'), __('Søndag', 'reginor-lite')][$g['weekday']];
            echo '<option value="' . (int) $groupId . '"' . selected($id, $groupId, false) . '>' . esc_html($period . ' · ' . $g['title'] . ' · ' . $day . ' ' . $g['start_time']) . '</option>';
        }
        echo ('</select><p><button class="rnl-button rnl-button-secondary">' . esc_html(__('Vis valgt kurs', 'reginor-lite')) . '</button></p></form>');
        if (!$id || !isset($groups[$id])) { echo '</div>'; return; }
        if (!empty($groups[$id]['data']['letsreg_mapping'])) {
            LetsRegCapacityPanel::render($groups[$id]['data']);
            \RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore::summary($groups[$id]['data']);
            echo '<p><a class="rnl-button rnl-button-secondary" href="' . esc_url(add_query_arg(['page' => 'reginor-lite', 'group' => $id], admin_url('admin.php'))) . '">' . esc_html(__('Åpne kursoppsettet', 'reginor-lite')) . '</a></p></div>'; return;
        }
        echo '<h2>' . esc_html(__('Demonstrasjon', 'reginor-lite')) . '</h2><p>' . esc_html(__('Dette kurset er ikke koblet til LetsReg. Prøvedata nedenfor vises bare her i administrasjonen.', 'reginor-lite')) . '</p>';
        try { $state = (new CapacityStore())->inspect($id); } catch (\RuntimeException $error) { echo '<p role="alert">' . esc_html($error->getMessage()) . '</p></div>'; return; }
        if ($state) {
            $view = CapacityView::project($state['snapshot'], time(), $state['error'], false, true);
            $status = ['unknown' => __('Kapasiteten er ukjent', 'reginor-lite'), 'fresh' => __('Opplysningene er ferske', 'reginor-lite'), 'full' => __('Kurset er fullt', 'reginor-lite'),
                'roles' => __('Ulik tilgjengelighet for fører og følger', 'reginor-lite'), 'expired' => __('Opplysningene har utløpt', 'reginor-lite'), 'error' => __('Kontrollen lyktes ikke', 'reginor-lite')];
            echo '<section class="rnl-panel"><div data-rnl-capacity-preview data-rnl-now="' . time() . '" data-rnl-expiry="' . (int) ($view['expires_at'] ?? 0) . '"><h2>' . esc_html($status[$view['state']]) . ('</h2><p><strong>' . esc_html(__('Eksempel på tekst:', 'reginor-lite')) . '</strong> ') . esc_html($view['label']) . ('</p></div><p>' . esc_html(__('Eksakte antall vises ikke. Påmeldingslenken endres ikke av demonstrasjonen.', 'reginor-lite')) . '</p><dl><dt>' . esc_html(__('Siste forsøk', 'reginor-lite')) . '</dt><dd>') . esc_html(self::time($state['last_attempt_at'])) . ('</dd><dt>' . esc_html(__('Siste vellykkede kontroll', 'reginor-lite')) . '</dt><dd>') . esc_html(self::time($state['last_success_at'])) . ('</dd><dt>' . esc_html(__('Opplysningene gjelder til', 'reginor-lite')) . '</dt><dd>') . esc_html($state['snapshot'] ? self::time($state['snapshot']['expires_at']) : __('Ingen bekreftede opplysninger', 'reginor-lite')) . '</dd></dl>';
            if ($state['failures'] >= 3) { echo ('<p class="rnl-notice">' . esc_html(__('Flere kontroller har feilet. Be administrator se over prøveoppsettet. Automatisk kontroll prøver igjen senere.', 'reginor-lite')) . '</p>'); }
            echo ('<p>' . esc_html(__('Neste automatiske forsøk: ', 'reginor-lite'))) . esc_html(self::time($state['next_attempt_at'])) . '.</p>';
            self::form($id, 'check_capacity');
            $wait = max($state['not_before'], $state['requested_at'] + 60) > time();
            echo '<button class="rnl-button"' . ($wait ? ' disabled aria-describedby="rnl-capacity-wait"' : '') . ('>' . esc_html(__('Kontroller nå', 'reginor-lite')) . '</button>');
            if ($wait) { echo ('<p id="rnl-capacity-wait">' . esc_html(__('Kontrollen er bestilt eller venter. Vent litt, og oppdater siden.', 'reginor-lite')) . '</p>'); }
            echo '</form><p><a href="' . esc_url(add_query_arg(['page' => 'rnl-capacity', 'group' => $id], admin_url('admin.php'))) . ('">' . esc_html(__('Oppdater siden', 'reginor-lite')) . '</a></p></section>');
        } else { echo ('<p>' . esc_html(__('Ingen prøve er satt opp for dette kurset. Administrator kan velge en prøvesituasjon.', 'reginor-lite')) . '</p>'); }
        if (current_user_can('manage_options')) {
            echo '<details' . (!$state || self::$error ? ' open' : '') . ('><summary>' . esc_html(__('Prøveoppsett for administrator', 'reginor-lite')) . '</summary><p>' . esc_html(__('Velg en situasjon. Lagring erstatter tidligere prøveresultat og bestiller en ny kontroll. Dette endrer ikke kurset eller påmeldingen.', 'reginor-lite')) . '</p>');
            self::form($id, 'configure_capacity');
            echo '<input type="hidden" name="version" value="' . (int) ($state['version'] ?? 0) . ('"><label for="rnl-scenario">' . esc_html(__('Hva vil du prøve?', 'reginor-lite')) . '</label><select name="scenario" id="rnl-scenario">');
            $scenario = is_string($_POST['scenario'] ?? null) ? wp_unslash($_POST['scenario']) : ($state['scenario'] ?? 'off');
            foreach (['off' => __('Ingen prøve – stopp kontrollene', 'reginor-lite')] + DemoCapacityAdapter::scenarios() as $key => $label) { echo '<option value="' . esc_attr($key) . '"' . selected($scenario, $key, false) . '>' . esc_html($label) . '</option>'; }
            echo ('</select><p><button class="rnl-button">' . esc_html(__('Lagre prøveoppsett', 'reginor-lite')) . '</button></p></form><p>' . esc_html(__('Automatisk kontroll krever en fungerende jobbkjører. Serverstyrt kjøring må settes opp og prøves før eventuell ekte tilkobling tas i bruk.', 'reginor-lite')) . '</p></details>');
        }
        echo '</div>';
    }
}
