<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\{LevelArchiveStore, WebIdentity, RichText};
use RegiNor\Lite\Frontend\LevelArchive;
use function RegiNor\Lite\translate as __;

final class LevelArchiveSettings
{
    public static function render(int $id, string $name, array $submitted = []): void
    {
        $state = LevelArchiveStore::read($id);
        echo '<details class="rnl-level-settings"' . (($submitted['command'] ?? '') === 'save_level_archive' && (int) ($submitted['id'] ?? 0) === $id ? ' open' : '') . '><summary>' . esc_html(__('Nivåarkiv på nettsiden', 'reginor-lite')) . '</summary><p>' . esc_html(__('Vis kurs for dette nivået sammen med egne tekster, spørsmål og artikler. Eksisterende nivåarkiver endres ikke.', 'reginor-lite')) . '</p>';
        if (!LevelArchive::prettyEnabled()) { echo '<p class="rnl-notice">' . esc_html(__('Pene nivåadresser er ikke tilgjengelige. Kontroller permalenker og om kursrekke/niva eller periodens adresse niva allerede er i bruk. En vanlig spørreadresse brukes inntil konflikten er avklart.', 'reginor-lite')) . '</p>'; }
        foreach (['default' => ['native_name' => __('Originalspråk og felles valg', 'reginor-lite')]] + WebIdentity::languages() as $language => $info) {
            $entry = LevelArchiveStore::entry($id, $language);
            if (!$entry['slug']) { $entry['slug'] = sanitize_title($name); $entry['title'] = $name; }
            $values = $entry + $state;
            $failed = ($submitted['command'] ?? '') === 'save_level_archive' && (int) ($submitted['id'] ?? 0) === $id && ($submitted['language'] ?? '') === $language;
            if ($failed) { $values = array_replace($values, array_filter(is_array($submitted['data'] ?? null) ? $submitted['data'] : [], 'is_scalar')); $values['enabled'] = !empty($submitted['data']['enabled']); }
            echo '<details' . ($failed ? ' open' : '') . '><summary>' . esc_html($info['native_name'] ?? $language) . '</summary><form method="post" class="rnl-edit-form" action="' . esc_url(admin_url('admin.php?page=rnl-resources')) . '">';
            wp_nonce_field('rnl_course_command');
            foreach (['command' => 'save_level_archive', 'id' => $id, 'version' => $failed ? ($submitted['version'] ?? $state['version']) : $state['version'], 'language' => $language] as $key => $value) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">'; }
            foreach (['title' => __('Arkivtittel', 'reginor-lite'), 'slug' => __('Adresse under /kursrekke/niva/', 'reginor-lite'), 'description' => __('Kort beskrivelse for søk og deling', 'reginor-lite')] as $key => $label) {
                echo '<label>' . esc_html($label) . '<input type="text" name="data[' . esc_attr($key) . ']" value="' . esc_attr($values[$key]) . '"' . ($key !== 'description' ? ' required' : '') . '></label>';
            }
            foreach (['intro' => __('Introduksjon over kursene', 'reginor-lite'), 'extra' => __('Ekstra informasjon etter kursene', 'reginor-lite')] as $key => $label) {
                $editorId = 'rnl-archive-' . $id . '-' . $language . '-' . $key;
                echo '<div class="rnl-rich-field"><label for="' . esc_attr($editorId) . '">' . esc_html($label) . '</label><textarea data-rnl-rich-text rows="8" id="' . esc_attr($editorId) . '" name="data[' . esc_attr($key) . ']">' . esc_textarea((string) $values[$key]) . '</textarea></div>';
            }
            if ($language === 'default') {
                echo '<label><input type="checkbox" name="data[enabled]" value="1"' . checked((bool) $values['enabled'], true, false) . '> ' . esc_html(__('Publiser nivåarkivet', 'reginor-lite')) . '</label><p class="rnl-help">' . esc_html(__('Arkivet kan være synlig selv om nivået ikke har aktuelle kurs. Publisering her gjelder bare nivåarkivet.', 'reginor-lite')) . '</p>';
                foreach (['faq' => __('Stikkord for spørsmål (FAQ)', 'reginor-lite'), 'articles' => __('Stikkord for artikler', 'reginor-lite')] as $key => $label) {
                    echo '<label>' . esc_html($label) . '<select name="data[' . esc_attr($key) . ']">';
                    echo '<option value="">' . esc_html(__('Ikke vis denne seksjonen', 'reginor-lite')) . '</option>';
                    foreach (self::sources() as $value => $text) { echo '<option value="' . esc_attr($value) . '"' . selected($values[$key], $value, false) . '>' . esc_html($text) . '</option>'; }
                    if ($values[$key] && !LevelArchiveStore::source((string) $values[$key])) { echo '<option selected value="' . esc_attr($values[$key]) . '">' . esc_html(__('Tidligere valg er utilgjengelig – velg på nytt', 'reginor-lite')) . '</option>'; }
                    echo '</select></label>';
                }
                echo '<p class="rnl-help">' . esc_html(__('Velg innholdstype, taksonomi og stikkord fra listen. Bare publisert innhold på valgt språk vises. Oversettelser kobles via WPML; manglende oversettelser utelates.', 'reginor-lite')) . '</p>';
                echo '<label>' . esc_html(__('Antall per seksjon', 'reginor-lite')) . '<input type="number" min="1" max="12" name="data[limit]" value="' . (int) $values['limit'] . '"></label><label>' . esc_html(__('Sortering', 'reginor-lite')) . '<select name="data[order]">';
                foreach (['date' => __('Nyeste først', 'reginor-lite'), 'title' => __('Alfabetisk', 'reginor-lite')] as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($values['order'], $value, false) . '>' . esc_html($label) . '</option>'; }
                echo '</select></label>';
            }
            echo '<p><button class="rnl-button" type="submit">' . esc_html(__('Lagre nivåarkiv', 'reginor-lite')) . '</button></p></form></details>';
        }
        if (LevelArchiveStore::publicData($id)) { echo '<p><a href="' . esc_url(LevelArchive::url($id)) . '" target="_blank" rel="noopener">' . esc_html(__('Se nivåarkivet', 'reginor-lite')) . '</a></p>'; }
        echo '</details>';
    }
    private static function sources(): array
    {
        $result = [];
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if ($type->name === 'attachment' || str_starts_with($type->name, 'rnl_')) { continue; }
            foreach (get_object_taxonomies($type->name, 'objects') as $taxonomy) {
                if (!$taxonomy->show_ui) { continue; }
                $terms = get_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false]);
                foreach (is_wp_error($terms) ? [] : $terms as $term) {
                    $result[$type->name . ':' . $taxonomy->name . ':' . $term->term_id] = $type->label . ' · ' . $taxonomy->label . ' · ' . $term->name;
                }
            }
        }
        return $result;
    }
}
