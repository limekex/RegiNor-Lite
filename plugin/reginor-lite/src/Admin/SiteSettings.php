<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class SiteSettings
{
    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('Nettsidevisning', 'reginor-lite'), __('Nettsidevisning', 'reginor-lite'), 'manage_options', 'rnl-site', [self::class, 'render']);
        add_action('admin_enqueue_scripts', static function ($page) use ($hook): void { if ($page === $hook) { CalendarSettings::enqueue(); } });
        add_action('load-' . $hook, static function (): void {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
            if (!current_user_can('manage_options')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
            if (isset($_POST['rnl_calendar_settings'])) { CalendarSettings::save(); }
            check_admin_referer('rnl_site_settings');
            $raw = wp_unslash($_POST['course_page'] ?? '');
            $id = is_string($raw) && ctype_digit($raw) ? (int) $raw : -1;
            if ($id !== 0 && (get_post_type($id) !== 'page' || get_post_status($id) !== 'publish' || get_post_field('post_password', $id) !== '')) { wp_die(__('Velg en publisert side uten passord.', 'reginor-lite')); }
            update_option('rnl_course_page_id', $id, false);
            wp_safe_redirect(admin_url('admin.php?page=rnl-site&updated=1'), 303); exit;
        });
    }
    public static function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
        echo ('<div class="wrap rnl-ui rnl-admin"><span class="rnl-eyebrow">RegiNor Lite</span><h1>' . esc_html(__('Vis kursene på nettsiden', 'reginor-lite')) . '</h1><p>' . esc_html(__('Koble kursoversikten til en valgt WordPress-side. Dagens kursinngang endres først når du selv legger inn oversikten.', 'reginor-lite')) . '</p>');
        if (isset($_GET['updated'])) { echo ('<p class="rnl-notice">' . esc_html(__('Siden er valgt. Legg inn kursoversikten på siden for å vise den.', 'reginor-lite')) . '</p>'); }
        echo ('<ol class="rnl-steps"><li>' . esc_html(__('Velg side', 'reginor-lite')) . '</li><li>' . esc_html(__('Legg inn kursoversikten', 'reginor-lite')) . '</li><li>' . esc_html(__('Kontroller siden', 'reginor-lite')) . '</li></ol><form method="post">'); wp_nonce_field('rnl_site_settings');
        echo ('<label for="rnl-page">' . esc_html(__('Hvilken side skal vise kursene?', 'reginor-lite')) . '</label><select id="rnl-page" name="course_page"><option value="0">' . esc_html(__('Ikke koblet til ennå', 'reginor-lite')) . '</option>');
        foreach (get_pages(['post_status' => 'publish']) as $p) { if ($p->post_password !== '') { continue; } echo '<option value="' . (int) $p->ID . '"' . selected((int) get_option('rnl_course_page_id', 0), $p->ID, false) . '>' . esc_html($p->post_title) . '</option>'; }
        echo '</select>'; submit_button(__('Lagre valgt side', 'reginor-lite')); echo ('</form><section class="rnl-panel"><h2>' . esc_html(__('Legg inn oversikten', 'reginor-lite')) . '</h2><p>' . esc_html(__('I WordPress-editoren velger du blokken ', 'reginor-lite')) . '<strong>' . esc_html(__('RegiNor kursoversikt', 'reginor-lite')) . '</strong>.</p><p>' . esc_html(__('I Avada kan du bruke et Text Block-element og lime inn:', 'reginor-lite')) . '</p><p><code>[reginor_courses]</code></p><p>' . esc_html(__('Kontroller at elementet tolker shortcodes i den installerte Avada-versjonen. Ingen eksisterende ACF-kurs flyttes eller endres.', 'reginor-lite')) . '</p></section>');
        echo '<details class="rnl-panel"><summary>' . esc_html(__('Kortkoder for kampanjesider', 'reginor-lite')) . '</summary><p>' . esc_html(__('Vis fremhevede introkurs først, og øvrige kurs i en egen oversikt nedenfor. Lim inn hver kortkode i sitt eget tekstelement.', 'reginor-lite')) . '</p>';
        echo '<p><code>' . esc_html('[reginor_courses levels="intro" featured="only" default_view="list" show_header="0" show_filters="0" show_view_switch="0"]') . '</code></p>';
        echo '<p><code>' . esc_html('[reginor_courses exclude_levels="intro" default_view="week" show_header="0"]') . '</code></p>';
        echo '<p>' . esc_html(__('Nivånavnene må finnes under Kursinnhold og ressurser → Kursnivåer. Bruk komma mellom flere navn, for eksempel «nybegynner,intro». Du kan også bruke nivå-ID. Utelatte nivåer skjules fra både kursutvalget og nivåfilteret.', 'reginor-lite')) . '</p><dl>';
        foreach ([
            'levels / exclude_levels' => __('Vis bare / utelat de oppgitte nivåene.', 'reginor-lite'),
            'featured="only"' => __('Vis bare kurs merket Fremhev. Standard er alle kurs; «exclude» utelater fremhevede kurs.', 'reginor-lite'),
            'default_view="list" / "week"' => __('Start med kort / kalender. «site» følger felles utseendevalg, «period» følger kursperioden.', 'reginor-lite'),
            'allowed_views="list" / "week" / "list,week"' => __('Tillat kort, kalender eller begge visninger.', 'reginor-lite'),
            'show_header="0"' => __('Skjul innledning, periodeoverskrift og synlig antall treff.', 'reginor-lite'),
            'show_filters="0"' => __('Skjul periode-, nivå- og dagfilter.', 'reginor-lite'),
            'show_view_switch="0"' => __('Skjul visningsvalget og bruk startvisningen fast.', 'reginor-lite'),
        ] as $attribute => $help) { echo '<dt><code>' . esc_html($attribute) . '</code></dt><dd>' . esc_html($help) . '</dd>'; }
        echo '</dl></details>';
        $url = \RegiNor\Lite\Frontend\PublicSite::url();
        if ($url) { echo '<p><a class="rnl-button" href="' . esc_url($url) . ('">' . esc_html(__('Åpne kursoversikten →', 'reginor-lite')) . '</a></p>'); }
        SharingSettings::render();
        CalendarSettings::render();
        echo ('<details><summary>' . esc_html(__('Teknisk kontroll før lansering', 'reginor-lite')) . '</summary><p>' . esc_html(__('Unnta den valgte siden, kursparametrene og RegiNor-sitemap fra fullsidecache og CDN-cache. Kontroller at SEO-pluginen bruker riktig canonical og ikke lager motstridende kursmerking. Avada og The Events Calendar må prøves i staging.', 'reginor-lite')) . '</p></details></div>');
    }
}
