<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\{CourseRepository, SalesHistory, LetsRegConnection, LetsRegAvailabilityStore};
use function RegiNor\Lite\translate as __;

final class SalesHistoryPanel
{
    public static function boot(): void
    {
        add_action('admin_post_rnl_sales_history_refresh', static function (): void {
            if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
            check_admin_referer('rnl_sales_history_refresh');
            $id = 0; $ok = false;
            try {
                $id = CourseActions::integer($_POST['course'] ?? '');
                $ok = self::refresh($id);
            } catch (\Throwable) { /* Provider errors remain on the connection page, never in a redirect. */ }
            wp_safe_redirect(add_query_arg(['page' => 'rnl-analytics', 'sales_course' => $id, 'sales_refresh' => $ok ? 'ok' : 'error'], admin_url('admin.php')), 303); exit;
        });
    }
    public static function refresh(int $id): bool
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Du har ikke tilgang.', 'reginor-lite'), 403); }
        if (!get_option('rnl_sales_history_enabled', false)) { throw new \RuntimeException(__('Slå på historikk og lagre måleoppsettet først.', 'reginor-lite'), 409); }
        $course = (new CourseRepository())->get($id, 'group')['data']; $binding = SalesHistory::binding($course);
        if (!$binding) { throw new \RuntimeException(__('Kurset mangler en kobling til konfigurert LetsReg-arrangør.', 'reginor-lite'), 409); }
        return LetsRegConnection::check($binding['event'], null, true)['state'] === 'verified';
    }
    private static function number(?int $number, bool $amount = false, bool $signed = false): string
    {
        if ($number === null) { return __('Ikke oppgitt', 'reginor-lite'); }
        return ($signed && $number > 0 ? '+' : '') . number_format_i18n($amount ? $number / 100 : $number, $amount ? 2 : 0);
    }
    private static function time(int $at): string { return wp_date('d.m.Y H:i:s', $at); }
    private static function cells(array $values): void
    {
        echo '<tr>'; foreach ($values as $value) { echo '<td>' . esc_html((string) $value) . '</td>'; } echo '</tr>';
    }
    private static function table(array $headings): void
    {
        echo '<div class="rnl-scroll"><table class="widefat striped"><thead><tr>';
        foreach ($headings as $heading) { echo '<th scope="col">' . esc_html($heading) . '</th>'; }
        echo '</tr></thead><tbody>';
    }
    public static function render(): void
    {
        if (!current_user_can('manage_options')) { return; }
        echo '<section class="rnl-panel" id="rnl-sales-history"><h2>' . esc_html(__('Klikk og utvikling hos LetsReg', 'reginor-lite')) . '</h2><p>' . esc_html(__('Sammenlign målte klikk med rapportert deltakerantall og ordresum. Tallene gjelder hele LetsReg-arrangementet. Vi vet ikke hvilke klikk som førte til påmelding eller betaling.', 'reginor-lite')) . '</p>';
        $courses = [];
        foreach ((new CourseRepository())->listing('group') as $id => $state) {
            if (SalesHistory::binding($state['data'])) { $courses[$id] = $state['data']; }
        }
        if (!$courses) { echo '<p>' . esc_html(__('Koble et kurs til LetsReg for å se denne rapporten.', 'reginor-lite')) . '</p></section>'; return; }
        $raw = $_GET['sales_course'] ?? ''; $id = is_scalar($raw) && ctype_digit((string) $raw) ? (int) $raw : (int) array_key_first($courses);
        $rawWindow = $_GET['sales_window'] ?? '15'; $window = is_scalar($rawWindow) && in_array((string) $rawWindow, ['5', '15', '30', '60'], true) ? (int) $rawWindow : 15;
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" class="rnl-field-section"><input type="hidden" name="page" value="rnl-analytics"><p><label for="rnl-sales-course">' . esc_html(__('Velg kurs', 'reginor-lite')) . '</label> <select id="rnl-sales-course" name="sales_course">';
        foreach ($courses as $cid => $course) { echo '<option value="' . (int) $cid . '"' . selected($id, $cid, false) . '>' . esc_html($course['title'] . ' · ' . get_the_title($course['period_id'])) . '</option>'; }
        echo '</select></p><p><label for="rnl-sales-window">' . esc_html(__('Tidsvindu for eksperimentell sammenligning', 'reginor-lite')) . '</label> <select id="rnl-sales-window" name="sales_window">';
        foreach ([5, 15, 30, 60] as $minutes) { echo '<option value="' . $minutes . '"' . selected($window, $minutes, false) . '>' . esc_html(sprintf(/* translators: %d: minutes. */ __('%d minutter', 'reginor-lite'), $minutes)) . '</option>'; }
        echo '</select></p><p class="rnl-help">' . esc_html(__('Bare den eksperimentelle vurderingen påvirkes. Ingen salg tilordnes automatisk en kampanje.', 'reginor-lite')) . '</p><p><button class="rnl-button" type="submit">' . esc_html(__('Vis utvikling', 'reginor-lite')) . '</button></p></form>';
        if (!isset($courses[$id])) { echo '<p class="rnl-notice">' . esc_html(__('Det valgte kurset har ikke en gyldig LetsReg-kobling.', 'reginor-lite')) . '</p></section>'; return; }
        $report = SalesHistory::report($id, $window); $observations = $report['observations']; $last = $observations ? end($observations) : null;
        if (!get_option('rnl_sales_history_enabled', false)) { echo '<p class="rnl-notice">' . esc_html(__('Historikken er avslått. Kryss av for historikk i måleoppsettet over og lagre for å starte innsamling.', 'reginor-lite')) . '</p>'; }
        else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="rnl_sales_history_refresh"><input type="hidden" name="course" value="' . $id . '">'; wp_nonce_field('rnl_sales_history_refresh');
            echo '<p><button class="rnl-button rnl-button-secondary" type="submit">' . esc_html(__('Hent siste tall fra LetsReg', 'reginor-lite')) . '</button></p></form>';
        }
        if (isset($_GET['sales_refresh'])) { echo '<p class="rnl-notice" role="status">' . esc_html($_GET['sales_refresh'] === 'ok' ? __('LetsReg-kontrollen er fullført. Se tidspunkt og tilgjengelige tall nedenfor.', 'reginor-lite') : __('Tallene kunne ikke oppdateres. Kontroller at historikk er slått på. Vent litt og prøv igjen, eller se LetsReg-tilkobling for tilgangsfeil.', 'reginor-lite')) . '</p>'; }
        if (get_transient('rnl_sales_history_error')) { echo '<p class="rnl-notice">' . esc_html(__('Noen historikkmålinger kunne ikke lagres. Utviklingen kan ha hull.', 'reginor-lite')) . '</p>'; }
        if (!$report['clicks_complete']) { echo '<p class="rnl-notice">' . esc_html(__('Klikkmengden overstiger rapportgrensen. Klikktallene er ufullstendige, og tidskobling er slått av.', 'reginor-lite')) . '</p>'; }
        if (!$report['history_complete']) { echo '<p class="rnl-notice">' . esc_html(__('Historikken overstiger rapportgrensen. Bare de nyeste målingene vises, uten tidsvurdering.', 'reginor-lite')) . '</p>'; }
        if (count($report['shared']) > 1) { echo '<p class="rnl-notice">' . esc_html(__('Flere lokale kurs deler dette LetsReg-arrangementet. Deltakerantall og ordresum kan ikke fordeles på kursene eller summeres mellom dem. Tidsvurderingen inkluderer målte klikk fra alle de koblede kursene.', 'reginor-lite')) . '</p>'; }
        echo '<p class="rnl-help">' . esc_html(__('Ordresummen kommer fra LetsReg, men valuta og behandling av betaling/refusjon er ikke dokumentert for dette feltet. Den vises uten valutasymbol. Endringer kan også skyldes rettelser, gratis påmeldinger, flere deltakere eller refusjoner. Første måling er bare et utgangspunkt.', 'reginor-lite')) . '</p>';
        if ($last) {
            echo '<p>' . esc_html(sprintf(/* translators: %s: local timestamp. */ __('Sist observert: %s.', 'reginor-lite'), self::time($last['at']))) . '</p>';
            $fresh = LetsRegAvailabilityStore::inspect($courses[$id], time());
            if ($last['at'] + 900 <= time() || empty($fresh['observation'])) { echo '<p class="rnl-notice">' . esc_html(__('Tallene er historiske. En fersk, gyldig kontroll mangler; de skal ikke tolkes som status akkurat nå.', 'reginor-lite')) . '</p>'; }
        } else { echo '<p>' . esc_html(__('Ingen historikk ennå. Første kontroll lager utgangspunktet; senere kontroller viser utviklingen. Tidligere påmeldingstidspunkter kan ikke gjenskapes fra totalsummen.', 'reginor-lite')) . '</p>'; }
        echo '<h3>' . esc_html(__('Daglig oversikt', 'reginor-lite')) . '</h3><p class="rnl-help">' . esc_html(__('Klikk er antall målte besøksøkter den dagen. LetsReg-tall er siste observerte total den dagen, ikke antall kjøp den dagen. Manglende målinger vises som ikke oppgitt.', 'reginor-lite')) . '</p>';
        self::table([__('Dato', 'reginor-lite'), __('Besøksøkter med klikk', 'reginor-lite'), __('Deltakere – total', 'reginor-lite'), __('Ordresum – total', 'reginor-lite')]);
        foreach ($report['days'] as $date => $day) { self::cells([$date, $day['clicks'], self::number($day['observation']['participants'] ?? null), self::number($day['observation']['order_sum_minor'] ?? null, true)]); }
        echo '</tbody></table></div><details><summary>' . esc_html(__('Se kampanjeklikk per dag', 'reginor-lite')) . '</summary>';
        self::table([__('Dato', 'reginor-lite'), __('Kilde', 'reginor-lite'), __('Kanal', 'reginor-lite'), __('Kampanje', 'reginor-lite'), __('Besøksøkter med klikk', 'reginor-lite')]);
        foreach ($report['campaigns'] as $row) { self::cells([$row['day'], $row['source'] ?: __('Ukjent', 'reginor-lite'), $row['medium'], $row['campaign'], $row['clicks']]); }
        echo '</tbody></table></div></details><details><summary>' . esc_html(__('Målehistorikk og mulige tidssammenfall – eksperimentelt', 'reginor-lite')) . '</summary><p>' . esc_html(__('Viser de siste 50 kontrollene. En økning skjedde en gang mellom to kontroller; kjøpstidspunktet er ukjent. Vi ser etter klikk fra valgt antall minutter før forrige kontroll frem til denne kontrollen. Ved mer enn 20 minutter mellom målinger gjøres ingen vurdering. Samme klikk kan forekomme ved flere økninger: dette er ikke salgstelling eller prosentvis sannsynlighet. Direkte og umålte besøk er alltid mulige kilder.', 'reginor-lite')) . '</p>';
        self::table([__('Kontrollintervall', 'reginor-lite'), __('Endring i deltakere', 'reginor-lite'), __('Endring i ordresum', 'reginor-lite'), __('Tidsvurdering', 'reginor-lite'), __('Klikkenes kilder – ikke salgstilordning', 'reginor-lite')]);
        $labels = ['baseline' => __('Første måling – ingen endring beregnet', 'reginor-lite'), 'no_increase' => __('Ingen registrert økning. Nedgang kan være rettelse/refusjon.', 'reginor-lite'), 'gap' => __('For langt mellom målingene', 'reginor-lite'), 'no_clicks' => __('Ingen målte klikk i tidsvinduet – kilde ukjent', 'reginor-lite'), 'one_candidate' => __('Én målt besøksøkt i tidsvinduet – mulig sammenfall, ikke bekreftet kobling', 'reginor-lite'), 'multiple_candidates' => __('Flere målte besøksøkter – tvetydig sammenfall', 'reginor-lite'), 'incomplete' => __('Ufullstendig klikkgrunnlag – ingen vurdering', 'reginor-lite')];
        foreach (array_reverse(array_slice($observations, -50)) as $o) {
            $text = $labels[$o['coincidence']['reason']];
            if ($o['coincidence']['journeys']) { $text .= ' (' . $o['coincidence']['journeys'] . ')'; }
            $sources = [];
            foreach (array_slice($o['coincidence']['sources'], 0, 5) as $source) {
                $sources[] = implode(' · ', array_filter([$source['source'] ?: __('Ukjent', 'reginor-lite'), $source['medium'], $source['campaign']], static fn(string $value): bool => $value !== ''));
            }
            if (count($o['coincidence']['sources']) > 5) { $sources[] = __('Flere kilder – se kampanjeoversikten', 'reginor-lite'); }
            self::cells([($o['from'] ? self::time($o['from']) . ' → ' : '') . self::time($o['at']), self::number($o['change']['participants'], false, true), self::number($o['change']['order_sum_minor'], true, true), $text, implode('; ', $sources) ?: '–']);
        }
        echo '</tbody></table></div></details></section>';
    }
}
