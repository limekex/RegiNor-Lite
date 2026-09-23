<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\WebIdentity;
use RegiNor\Lite\Frontend\PublicRoutes;
use function RegiNor\Lite\translate as __;

final class SharingSettings
{
    public static function boot(): void
    {
        add_action('admin_post_rnl_sharing', [self::class, 'save']);
        add_action('admin_enqueue_scripts', static function (): void {
            if (!in_array($_GET['page'] ?? '', ['reginor-lite', 'rnl-site'], true)) { return; }
            if (current_user_can('upload_files')) { wp_enqueue_media(); }
            wp_enqueue_script('rnl-sharing', plugins_url('assets/sharing.js', dirname(__DIR__, 2) . '/reginor-lite.php'), ['wp-i18n'], (string) filemtime(dirname(__DIR__, 2) . '/assets/sharing.js'), true);
            wp_set_script_translations('rnl-sharing', 'reginor-lite', dirname(__DIR__, 2) . '/languages');
        });
    }
    public static function save(): void
    {
        $raw = wp_unslash($_POST); $id = isset($raw['object']) && is_scalar($raw['object']) ? absint($raw['object']) : -1;
        check_admin_referer('rnl_sharing_' . $id);
        try {
            if (!$id) {
                if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Du har ikke tilgang.', 'reginor-lite'), 403); }
                $image = filter_var($raw['image_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($image === false || ($image && !WebIdentity::image($image))) { throw new \InvalidArgumentException(__('Velg et tilgjengelig bilde fra mediebiblioteket.', 'reginor-lite')); }
                update_option('rnl_sharing_image', $image, false);
                update_option('rnl_pretty_urls', isset($raw['pretty']), false);
                $url = admin_url('admin.php?page=rnl-site&sharing_saved=1#rnl-sharing');
            } else {
                $state = (new \RegiNor\Lite\Infrastructure\CourseRepository())->get($id);
                $language = is_string($raw['language'] ?? null) ? $raw['language'] : '';
                WebIdentity::save($id, (int) ($raw['version'] ?? -1), $language, $raw);
                $query = ['page' => 'reginor-lite', 'period' => $state['data']['period_id'] ?? $id, 'step' => isset($state['data']['period_id']) ? 'courses' : 'period', 'sharing_saved' => 1, 'rnl_web_language' => $language];
                if (isset($state['data']['period_id'])) { $query['group'] = $id; }
                $url = add_query_arg($query, admin_url('admin.php')) . '#rnl-sharing';
            }
        } catch (\Throwable $error) { wp_die(esc_html($error->getMessage()), '', ['response' => in_array($error->getCode(), [403, 409], true) ? $error->getCode() : 400, 'back_link' => true]); }
        wp_safe_redirect($url, 303); exit;
    }
    public static function render(int $id = 0): void
    {
        if ($id ? !current_user_can('edit_post', $id) : !current_user_can('manage_options')) { return; }
        $language = is_string($_GET['rnl_web_language'] ?? null) ? $_GET['rnl_web_language'] : 'default';
        $languages = WebIdentity::languages();
        if ($language !== 'default' && !isset($languages[$language])) { $language = 'default'; }
        if ($id) { WebIdentity::ensure($id); }
        $entry = $id ? WebIdentity::entry($id, $language) : ['image_id' => (int) get_option('rnl_sharing_image', 0)];
        echo '<details id="rnl-sharing" class="rnl-panel"' . (isset($_GET['sharing_saved']) ? ' open' : '') . '><summary>' . esc_html(__('Adresse og deling', 'reginor-lite')) . '</summary><p class="rnl-help">' . esc_html($id ? __('Valgfritt. Navn og beskrivelse brukes automatisk. Her kan du tilpasse adressen og hvordan kurset eller perioden deles. Endringene påvirker ikke kursdatoer eller publisering.', 'reginor-lite') : __('Velg et felles delingsbilde. Kurs og perioder kan ha egne bilder. Dette valget er uavhengig av kalenderens standardbilde.', 'reginor-lite')) . '</p>';
        if (isset($_GET['sharing_saved'])) { echo '<p class="rnl-notice" role="status">' . esc_html(__('Delingsvalgene er lagret.', 'reginor-lite')) . '</p>'; }
        if ($id && $languages) {
            echo '<p>' . esc_html(__('Rediger språk:', 'reginor-lite')) . ' ';
            foreach (['default' => ['native_name' => __('Standard', 'reginor-lite')]] + $languages as $code => $info) {
                echo '<a href="' . esc_url(add_query_arg('rnl_web_language', $code)) . '#rnl-sharing"' . ($language === $code ? ' aria-current="true"' : '') . '>' . esc_html($info['native_name'] ?? $code) . '</a> ';
            }
            echo '</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-rnl-sharing>'; wp_nonce_field('rnl_sharing_' . $id);
        echo '<input type="hidden" name="action" value="rnl_sharing"><input type="hidden" name="object" value="' . $id . '">';
        if ($id) {
            $state = get_post_meta($id, \RegiNor\Lite\Infrastructure\ContentTypes::META, true);
            $pid = (int) ($state['data']['period_id'] ?? $id); $gid = $pid === $id ? null : $id;
            echo '<input type="hidden" name="version" value="' . WebIdentity::read($id)['version'] . '"><input type="hidden" name="language" value="' . esc_attr($language) . '">';
            foreach (['slug' => [__('Siste del av adressen', 'reginor-lite'), __('Eksempel: salsa-nybegynner-mandag. Gamle adresser videresendes automatisk når du endrer denne.', 'reginor-lite')], 'title' => [__('Tittel ved deling', 'reginor-lite'), __('La feltet stå tomt for å bruke kursnavn og periodenavn.', 'reginor-lite')], 'description' => [__('Kort beskrivelse ved deling', 'reginor-lite'), __('Eksempel: Lær salsa på mandager på Orient. Tomt felt bruker den vanlige kursbeskrivelsen.', 'reginor-lite')]] as $key => [$label, $help]) {
                echo '<div class="rnl-field-card"><label for="rnl-sharing-' . $key . '">' . esc_html($label) . '</label><p class="rnl-help" id="rnl-sharing-' . $key . '-help">' . esc_html($help) . '</p>';
                if ($key === 'description') { echo '<textarea id="rnl-sharing-description" name="description" rows="3" aria-describedby="rnl-sharing-description-help">' . esc_textarea($entry[$key]) . '</textarea>'; }
                else { echo '<input id="rnl-sharing-' . $key . '" name="' . $key . '" value="' . esc_attr($entry[$key]) . '" aria-describedby="rnl-sharing-' . $key . '-help"' . ($key === 'slug' ? ' required' : '') . '>'; }
                echo '</div>';
            }
            echo '<div class="rnl-field-card"><strong>' . esc_html(__('Forhåndsvisning av lagret adresse', 'reginor-lite')) . '</strong><p class="rnl-sharing-url">' . esc_html(PublicRoutes::url($pid, $gid, $language === 'default' ? null : $language)) . '</p><strong data-rnl-share-title data-default="' . esc_attr($state['data']['title']) . '">' . esc_html($entry['title'] ?: $state['data']['title']) . '</strong><p data-rnl-share-description>' . esc_html($entry['description']) . '</p><p class="rnl-help">' . esc_html(__('Adressen er tilgjengelig når innholdet er publisert og synlig. Forhåndsvisningen av teksten oppdateres mens du skriver.', 'reginor-lite')) . '</p></div>';
        } else {
            echo '<div class="rnl-field-card"><label><input type="checkbox" name="pretty" value="1"' . checked((bool) get_option('rnl_pretty_urls', true), true, false) . '> ' . esc_html(__('Bruk lesbare kursadresser under /kursrekke/', 'reginor-lite')) . '</label><p class="rnl-help">' . esc_html(__('Krever lesbare permalenker i WordPress. Hvis en side allerede bruker /kursrekke/, beholdes den og vanlige kurslenker brukes inntil konflikten er løst.', 'reginor-lite')) . '</p></div>';
        }
        echo '<div class="rnl-field-card"><label>' . esc_html(__('Delingsbilde', 'reginor-lite')) . '</label><p class="rnl-help">' . esc_html(__('La bildet være tomt for å bruke periodens eller nettstedets standardbilde.', 'reginor-lite')) . '</p><input type="hidden" name="image_id" value="' . (int) $entry['image_id'] . '"><div data-rnl-image-preview>';
        if ($entry['image_id']) { echo wp_get_attachment_image($entry['image_id'], 'medium', false, ['class' => 'rnl-image-preview']); }
        echo '</div><div class="rnl-actions">';
        if (current_user_can('upload_files')) { echo '<button type="button" class="rnl-button rnl-button-secondary" data-rnl-image-choose>' . esc_html(__('Velg bilde', 'reginor-lite')) . '</button>'; }
        echo '<button type="button" class="rnl-button rnl-button-secondary" data-rnl-image-remove>' . esc_html(__('Bruk standardbilde', 'reginor-lite')) . '</button></div><p role="status" data-rnl-image-status></p></div>';
        submit_button(__('Lagre adresse og deling', 'reginor-lite')); echo '</form></details>';
    }
}
