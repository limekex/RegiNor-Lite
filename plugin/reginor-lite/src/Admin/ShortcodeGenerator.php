<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Frontend\OverviewOptions;
use RegiNor\Lite\Infrastructure\{CourseRepository, RichText};
use function RegiNor\Lite\translate as __;

/** Read-only builder: generates text, never publishes or changes course data. */
final class ShortcodeGenerator
{
    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('Kortkodegenerator', 'reginor-lite'), __('Kortkodegenerator', 'reginor-lite'), 'rnl_edit_periods', 'rnl-shortcodes', [self::class, 'render']);
        add_action('admin_enqueue_scripts', static function ($page) use ($hook): void {
            if ($page !== $hook) { return; }
            $file = dirname(__DIR__, 2) . '/assets/shortcode-generator.js';
            wp_enqueue_script('rnl-shortcode-generator', plugins_url('assets/shortcode-generator.js', dirname(__DIR__, 2) . '/reginor-lite.php'), [], (string) filemtime($file), true);
            wp_set_script_translations('rnl-shortcode-generator', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
        });
    }

    public static function build(array $input): string
    {
        $options = OverviewOptions::normalize($input);
        if ($options['invalid']) { throw new \InvalidArgumentException(__('Kontroller pris, klokkeslett og antall kvelder. Fra-verdien må være mindre enn eller lik til-verdien.', 'reginor-lite')); }
        $attributes = [];
        foreach (OverviewOptions::DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $input)) { continue; }
            if (in_array($key, OverviewOptions::LIST_FILTERS, true) || str_starts_with($key, 'exclude_')) {
                $value = implode(',', array_filter(array_map('sanitize_title', $options[$key])));
            } elseif (in_array($key, OverviewOptions::BOUNDS, true)) {
                $value = $options[$key] === null ? '' : (str_starts_with($key, 'price_') ? number_format($options[$key] / 100, 2, '.', '') : (string) $options[$key]);
            } elseif ($key === 'allowed_views') { $value = implode(',', $options[$key]); }
            elseif (str_starts_with($key, 'show_')) { $value = $options[$key] ? '1' : '0'; }
            else { $value = (string) $options[$key]; }
            if ($value === '') { continue; }
            $attributes[] = $key . '="' . $value . '"';
        }
        return '[reginor_courses' . ($attributes ? ' ' . implode(' ', $attributes) : '') . ']';
    }

    public static function render(): void
    {
        if (!current_user_can('rnl_edit_periods') || !current_user_can('rnl_select_resources')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
        $input = isset($_GET['rnl_sc']) && is_array($_GET['rnl_sc']) ? wp_unslash($_GET['rnl_sc']) : [];
        $options = OverviewOptions::normalize($input);
        $repo = new CourseRepository(); $choices = [];
        foreach (['levels'=>'level','periods'=>'period','rooms'=>'room','venues'=>'venue','courses'=>'group'] as $key => $kind) {
            $choices[$key] = [];
            foreach ($repo->listing($kind) as $id => $state) { $choices[$key][(string) $id] = $state['data']['title'] . ' (#' . $id . ')'; }
        }
        $choices['styles'] = [];
        foreach ($repo->listing('course') as $state) { $name = RichText::plain($state['data']['dance_style']); if ($name !== '') { $choices['styles'][sanitize_title($name)] = $name; } }
        $choices['instructors'] = [];
        foreach ($repo->instructors() as $instructor) { $choices['instructors'][(string) $instructor['id']] = $instructor['title']; }
        $choices['days'] = ['1'=>__('Mandag', 'reginor-lite'),'2'=>__('Tirsdag', 'reginor-lite'),'3'=>__('Onsdag', 'reginor-lite'),'4'=>__('Torsdag', 'reginor-lite'),'5'=>__('Fredag', 'reginor-lite'),'6'=>__('Lørdag', 'reginor-lite'),'7'=>__('Søndag', 'reginor-lite')];
        $choices['statuses'] = ['available'=>__('Påmelding tilgjengelig hos LetsReg', 'reginor-lite'),'external'=>__('Egen påmeldingslenke', 'reginor-lite'),'dropin'=>__('Kun drop-in', 'reginor-lite'),'later'=>__('Åpner snart', 'reginor-lite'),'full'=>__('Fullt', 'reginor-lite'),'waiting'=>__('Fullt – venteliste', 'reginor-lite'),'closed'=>__('Stengt', 'reginor-lite'),'cancelled'=>__('Avlyst', 'reginor-lite'),'unknown'=>__('Må avklares', 'reginor-lite'),'ended'=>__('Avsluttet', 'reginor-lite')];
        $choices['price_bases'] = ['person'=>__('Per person', 'reginor-lite'),'pair'=>__('Per par', 'reginor-lite')];
        echo '<div class="wrap rnl-ui rnl-admin"><h1>' . esc_html(__('Kortkodegenerator', 'reginor-lite')) . '</h1><p>' . esc_html(__('Velg kursutvalg og visning. Lag kortkoden og lim den inn i et tekstelement i Avada eller WordPress. Kurs og sider endres ikke her.', 'reginor-lite')) . '</p>';
        try { $code = self::build($input); }
        catch (\InvalidArgumentException $error) { $code = ''; echo '<p class="rnl-notice" role="alert">' . esc_html($error->getMessage()) . '</p>'; }
        if ($code) {
            echo '<section class="rnl-panel"><label for="rnl-generated-shortcode"><strong>' . esc_html(__('Din kortkode', 'reginor-lite')) . '</strong></label><textarea id="rnl-generated-shortcode" rows="4" readonly>' . esc_textarea($code) . '</textarea><button type="button" class="rnl-button rnl-button-secondary" id="rnl-copy-shortcode" hidden>' . esc_html(__('Kopier kortkode', 'reginor-lite')) . '</button><p id="rnl-copy-status" role="status" data-success="' . esc_attr(__('Kortkoden er kopiert.', 'reginor-lite')) . '" data-dirty="' . esc_attr(__('Valgene er endret. Trykk «Lag kortkode» for å oppdatere teksten.', 'reginor-lite')) . '" data-failure="' . esc_attr(__('Marker teksten og kopier med tastaturet.', 'reginor-lite')) . '">' . esc_html(__('Kortkoden oppdateres når du trykker «Lag kortkode» nedenfor.', 'reginor-lite')) . '</p></section>';
        }
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="rnl-shortcodes"><section class="rnl-panel"><h2>' . esc_html(__('Visning', 'reginor-lite')) . '</h2>';
        foreach (['default_view'=>[__('Startvisning', 'reginor-lite'),['list'=>__('Kurskort', 'reginor-lite'),'week'=>__('Kalender', 'reginor-lite'),'period'=>__('Kursperiodens valg', 'reginor-lite')]], 'allowed_views'=>[__('Tillatte visninger', 'reginor-lite'),['list,week'=>__('Begge', 'reginor-lite'),'list'=>__('Bare kurskort', 'reginor-lite'),'week'=>__('Bare kalender', 'reginor-lite')]], 'featured'=>[__('Fremheving', 'reginor-lite'),['all'=>__('Alle', 'reginor-lite'),'only'=>__('Bare fremhevede', 'reginor-lite'),'exclude'=>__('Utelat fremhevede', 'reginor-lite')]], 'dropin'=>[__('Drop-in', 'reginor-lite'),['all'=>__('Alle', 'reginor-lite'),'only'=>__('Bare kurs med drop-in', 'reginor-lite'),'exclude'=>__('Utelat kurs med drop-in', 'reginor-lite')]]] as $key => [$label,$values]) {
            $value = $key === 'allowed_views' ? implode(',', $options[$key]) : $options[$key];
            echo '<p><label>' . esc_html($label) . ' <select name="rnl_sc[' . esc_attr($key) . ']">'; foreach ($values as $v => $title) { echo '<option value="' . esc_attr($v) . '"' . selected($v, $value, false) . '>' . esc_html($title) . '</option>'; } echo '</select></label></p>';
        }
        foreach (['show_header'=>__('Vis overskrift og innledning', 'reginor-lite'),'show_filters'=>__('Vis filtre', 'reginor-lite'),'show_view_switch'=>__('Vis valg mellom kalender og kort', 'reginor-lite')] as $key=>$label) { echo '<p><input type="hidden" name="rnl_sc[' . esc_attr($key) . ']" value="0"><label><input type="checkbox" name="rnl_sc[' . esc_attr($key) . ']" value="1"' . checked($options[$key], true, false) . '> ' . esc_html($label) . '</label></p>'; }
        echo '</section><section class="rnl-panel"><h2>' . esc_html(__('Kursutvalg', 'reginor-lite')) . '</h2><p>' . esc_html(__('Ingen avkrysning betyr alle. Flere valg innen samme felt betyr enten eller; ulike felt må passe samtidig. Utelat vinner over Vis bare. Bare offentlig tilgjengelige kurs kan vises.', 'reginor-lite')) . '</p>';
        foreach (['styles'=>__('Dansestil', 'reginor-lite'),'levels'=>__('Nivå', 'reginor-lite'),'days'=>__('Ukedager', 'reginor-lite'),'venues'=>__('Steder', 'reginor-lite'),'rooms'=>__('Saler', 'reginor-lite'),'instructors'=>__('Instruktører', 'reginor-lite'),'periods'=>__('Kursperioder', 'reginor-lite'),'courses'=>__('Bestemte kurs', 'reginor-lite'),'statuses'=>__('Påmeldingsstatus', 'reginor-lite'),'price_bases'=>__('Prisgrunnlag', 'reginor-lite')] as $key=>$label) {
            echo '<details><summary>' . esc_html($label) . '</summary>';
            foreach ([''=>__('Vis bare', 'reginor-lite'),'exclude_'=>__('Utelat', 'reginor-lite')] as $prefix=>$heading) {
                echo '<fieldset><legend>' . esc_html($heading) . '</legend>';
                foreach ($choices[$key] as $v=>$title) { echo '<label class="rnl-shortcode-choice"><input type="checkbox" name="rnl_sc[' . esc_attr($prefix . $key) . '][]" value="' . esc_attr((string) $v) . '"' . checked(in_array((string) $v, $options[$prefix.$key], true), true, false) . '> ' . esc_html($title) . '</label>'; }
                if (!$choices[$key]) { echo '<p>' . esc_html(__('Ingen valg er opprettet ennå.', 'reginor-lite')) . '</p>'; }
                echo '</fieldset>';
            }
            echo '</details>';
        }
        echo '</section><section class="rnl-panel"><h2>' . esc_html(__('Pris, klokkeslett og kurskvelder', 'reginor-lite')) . '</h2><p>' . esc_html(__('Grenser inkluderer verdien du skriver. Pris gjelder ordinær vist kurspris, ikke drop-in-pris, og beholder grunnlaget per person eller par. Klokkeslett gjelder vanlig oppstartstid. Antall gjelder ikke-avlyste kurskvelder.', 'reginor-lite')) . '</p>';
        foreach (['price_min'=>__('Minste pris (kr)', 'reginor-lite'),'price_max'=>__('Høyeste pris (kr)', 'reginor-lite'),'time_from'=>__('Tidligste oppstart', 'reginor-lite'),'time_until'=>__('Seneste oppstart', 'reginor-lite'),'sessions_min'=>__('Minst antall kurskvelder', 'reginor-lite'),'sessions_max'=>__('Maksimalt antall kurskvelder', 'reginor-lite')] as $key=>$label) {
            $value = is_scalar($input[$key] ?? '') ? (string) ($input[$key] ?? '') : '';
            echo '<p><label>' . esc_html($label) . ' <input name="rnl_sc[' . esc_attr($key) . ']" type="' . (str_starts_with($key,'time_') ? 'time' : 'number') . '"' . (str_starts_with($key,'time_') ? '' : ' min="0" step="' . (str_starts_with($key,'price_') ? '0.01' : '1') . '"') . ' value="' . esc_attr($value) . '"></label></p>';
        }
        echo '</section><button class="rnl-button">' . esc_html(__('Lag kortkode', 'reginor-lite')) . '</button></form><p>' . esc_html(__('Valgt periode begrenser hvilke perioder besøkeren kan velge; én periode vises om gangen. Sal, sted og tid følger kursets grunnoppsett. Instruktørutvalget gjelder gjenstående aktive kurskvelder. Kontroller den ferdige siden før annonsering.', 'reginor-lite')) . '</p></div>';
    }
}
