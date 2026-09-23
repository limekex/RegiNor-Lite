<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\JourneyStore;
use function RegiNor\Lite\translate as __;

final class AnalyticsPage
{
    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('Statistikk og kampanjer', 'reginor-lite'), __('Statistikk', 'reginor-lite'), 'manage_options', 'rnl-analytics', [self::class, 'render']);
        add_action('load-' . $hook, static function (): void {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { return; }
            if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
            check_admin_referer('rnl_analytics_settings');
            if (isset($_POST['enabled'])) { JourneyStore::install(); }
            update_option('rnl_journey_enabled', isset($_POST['enabled']), false);
            if (isset($_POST['sales_history'])) { \RegiNor\Lite\Infrastructure\SalesHistory::install(); }
            update_option('rnl_sales_history_enabled', isset($_POST['sales_history']), false);
            wp_safe_redirect(admin_url('admin.php?page=rnl-analytics&updated=1'), 303); exit;
        });
    }
    public static function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
        $report = JourneyStore::report(); $enabled = (bool) get_option('rnl_journey_enabled', false);
        echo '<div class="wrap rnl-ui rnl-admin"><h1>' . esc_html(__('Statistikk og kampanjer', 'reginor-lite')) . '</h1><p>' . esc_html(__('Se hvordan besøkende finner kursene og går videre til LetsReg. Tallene gjelder målte besøk med samtykke de siste 30 dagene, ikke alle besøkende.', 'reginor-lite')) . '</p>';
        if (isset($_GET['updated'])) { echo '<p class="rnl-notice" role="status">' . esc_html(__('Innstillingen er lagret.', 'reginor-lite')) . '</p>'; }
        echo '<section class="rnl-panel"><h2>' . esc_html(__('Måleoppsett', 'reginor-lite')) . '</h2><p>' . esc_html(__('Statistikk krever samtykke til statistikk. Annonse-ID-er fra Google og sosiale medier krever i tillegg markedsføringssamtykke. Ingen sporingsskript fra Google eller Meta installeres her.', 'reginor-lite')) . '</p><form method="post">';
        wp_nonce_field('rnl_analytics_settings');
        echo '<label><input type="checkbox" name="enabled" value="1"' . checked($enabled, true, false) . '> ' . esc_html(__('Registrer besøksveien etter samtykke', 'reginor-lite')) . '</label><p class="rnl-help">' . esc_html(__('Av som standard. Navn, e-post og fullstendige nettadresser lagres ikke. Egne måledata slettes etter 30 dager. Innloggede brukere telles ikke.', 'reginor-lite')) . '</p>';
        echo '<p><label><input type="checkbox" name="sales_history" value="1"' . checked((bool) get_option('rnl_sales_history_enabled', false), true, false) . '> ' . esc_html(__('Lagre LetsRegs ordresum og deltakerantall over tid', 'reginor-lite')) . '</label></p><p class="rnl-help">' . esc_html(__('Historikken starter ved neste kontroll og beholdes i 30 dager. Den inneholder bare arrangementstall og henter ikke deltakerregistre. Bakgrunnskontrollen følger koblede kurs, også med manuell påmeldingsstatus. Ordresum er ikke verifisert betaling.', 'reginor-lite')) . '</p>';
        submit_button(__('Lagre måleoppsett', 'reginor-lite')); echo '</form>';
        echo '<p>' . esc_html(function_exists('cmplz_has_consent') ? __('Complianz er funnet. Prøv godta, avvise og trekke tilbake samtykke før lansering.', 'reginor-lite') : __('Complianz er ikke funnet her. Besøksmålingen venter til samtykkeløsningen er tilgjengelig. Historikk over arrangementstall styres separat.', 'reginor-lite')) . '</p></section>';
        SalesHistoryPanel::render();
        $labels = ['landing' => __('Besøksøkter startet', 'reginor-lite'), 'page_view' => __('Besøksøkter med sidevisning', 'reginor-lite'), 'course_list' => __('Besøksøkter i kursoversikten', 'reginor-lite'), 'course_view' => __('Besøksøkter på kursdetaljer', 'reginor-lite'), 'letsreg_click' => __('Besøksøkter med klikk til LetsReg', 'reginor-lite')];
        $stages = array_column($report['stages'], 'journeys', 'stage');
        echo '<h2>' . esc_html(__('Veien til påmelding', 'reginor-lite')) . '</h2><dl class="rnl-facts">';
        foreach ($labels as $stage => $label) { echo '<div><dt>' . esc_html($label) . '</dt><dd>' . (int) ($stages[$stage] ?? 0) . '</dd></div>'; }
        echo '<div><dt>' . esc_html(__('Bekreftede påmeldinger og kjøp', 'reginor-lite')) . '</dt><dd>' . esc_html(__('Kan ikke knyttes til besøk med dagens LetsReg-støtte', 'reginor-lite')) . '</dd></div></dl><p class="rnl-help">' . esc_html(__('Et klikk viser interesse, ikke et gjennomført kjøp. Besøksøkter er nettleserøkter, ikke identifiserte personer. Samtykke og blokkering kan gi manglende trinn.', 'reginor-lite')) . '</p>';
        echo '<h2>' . esc_html(__('Kampanjer', 'reginor-lite')) . '</h2><div class="rnl-scroll"><table class="widefat"><thead><tr>';
        foreach ([__('Kilde', 'reginor-lite'), __('Kanal', 'reginor-lite'), __('Kampanje', 'reginor-lite'), __('Annonsevariant', 'reginor-lite'), __('Besøksøkter', 'reginor-lite'), __('Kursdetaljer', 'reginor-lite'), __('Til LetsReg', 'reginor-lite')] as $label) { echo '<th scope="col">' . esc_html($label) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($report['campaigns'] as $row) { echo '<tr>'; foreach (['source', 'medium', 'campaign', 'content', 'journeys', 'views', 'clicks'] as $key) { echo '<td>' . esc_html($row[$key] === '' ? __('Ikke oppgitt', 'reginor-lite') : (string) $row[$key]) . '</td>'; } echo '</tr>'; }
        if (!$report['campaigns']) { echo '<tr><td colspan="7">' . esc_html(__('Ingen målte besøk ennå.', 'reginor-lite')) . '</td></tr>'; }
        echo '</tbody></table></div><h2>' . esc_html(__('Interesse per kurs', 'reginor-lite')) . '</h2><div class="rnl-scroll"><table class="widefat"><thead><tr><th>' . esc_html(__('Kurs', 'reginor-lite')) . '</th><th>' . esc_html(__('Kursdetaljer', 'reginor-lite')) . '</th><th>' . esc_html(__('Til LetsReg', 'reginor-lite')) . '</th></tr></thead><tbody>';
        foreach ($report['courses'] as $row) { echo '<tr><td>' . esc_html(get_the_title((int) $row['course_id']) ?: __('Slettet kurs', 'reginor-lite')) . '</td><td>' . (int) $row['views'] . '</td><td>' . (int) $row['clicks'] . '</td></tr>'; }
        echo '</tbody></table></div><details><summary>' . esc_html(__('Oppsett i Google Tag Manager', 'reginor-lite')) . '</summary><p>' . esc_html(__('Bruk egne hendelsesutløsere for RegiNor i eksisterende GTM-oppsett. La Complianz styre samtykke for GA4 og annonser. Ikke tell disse hendelsene som kjøp.', 'reginor-lite')) . '</p><code>rnl_landing · rnl_page_view · rnl_course_list · rnl_course_view · rnl_letsreg_click</code><p>' . esc_html(__('Full kjøpskobling krever verifisert referanse og bekreftelse fra LetsReg. Ingen person- eller annonse-ID legges til påmeldingslenken i denne versjonen.', 'reginor-lite')) . '</p></details></div>';
    }
}
