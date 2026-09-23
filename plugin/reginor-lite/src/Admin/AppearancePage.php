<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\Appearance;
use RegiNor\Lite\Infrastructure\ColorPalette;
use function RegiNor\Lite\translate as __;

final class AppearancePage
{
    private static string $error = '';
    private static int $fieldId = 0;
    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('Utseende', 'reginor-lite'), __('Utseende', 'reginor-lite'), 'manage_options', 'rnl-appearance', [self::class, 'render']);
        add_action('load-' . $hook, static function (): void {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
            if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
            check_admin_referer('rnl_appearance');
            try {
                $raw = wp_unslash($_POST['appearance'] ?? []);
                if (!is_array($raw)) { throw new \InvalidArgumentException(__('Kontroller feltene og prøv igjen.', 'reginor-lite')); }
                if (($_POST['operation'] ?? '') === 'global') { update_option(Appearance::OPTION, Appearance::validate($raw), false); }
                else { throw new \InvalidArgumentException(__('Ukjent handling.', 'reginor-lite')); }
                wp_safe_redirect(add_query_arg(['page' => 'rnl-appearance', 'period' => absint($_POST['period'] ?? 0), 'updated' => 1], admin_url('admin.php')), 303); exit;
            } catch (\Throwable $error) { self::$error = $error->getMessage(); status_header($error->getCode() === 403 ? 403 : 400); }
        });
    }
    public static function color(string $key, string $label, string $help, string $value, array $settings, bool $course = false, string $namespace = 'appearance'): void
    {
        $uid = $key . '-' . ++self::$fieldId;
        $prefix = $course ? '' : $key . '_'; $field = $course ? 'color' : $key;
        $namePrefix = $namespace === 'data' ? 'appearance_' : '';
        $source = $settings[$prefix . 'source'] ?? ''; $alpha = $settings[$prefix . 'alpha'] ?? 100; $tone = $settings[$prefix . 'tone'] ?? 'auto';
        echo '<div class="rnl-field-card" data-rnl-swatch="' . esc_attr($key) . '"><label for="rnl-source-' . esc_attr($uid) . '">' . esc_html($label) . '</label><select id="rnl-source-' . esc_attr($uid) . '" name="' . esc_attr($namespace) . '[' . esc_attr($namePrefix . $prefix . 'source') . ']" data-rnl-source><option value="">' . esc_html(__('Egen farge', 'reginor-lite')) . '</option>';
        $choices = ColorPalette::choices();
        if ($source && !isset($choices[$source])) { $choices[$source] = ['label' => __('Lagret global farge', 'reginor-lite') . ' · ' . $source, 'color' => '']; }
        foreach ($choices as $id => $choice) { echo '<option value="' . esc_attr($id) . '" data-color="' . esc_attr($choice['color']) . '"' . selected($source, $id, false) . '>' . esc_html($choice['label']) . '</option>'; }
        echo '</select><div class="rnl-palette-samples" data-rnl-palette-samples role="group" aria-label="' . esc_attr($label) . '"></div><label for="rnl-color-' . esc_attr($uid) . '" class="rnl-help">' . esc_html(__('Egen farge / reservefarge', 'reginor-lite')) . '</label><input type="color" id="rnl-color-' . esc_attr($uid) . '" name="' . esc_attr($namespace) . '[' . esc_attr($namePrefix . $field) . ']" value="' . esc_attr($value) . '" data-rnl-color="' . esc_attr($key) . '" data-default="' . esc_attr(Appearance::COLORS[$key] ?? '#ffffff') . '" aria-describedby="rnl-help-' . esc_attr($uid) . '"><span class="rnl-help" id="rnl-help-' . esc_attr($uid) . '">' . esc_html($help) . '</span><label for="rnl-alpha-' . esc_attr($uid) . '">' . esc_html(__('Alpha / dekkevne (%)', 'reginor-lite')) . '</label><input type="range" min="0" max="100" step="1" value="' . (int) $alpha . '" data-rnl-alpha-slider aria-label="' . esc_attr(__('Juster dekkevne', 'reginor-lite')) . '"><input type="number" id="rnl-alpha-' . esc_attr($uid) . '" name="' . esc_attr($namespace) . '[' . esc_attr($namePrefix . $prefix . 'alpha') . ']" value="' . (int) $alpha . '" min="0" max="100" step="1" required data-rnl-alpha><span class="rnl-help">' . esc_html(__('0 = helt gjennomsiktig. 100 = helt dekkende. Tekst og knapper beholder full styrke.', 'reginor-lite')) . '</span><label for="rnl-tone-' . esc_attr($uid) . '">' . esc_html(__('Tekst på bakgrunnen', 'reginor-lite')) . '</label><select id="rnl-tone-' . esc_attr($uid) . '" name="' . esc_attr($namespace) . '[' . esc_attr($namePrefix . $prefix . 'tone') . ']" data-rnl-tone>';
        foreach (['auto' => __('Automatisk utgangspunkt', 'reginor-lite'), 'dark' => __('Mørk tekst', 'reginor-lite'), 'light' => __('Lys tekst', 'reginor-lite')] as $id => $text) { echo '<option value="' . $id . '"' . selected($tone, $id, false) . '>' . esc_html($text) . '</option>'; }
        echo '</select></div>';
    }
    public static function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(esc_html(__('Du har ikke tilgang.', 'reginor-lite')), '', ['response' => 403]); }
        $s = Appearance::settings(); $period = 0;
        echo '<div class="wrap rnl-ui rnl-admin" data-rnl-appearance-root><h1>' . esc_html(__('Utseende på kursoversikten', 'reginor-lite')) . '</h1><p>' . esc_html(__('Velg farger, se et eksempel og lagre når du er fornøyd. Valgene gjelder RegiNor på nettsiden.', 'reginor-lite')) . '</p>';
        if (self::$error) { echo '<p class="rnl-notice" role="alert">' . esc_html(self::$error) . '</p>'; }
        elseif (isset($_GET['updated'])) { echo '<p class="rnl-notice" role="status">' . esc_html(__('Utseendet er lagret.', 'reginor-lite')) . '</p>'; }
        echo '<form method="post" data-rnl-appearance>'; wp_nonce_field('rnl_appearance');
        echo '<input type="hidden" name="operation" value="global"><input type="hidden" name="period" value="' . $period . '"><section class="rnl-field-section"><h2>' . esc_html(__('1. Farger', 'reginor-lite')) . '</h2><p>' . esc_html(__('Velg egen farge eller en koblet global farge. Juster alpha for gjennomsiktighet. Ved transparente bakgrunner må tekstfargen vurderes mot det som ligger bak.', 'reginor-lite')) . '</p><div class="rnl-field-grid">';
        $labels = ['background' => [__('Hele oversikten', 'reginor-lite'), __('Bakgrunnen bak filtre, kursliste og kalender.', 'reginor-lite')],
            'day' => [__('Dagkolonner', 'reginor-lite'), __('Bakgrunnen rundt alle kurs på en dag, for eksempel mandag.', 'reginor-lite')],
            'day_heading' => [__('Dagoverskrifter', 'reginor-lite'), __('Feltet med dagsnavnet øverst i kalenderen.', 'reginor-lite')],
            'room' => [__('Salkolonner', 'reginor-lite'), __('Bak salnavnet og nedover salens tidsplan på stor skjerm.', 'reginor-lite')],
            'course' => [__('Kurskort', 'reginor-lite'), __('Vanlig bakgrunn for kurs i liste, kalender og detaljer.', 'reginor-lite')],
            'highlight' => [__('Fremhevede kurs', 'reginor-lite'), __('Bakgrunn for fremhevede kurs. Fremheving velges i kursoppsettet.', 'reginor-lite')]];
        foreach ($labels as $key => [$label, $help]) { self::color($key, $label, $help, $s[$key], $s); }
        echo '</div><div class="rnl-field-card"><label for="rnl-day-color-views">' . esc_html(__('Hvor skal dagsfargene brukes?', 'reginor-lite')) . '</label><select id="rnl-day-color-views" name="appearance[day_color_views]" data-rnl-day-color-views>';
        foreach (['week' => __('Kun kalendervisning', 'reginor-lite'), 'list' => __('Kun kurslistevisning (kort)', 'reginor-lite'), 'both' => __('Begge', 'reginor-lite')] as $value => $label) { echo '<option value="' . $value . '"' . selected($s['day_color_views'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><p class="rnl-help">' . esc_html(__('Gjelder standardfargen for dagkolonner og egne farger per ukedag. I kalenderen farges dagens bakgrunn og overskrift. I kurslisten farges kortene; egen kursfarge og fremheving går foran. Enkeltkurssiden påvirkes ikke.', 'reginor-lite')) . '</p></div>';
        echo '<details><summary>' . esc_html(__('Egne farger for bestemte dager', 'reginor-lite')) . '</summary><p>' . esc_html(__('Dagene bruker standardfargen over til du velger en egen farge. Saler kan overstyres i saloppsettet under Kursinnhold og ressurser.', 'reginor-lite')) . '</p>';
        foreach ([1 => __('Mandag', 'reginor-lite'), __('Tirsdag', 'reginor-lite'), __('Onsdag', 'reginor-lite'), __('Torsdag', 'reginor-lite'), __('Fredag', 'reginor-lite'), __('Lørdag', 'reginor-lite'), __('Søndag', 'reginor-lite')] as $day => $label) {
            $choice = $s['days'][$day] ?? [];
            echo '<details><summary>' . esc_html($label) . '</summary><label><input type="hidden" name="appearance[days][' . $day . '][enabled]" value="0"><input type="checkbox" name="appearance[days][' . $day . '][enabled]" value="1"' . checked(!empty($choice['enabled']), true, false) . '> ' . esc_html(__('Bruk egen farge denne dagen', 'reginor-lite')) . '</label>';
            self::color('day' . $day, $label, __('Brukes for denne ukedagen i visningene du valgte over.', 'reginor-lite'), $choice['color'] ?? $s['day'], $choice, true, 'appearance[days][' . $day . ']');
            echo '</details>';
        }
        echo '</details><div>';
        echo '</div><button type="button" class="rnl-button rnl-button-secondary" data-rnl-reset hidden>' . esc_html(__('Bruk standardfargene', 'reginor-lite')) . '</button></section><section class="rnl-field-section"><h2>' . esc_html(__('2. Visning', 'reginor-lite')) . '</h2><div class="rnl-field-grid"><div class="rnl-field-card"><label for="rnl-default-view">' . esc_html(__('Hva skal besøkende se først?', 'reginor-lite')) . '</label><select id="rnl-default-view" name="appearance[view]">';
        foreach (['period' => __('Bruk kursperiodens valg', 'reginor-lite'), 'list' => __('Kursliste', 'reginor-lite'), 'week' => __('Ukeskalender', 'reginor-lite')] as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($s['view'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><span class="rnl-help">' . esc_html(__('Besøkende kan fortsatt bytte visning. Et eget valg i blokken eller shortcoden går foran dette.', 'reginor-lite')) . '</span></div><div class="rnl-field-card"><label for="rnl-day-layout">' . esc_html(__('Hvordan plasseres dagene?', 'reginor-lite')) . '</label><select id="rnl-day-layout" name="appearance[layout]">';
        foreach (['columns' => __('Ved siden av hverandre når det er plass', 'reginor-lite'), 'stacked' => __('Under hverandre, med mer plass til salene', 'reginor-lite')] as $value => $label) { echo '<option value="' . $value . '"' . selected($s['layout'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select><span class="rnl-help">' . esc_html(__('På mobil vises kursene som en lesbar liste.', 'reginor-lite')) . '</span></div></div></section><h2>' . esc_html(__('Forhåndsvisning av farger', 'reginor-lite')) . '</h2><p>' . esc_html(__('Eksempler, ikke ekte kurs. Endringer vises her med én gang og på nettsiden etter lagring.', 'reginor-lite')) . '</p>';
        echo '<div class="rnl-ui rnl-public rnl-appearance-preview" data-rnl-preview style="' . esc_attr(Appearance::variables($s)) . '"><div class="rnl-day"><h3>' . esc_html(__('Mandag', 'reginor-lite')) . '</h3><div class="rnl-preview-rooms">';
        foreach ([false, true] as $featured) {
            echo '<div class="rnl-preview-room"><h4 class="rnl-room-heading">' . esc_html($featured ? __('Sal B', 'reginor-lite') : __('Sal A', 'reginor-lite')) . '<span class="rnl-room-venue">' . esc_html(__('Eksempelsted', 'reginor-lite')) . '</span></h4><article class="rnl-week-course' . ($featured ? ' rnl-featured' : '') . '">';
            echo '<h4>' . esc_html(__('Eksempelkurs', 'reginor-lite')) . '</h4><p>18:15–19:15</p><p>' . esc_html(__('For deg som vil lære å danse.', 'reginor-lite')) . '</p></article></div>';
        }
        echo '</div></div><h3>' . esc_html(__('Kursliste – eksempel', 'reginor-lite')) . '</h3><article class="rnl-card" data-rnl-day-card-preview><h4>' . esc_html(__('Eksempelkurs mandag', 'reginor-lite')) . '</h4><p><strong>' . esc_html(__('Sal A', 'reginor-lite')) . '</strong><br>' . esc_html(__('Eksempelsted', 'reginor-lite')) . '</p></article></div>'; submit_button(__('Lagre farger og visning', 'reginor-lite')); echo '</form>'; $url = \RegiNor\Lite\Frontend\PublicSite::url();
        if ($url) { echo '<p><a class="rnl-button rnl-button-secondary" href="' . esc_url($url) . '">' . esc_html(__('Åpne kursoversikten', 'reginor-lite')) . '</a></p>'; }
        if (ColorPalette::avada()) { echo '<p class="rnl-help">' . esc_html(__('Avadas globale farger hentes fra nettstedet. Kontroller resultatet på kursoversikten, særlig ved gjennomsiktighet.', 'reginor-lite')) . '</p><iframe hidden data-rnl-palette-frame title="' . esc_attr(__('Henter nettstedets globale farger', 'reginor-lite')) . '" src="' . esc_url(add_query_arg('rnl_palette', wp_create_nonce('rnl_palette'), home_url('/'))) . '"></iframe>'; }
        echo '</div>';
    }
}
