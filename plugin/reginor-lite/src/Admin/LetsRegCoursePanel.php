<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\LetsRegMapping;
use function RegiNor\Lite\translate as __;

final class LetsRegCoursePanel
{
    private static function roles(): array
    {
        return ['' => __('Ikke med på dette kurset', 'reginor-lite'), 'leader' => __('Fører', 'reginor-lite'),
            'follower' => __('Følger', 'reginor-lite'), 'open' => __('Uten rollefordeling', 'reginor-lite')];
    }

    private static function forms(): array
    {
        return ['single' => __('Enkeltpåmelding', 'reginor-lite'), 'pair' => __('Parpåmelding – per deltaker', 'reginor-lite')];
    }

    public static function summary(?array $mapping): void
    {
        if (!$mapping) { echo '<p>' . esc_html(__('Ingen LetsReg-kobling er valgt for dette kurset.', 'reginor-lite')) . '</p>'; return; }
        echo '<p><strong>' . esc_html($mapping['event_name'] ?: __('Arrangement uten navn', 'reginor-lite')) . '</strong> <small>#' . (int) $mapping['event_id'] . '</small></p>';
        echo '<ul>';
        foreach ($mapping['categories'] as $category) {
            echo '<li>' . esc_html(($category['name'] ?: __('Priskategori uten navn', 'reginor-lite')) . ' · ' . self::roles()[$category['role']] . ' · ' . self::forms()[$category['registration']]) . ' <small>#' . (int) $category['id'] . '</small></li>';
        }
        echo '</ul><p class="rnl-help">' . esc_html(sprintf(/* translators: %s: date and time of the source event check. */ __('Oppsettet bygger på arrangementskontrollen %s. Koblingen gir foreløpig ingen bekreftede kapasitetstall.', 'reginor-lite'), wp_date('d.m.Y H:i', $mapping['checked_at']))) . '</p>';
    }

    public static function render(int $id, array $state, array $submitted = [], bool $error = false): void
    {
        $mapping = $state['data']['letsreg_mapping'] ?? null;
        echo '<section class="rnl-panel" id="rnl-letsreg-fallback"><h3>' . esc_html(__('LetsReg-kobling', 'reginor-lite')) . '</h3>';
        self::summary($mapping);
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html(LetsRegMapping::canUse()
                ? __('Søk og valg av LetsReg-arrangement på denne siden krever JavaScript. Aktiver JavaScript i nettleseren og last siden på nytt.', 'reginor-lite')
                : __('Du har ikke tilgang til å søke etter eller koble kurs til LetsReg.', 'reginor-lite')) . '</p></section>'; return;
        }
        if (get_post_status($id) !== 'draft') {
            echo '<p>' . esc_html(__('Ta perioden tilbake til kladd før du endrer LetsReg-koblingen.', 'reginor-lite')) . '</p></section>'; return;
        }
        $source = LetsRegMapping::source();
        $url = admin_url('admin.php?page=rnl-letsreg&rnl_course=' . $id);
        echo '<p><a href="' . esc_url($url) . '">' . esc_html(__('Finn riktig arrangement hos LetsReg', 'reginor-lite')) . '</a></p>';
        echo '<p class="rnl-help">' . esc_html(__('Lagre eventuelle kursendringer før du åpner tilkoblingssiden. Der kan du hente riktig arrangement og gå tilbake hit.', 'reginor-lite')) . '</p>';
        if ($source) {
            $sticky = $error && ($submitted['command'] ?? '') === 'preview_letsreg' && (string) ($submitted['id'] ?? '') === (string) $id
                && ($submitted['verification_id'] ?? '') === $source['verification_id'] && is_array($submitted['data']['categories'] ?? null);
            $selected = $sticky ? $submitted['data']['categories'] : (($mapping['event_id'] ?? 0) === $source['event_id'] ? array_column($mapping['categories'], null, 'id') : []);
            echo '<details' . ($sticky ? ' open' : '') . '><summary>' . esc_html(__('Velg priskategorier for dette kurset', 'reginor-lite')) . '</summary>';
            echo '<p><strong>' . esc_html($source['event_name'] ?: __('Arrangement uten navn', 'reginor-lite')) . '</strong> <small>#' . (int) $source['event_id'] . '</small></p>';
            echo '<p>' . esc_html(__('Velg bare kategoriene som gjelder dette kurset. Rolle sier hvem plassen er for. Påmeldingsform skiller enkeltpåmelding fra parpåmelding. Ved parpåmelding gjelder prisen én deltaker; partneren registreres separat hos LetsReg.', 'reginor-lite')) . '</p>';
            self::start($id, $state, 'link');
            self::hidden('event_id', (string) $source['event_id']); self::hidden('verification_id', $source['verification_id']);
            self::categoryFields($source, $selected);
            if (!array_filter($source['event']['prices'], static fn ($price) => $price['active'])) { echo '<p>' . esc_html(__('Arrangementet har ingen aktive priskategorier å velge.', 'reginor-lite')) . '</p>'; }
            echo '<p class="rnl-help">' . esc_html(__('Eksempel: Velg «Fører» og «Parpåmelding – per deltaker» for en parkategori som gjelder én fører. Kontroller valgene før lagring. Kursprisen og påmeldingslenken endres ikke av denne koblingen.', 'reginor-lite')) . '</p>';
            echo '<button class="rnl-button"' . (!array_filter($source['event']['prices'], static fn ($price) => $price['active']) || !$source['event']['active'] || $source['event']['isCancelled'] ? ' disabled' : '') . '>' . esc_html(__('Forhåndsvis LetsReg-koblingen', 'reginor-lite')) . '</button></form></details>';
            if (!$source['event']['active'] || $source['event']['isCancelled']) { echo '<p class="rnl-notice">' . esc_html(__('Arrangementet er inaktivt eller avlyst. Hent et aktivt arrangement før du kobler kurset.', 'reginor-lite')) . '</p>'; }
        } else {
            echo '<p>' . esc_html(__('Hent arrangementet på tilkoblingssiden først. En kontroll kan brukes i 15 minutter; deretter må arrangementet hentes på nytt før en kobling kan lagres.', 'reginor-lite')) . '</p>';
        }
        if ($mapping) {
            echo '<details><summary>' . esc_html(__('Fjern LetsReg-koblingen', 'reginor-lite')) . '</summary><p>' . esc_html(__('Bare koblingen fjernes. Kurset og påmeldingslenken beholdes. Du får se endringen før du bekrefter.', 'reginor-lite')) . '</p>';
            self::start($id, $state, 'remove');
            echo '<button class="rnl-button rnl-button-secondary">' . esc_html(__('Forhåndsvis fjerning', 'reginor-lite')) . '</button></form></details>';
        }
        echo '</section>';
    }

    public static function categoryFields(array $source, array $selected = [], string $prefix = 'rnl-category-'): void
    {
        // A saved mapping omits excluded categories. Do not automatically add them back.
        $autofill = $selected === [];
        echo '<div data-category-suggestions><button type="button" hidden data-fill-category-suggestions class="rnl-button rnl-button-secondary">' . esc_html(__('Fyll ut forslag for kategorier uten rolle', 'reginor-lite')) . '</button><p data-category-suggestion-status class="rnl-help" role="status"></p>';
        if (!$autofill) {
            echo '<p class="rnl-help">' . esc_html(__('Tidligere kategorivalg er beholdt. Forslagene nedenfor er fortsatt tilgjengelige. Bruk «Fyll ut forslag for kategorier uten rolle» for å ta med de resterende kategoriene som har et tydelig forslag.', 'reginor-lite')) . '</p>';
        }
        foreach ($source['event']['prices'] as $price) {
            $pid = $price['id']; $name = $price['name'] ?: __('Priskategori uten navn', 'reginor-lite');
            $proposal = $price['active'] ? LetsRegCategorySuggestion::fromName($price['name']) : null;
            echo '<fieldset class="rnl-field-section rnl-category-card"' . ($proposal ? ' data-category-role="' . esc_attr($proposal['role']) . '" data-category-registration="' . esc_attr($proposal['registration']) . '"' : '') . '><legend>' . esc_html($name) . ' <small>#' . (int) $pid . '</small></legend>';
            if (!$price['active']) { echo '<p>' . esc_html(__('Inaktiv hos LetsReg – kan ikke velges.', 'reginor-lite')) . '</p></fieldset>'; continue; }
            $hintId = $prefix . $pid . '-suggestion';
            $hint = $proposal ? sprintf(
                /* translators: 1: suggested dance role, 2: suggested registration form. */
                __('Forslag fra kategorinavnet: %1$s · %2$s. Kontroller at kategorien gjelder kurset. Du kan endre feltene eller velge «Ikke med på dette kurset».', 'reginor-lite'),
                self::roles()[$proposal['role']], self::forms()[$proposal['registration']]
            ) : __('Kategorinavnet gir ikke et entydig forslag. Velg rolle og påmeldingsform selv hvis kategorien gjelder dette kurset.', 'reginor-lite');
            echo '<p class="rnl-help" id="' . esc_attr($hintId) . '">' . esc_html($hint) . '</p>';
            echo '<div class="rnl-field-grid">';
            foreach (['role' => [__('Rolle', 'reginor-lite'), self::roles(), ''], 'registration' => [__('Påmeldingsform', 'reginor-lite'), self::forms(), 'single']] as $key => [$label, $options, $default]) {
                $value = is_string($selected[$pid][$key] ?? null) ? $selected[$pid][$key] : ($autofill ? ($proposal[$key] ?? $default) : $default);
                $field = $prefix . $pid . '-' . $key;
                echo '<p class="rnl-field-card"><label for="' . esc_attr($field) . '">' . esc_html($label) . '</label><select id="' . esc_attr($field) . '" aria-describedby="' . esc_attr($hintId) . '" name="data[categories][' . (int) $pid . '][' . esc_attr($key) . ']">';
                foreach ($options as $option => $text) { echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($text) . '</option>'; }
                echo '</select></p>';
            }
            echo '</div></fieldset>';
        }
        echo '</div>';
    }

    private static function hidden(string $name, string $value): void { echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }

    private static function start(int $id, array $state, string $action): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=reginor-lite&period=' . $state['data']['period_id'] . '&group=' . $id . '#rnl-letsreg-course')) . '">';
        wp_nonce_field('rnl_course_command');
        foreach (['command' => 'preview_letsreg', 'id' => (string) $id, 'version' => (string) $state['version'], 'mapping_action' => $action] as $name => $value) { self::hidden($name, $value); }
    }
}
