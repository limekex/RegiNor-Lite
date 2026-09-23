<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\TecBridge;
use RegiNor\Lite\Infrastructure\TecDefaults;
use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\ContentTypes;
use RegiNor\Lite\Frontend\Catalog;
use RegiNor\Lite\Frontend\PublicSite;
use function RegiNor\Lite\translate as __;

final class CalendarSettings
{
    /** Navigation to the source period; hidden periods get no public link. */
    public static function periodActions(int $period): array
    {
        if (get_post_type($period) !== ContentTypes::TYPES['period'] || !current_user_can('edit_post', $period)) { return []; }
        $actions = ['rnl_manage_period' => '<a href="' . esc_url(add_query_arg(['page' => 'reginor-lite', 'period' => $period, 'step' => 'courses'], admin_url('admin.php'))) . '">' . esc_html(__('Administrer kursperioden', 'reginor-lite')) . '</a>'];
        $catalog = (new Catalog())->read();
        if (isset($catalog['periods'][$period]) && ($url = PublicSite::url(null, ['rnl_period' => $period]))) {
            $actions['rnl_view_period'] = '<a href="' . esc_url($url) . '">' . esc_html(__('Se kursrekken på nettsiden', 'reginor-lite')) . '</a>';
        }
        return $actions;
    }

    public static function eventActions(array $actions, \WP_Post $post): array
    {
        $period = TecBridge::owner((int) $post->ID);
        return $period ? array_merge($actions, self::periodActions($period)) : $actions;
    }

    public static function save(): void
    {
        check_admin_referer('rnl_calendar_settings');
        try { TecDefaults::update(wp_unslash($_POST)); }
        catch (\InvalidArgumentException $error) { wp_die(esc_html($error->getMessage()), '', ['response' => 400, 'back_link' => true]); }
        TecBridge::sync();
        wp_safe_redirect(admin_url('admin.php?page=rnl-site&calendar_updated=1#rnl-calendar'), 303); exit;
    }

    public static function enqueue(): void
    {
        wp_enqueue_media();
        $file = dirname(__DIR__, 2) . '/assets/calendar-settings.js';
        wp_enqueue_script('rnl-calendar-settings', plugins_url('assets/calendar-settings.js', dirname(__DIR__, 2) . '/reginor-lite.php'), ['media-views'], (string) filemtime($file), true);
        wp_localize_script('rnl-calendar-settings', 'rnlCalendarSettings', [
            'title' => __('Velg standardbilde til kalenderen', 'reginor-lite'),
            'select' => __('Bruk dette bildet', 'reginor-lite'),
            'selected' => __('Bildet er valgt. Lagre kalendervalg for å bruke det.', 'reginor-lite'),
            'removed' => __('Bildet er fjernet fra valget. Lagre kalendervalg for å bekrefte.', 'reginor-lite'),
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) { return; }
        echo '<section id="rnl-calendar" class="rnl-panel"><h2>' . esc_html(__('Arrangementskalender', 'reginor-lite')) . '</h2><p>' . esc_html(__('Velg en felles kategori og et bilde for kursperiodene i The Events Calendar. Valgene gjelder nye og eksisterende kalenderoppføringer fra RegiNor.', 'reginor-lite')) . '</p>';
        if (!TecBridge::available() || !taxonomy_exists(TecDefaults::TAXONOMY)) {
            echo '<p>' . esc_html(__('Aktiver The Events Calendar før du velger kalenderkategori og bilde.', 'reginor-lite')) . '</p></section>'; return;
        }
        if (isset($_GET['calendar_updated'])) { echo '<p class="rnl-notice" role="status">' . esc_html(__('Kalendervalgene er lagret. Synkroniseringsstatus vises på hver kursperiode.', 'reginor-lite')) . '</p>'; }
        $values = TecDefaults::read();
        echo '<form method="post">'; wp_nonce_field('rnl_calendar_settings');
        echo '<input type="hidden" name="rnl_calendar_settings" value="1"><div class="rnl-field-card"><label for="rnl-calendar-category">' . esc_html(__('Fast arrangementskategori', 'reginor-lite')) . '</label><p class="rnl-help" id="rnl-calendar-category-help">' . esc_html(__('Velg for eksempel «Kursperioder». Dette er kalenderens egne kategorier, ikke kategorier for blogginnlegg.', 'reginor-lite')) . '</p>';
        $select = wp_dropdown_categories(['taxonomy' => TecDefaults::TAXONOMY, 'hide_empty' => false, 'hierarchical' => true, 'orderby' => 'name', 'name' => 'category_id', 'id' => 'rnl-calendar-category', 'selected' => $values['category_id'], 'show_option_none' => __('Ingen fast kategori', 'reginor-lite'), 'option_none_value' => '0', 'echo' => false]);
        echo str_replace('<select ', '<select aria-describedby="rnl-calendar-category-help" ', $select);
        echo '<p><a href="' . esc_url(admin_url('edit-tags.php?taxonomy=tribe_events_cat&post_type=tribe_events')) . '">' . esc_html(__('Administrer kalenderkategorier', 'reginor-lite')) . '</a></p></div>';
        echo '<div class="rnl-field-card"><h3>' . esc_html(__('Standard fremhevet bilde', 'reginor-lite')) . '</h3><p class="rnl-help" id="rnl-calendar-image-help">' . esc_html(__('Velg for eksempel et dansebilde eller et felles bilde for kursperiodene. Det vises der kalenderens visning bruker arrangementsbilder. Valgfritt.', 'reginor-lite')) . '</p>';
        echo '<input type="hidden" id="rnl-calendar-image" name="image_id" value="' . (int) $values['image_id'] . '"><div id="rnl-calendar-image-preview">';
        if ($values['image_id']) { echo wp_get_attachment_image($values['image_id'], 'medium', false, ['class' => 'rnl-image-preview']); }
        echo '</div><p><button type="button" class="rnl-button rnl-button-secondary" id="rnl-calendar-image-choose" aria-describedby="rnl-calendar-image-help">' . esc_html(__('Velg eller bytt bilde', 'reginor-lite')) . '</button> <button type="button" class="rnl-button rnl-button-secondary" id="rnl-calendar-image-remove"' . ($values['image_id'] ? '' : ' hidden') . '>' . esc_html(__('Fjern bilde', 'reginor-lite')) . '</button></p><p id="rnl-calendar-image-status" role="status"></p></div>';
        echo '<p>' . esc_html(__('Kalenderdeling aktiveres fortsatt på hver kursperiode. Når du lagrer, oppdateres kalenderoppføringene som allerede er synlige. Fjerner du kategori eller bilde her, fjernes det også fra disse oppføringene.', 'reginor-lite')) . '</p>';
        submit_button(__('Lagre kalendervalg', 'reginor-lite'));
        echo '</form>';
        echo '<h3>' . esc_html(__('Kursperioder med kalenderdeling', 'reginor-lite')) . '</h3><p>' . esc_html(__('Også fremtidige kursperioder vises i kalenderen før kursstart, når de er publisert og synlighetsvinduet er åpent. Kalenderoppføringen lenker til den aktuelle kursrekken.', 'reginor-lite')) . '</p>';
        $periods = array_filter((new CourseRepository())->listing('period'), static fn ($state) => !empty($state['data']['calendar_enabled']));
        if (!$periods) {
            echo '<p>' . esc_html(__('Ingen kursperioder har kalenderdeling ennå. Slå på «Vis hele kursperioden i arrangementskalenderen» i periodeoppsettet.', 'reginor-lite')) . '</p>';
        } else {
            echo '<div class="rnl-scroll"><table class="widefat striped"><thead><tr><th scope="col">' . esc_html(__('Kursperiode', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Kalenderstatus', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Lenker', 'reginor-lite')) . '</th></tr></thead><tbody>';
            foreach ($periods as $id => $state) {
                echo '<tr><th scope="row">' . esc_html($state['data']['title']) . '</th><td>' . esc_html(TecBridge::status((int) $id)) . '</td><td>' . implode('<br>', self::periodActions((int) $id)) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }
}
