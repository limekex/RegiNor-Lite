<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\LetsRegConnection;
use RegiNor\Lite\Infrastructure\LetsRegMapping;
use function RegiNor\Lite\translate as __;

/** Inline course linking with delegated access; provider reads and local writes are separate. */
final class LetsRegCoursePicker
{
    public static function boot(): void
    {
        add_action('wp_ajax_rnl_letsreg_course', static function (): void {
            nocache_headers();
            try { wp_send_json_success(self::dispatch(wp_unslash($_POST))); }
            catch (\InvalidArgumentException $error) { wp_send_json_error(['message' => $error->getMessage()], 400); }
            catch (\RuntimeException $error) {
                $code = in_array($error->getCode(), [400, 403, 409, 429, 503], true) ? $error->getCode() : 400;
                wp_send_json_error(['message' => $error->getMessage()], $code);
            }
        });
    }

    public static function dispatch(array $input): array
    {
        LetsRegMapping::authorize();
        if (!is_string($input['nonce'] ?? null) || !wp_verify_nonce($input['nonce'], 'rnl_letsreg_course')) {
            throw new \RuntimeException(__('Skjemaet er utløpt. Behold endringene dine og åpne kurset i en ny fane.', 'reginor-lite'), 403);
        }
        $repo = new CourseRepository(); $id = CourseActions::integer($input['id'] ?? '');
        $import = ($input['mode'] ?? '') === 'import';
        if ($import) { LetsRegMapping::authorizeImport(); }
        $state = $repo->get($id, $import ? 'period' : 'group');
        $courseData = $import ? $repo->newGroupDefaults($id) : $state['data'];
        $periodId = $import ? $id : $state['data']['period_id'];
        $operation = CourseActions::scalar($input, 'operation');
        if ($import && in_array($operation, ['preview_import', 'confirm_import', 'confirm_import_batch'], true)) { return LetsRegImport::dispatch($input, $repo, $state); }
        if ($operation === 'search' || $operation === 'select') {
            $result = $operation === 'search'
                ? LetsRegConnection::checkCourse(null, ['query' => CourseActions::scalar($input, 'query'), 'offset' => CourseActions::integer($input['offset'] ?? '0')])
                : LetsRegConnection::checkCourse(CourseActions::integer($input['event_id'] ?? ''));
            if ($result['state'] !== 'verified') {
                throw new \RuntimeException(LetsRegPage::message($result['error'] ?? '') . ' ' . __('Valgene dine er beholdt. Prøv igjen senere.', 'reginor-lite'), 503);
            }
            if ($operation === 'search') {
                return \RegiNor\Lite\Infrastructure\LetsRegCourseSuggestions::search($result['search'], $repo->get($periodId, 'period')['data']);
            }
            $source = LetsRegMapping::source();
            if (!$source || !$source['event']['active'] || $source['event']['isCancelled']) {
                throw new \InvalidArgumentException(__('Arrangementet er inaktivt eller avlyst. Velg et annet treff.', 'reginor-lite'));
            }
            if (!array_filter($source['event']['prices'], static fn ($price) => $price['active'])) {
                throw new \InvalidArgumentException(__('Arrangementet har ingen aktive priskategorier. Velg et annet treff.', 'reginor-lite'));
            }
            $mapping = $state['data']['letsreg_mapping'] ?? null;
            $selected = ($mapping['event_id'] ?? null) === $source['event_id'] && ($mapping['organizer_id'] ?? null) === $source['organizer_id']
                && ($mapping['affiliate_id'] ?? null) === $source['affiliate_id'] ? array_column($mapping['categories'], null, 'id') : [];
            ob_start(); LetsRegCoursePanel::categoryFields($source, $selected, 'rnl-inline-category-'); $html = ob_get_clean();
            return ['event_id' => $source['event_id'], 'event_name' => $source['event_name'], 'verification_id' => $source['verification_id'], 'html' => $html, 'event_url' => $source['event']['event_url'] ?? null,
                'description' => $source['event']['description'] ?? '',
                'description_template' => $parsed = \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::parse($source['event']['description'] ?? ''),
                'description_template_message' => LetsRegTemplateHelp::message($parsed),
                'receipt' => $import ? \RegiNor\Lite\Infrastructure\LetsRegImportSource::issue() : null,
                'suggestions' => \RegiNor\Lite\Infrastructure\LetsRegCourseSuggestions::from($source['event'], $courseData, $repo->get($periodId, 'period')['data'],
                    array_map(static fn ($row) => $row['data'], $repo->listing('level')))];
        }
        if ($import || !in_array($operation, ['save', 'remove'], true)) { throw new \InvalidArgumentException(__('Ukjent handling.', 'reginor-lite')); }
        $mapping = null;
        if ($operation === 'save') {
            if (!is_array($input['data']['categories'] ?? null)) { throw new \InvalidArgumentException(__('Velg kategoriene som gjelder kurset.', 'reginor-lite')); }
            $mapping = LetsRegMapping::build(CourseActions::integer($input['event_id'] ?? ''), CourseActions::scalar($input, 'verification_id'), $input['data']['categories']);
        }
        $useUrl = $operation === 'save' && ($input['use_api_url'] ?? '') === '1';
        $saved = $repo->saveLetsRegMapping($id, CourseActions::integer($input['version'] ?? ''), $mapping, $useUrl);
        ob_start(); LetsRegCoursePanel::summary($saved['data']['letsreg_mapping']); $html = ob_get_clean();
        return ['version' => $saved['version'], 'html' => $html, 'linked' => $mapping !== null,
            'registration_url' => $useUrl ? $saved['data']['registration_url'] : null,
            'message' => $useUrl ? __('Koblingen og påmeldingslenken er lagret. Kursets publiseringsstatus er beholdt.', 'reginor-lite') : __('LetsReg-koblingen er lagret. Øvrige kursfelt og publisering er uendret.', 'reginor-lite')];
    }

    public static function render(int $id, array $state, bool $import = false): void
    {
        echo '<section class="rnl-panel" id="rnl-letsreg-course"><h3>' . esc_html(__('Påmelding hos LetsReg', 'reginor-lite')) . '</h3>';
        if (!LetsRegMapping::canUse() || !current_user_can('edit_post', $id) || ($import && !LetsRegMapping::canImport())) { LetsRegCoursePanel::summary($state['data']['letsreg_mapping'] ?? null); echo '</section>'; return; }
        echo '<div hidden data-rnl-letsreg-picker data-mode="' . ($import ? 'import' : 'course') . '" data-id="' . (int) $id . '" data-version="' . (int) $state['version'] . '" data-registration-url="' . esc_attr($state['data']['registration_url']) . '" data-nonce="' . esc_attr(wp_create_nonce('rnl_letsreg_course')) . '" data-url="' . esc_url(admin_url('admin-ajax.php')) . '">';
        echo '<div data-saved-summary>'; LetsRegCoursePanel::summary($state['data']['letsreg_mapping'] ?? null); echo '</div>';
        echo '<p class="rnl-help">' . esc_html($import ? __('Velg arrangement og priskategorier. Forslag fra LetsReg fylles inn i kursoppsettet nedenfor. Kontroller sal, datoer og pris før du oppretter utkastet.', 'reginor-lite') : __('Valgfritt for lokale kurs: Egen påmeldingslenke og Kun drop-in trenger ingen LetsReg-kobling. For LetsReg-kurs finner du arrangementet og lagrer koblingen her, også etter publisering.', 'reginor-lite')) . '</p>';
        echo '<details data-editor' . (empty($state['data']['letsreg_mapping']) ? ' open' : '') . '><summary>' . esc_html(__('Velg eller bytt arrangement', 'reginor-lite')) . '</summary><form data-search-form><p><label for="rnl-letsreg-inline-query">' . esc_html(__('Søk etter arrangement', 'reginor-lite')) . '</label></p>';
        echo '<p><input type="search" id="rnl-letsreg-inline-query" name="query" maxlength="120" value="' . esc_attr($state['data']['title']) . '" placeholder="' . esc_attr(__('Eksempel: Salsa øvet', 'reginor-lite')) . '"> <button type="submit" class="rnl-button">' . esc_html(__('Søk', 'reginor-lite')) . '</button></p><p><label class="rnl-period-toggle"><input type="checkbox" role="switch" name="period_only" checked aria-describedby="rnl-letsreg-period-help"> ' . esc_html(__('Kun treff i valgt kursperiode', 'reginor-lite')) . '</label><span id="rnl-letsreg-period-help" class="rnl-help">' . esc_html(__('Vis arrangementer med oppstart fra periodens start til og med sluttdatoen. Treff uten oppstartsdato vises når bryteren er av. Uten sluttdato brukes bare startgrensen.', 'reginor-lite')) . '</span></p></form><div data-results></div>';
        echo '<form data-mapping-form hidden><h4 data-event-name tabindex="-1"></h4><input type="hidden" name="event_id"><input type="hidden" name="verification_id"><p>' . esc_html(__('Velg rollen for kategoriene som gjelder kurset. Ved parpåmelding gjelder prisen én deltaker; partneren legges til separat hos LetsReg.', 'reginor-lite')) . '</p><div data-url-preview hidden><p><strong>' . esc_html(__('Påmeldingslenke fra LetsReg', 'reginor-lite')) . '</strong><br><a data-event-url target="_blank" rel="noopener noreferrer"></a></p><p data-url-difference></p><label><input type="checkbox" name="use_api_url" value="1"> ' . esc_html(__('Bruk denne påmeldingslenken', 'reginor-lite')) . '</label></div><p data-url-missing hidden>' . esc_html(__('LetsReg returnerte ingen gyldig offentlig lenke. Du kan fortsatt lagre koblingen og legge inn lenken i kursoppsettet.', 'reginor-lite')) . '</p><div data-categories></div><div data-course-suggestions hidden></div><button class="rnl-button" type="submit">' . esc_html(__('Lagre koblingen', 'reginor-lite')) . '</button> <button type="button" class="rnl-button rnl-button-secondary" data-cancel>' . esc_html(__('Avbryt valget', 'reginor-lite')) . '</button></form></details>';
        echo '<details data-remove-panel' . (empty($state['data']['letsreg_mapping']) ? ' hidden' : '') . '><summary>' . esc_html(__('Fjern koblingen', 'reginor-lite')) . '</summary><p>' . esc_html(__('Kurset, påmeldingslenken og publiseringen beholdes.', 'reginor-lite')) . '</p><button type="button" class="rnl-button rnl-button-secondary" data-remove>' . esc_html(__('Fjern LetsReg-koblingen', 'reginor-lite')) . '</button></details><p data-message role="status" aria-live="polite" tabindex="-1"></p></div>';
        if (!$import) { echo '<noscript>'; LetsRegCoursePanel::render($id, $state); echo '</noscript>'; }
        echo '</section>';
        if (!$import) { LetsRegChangesPanel::render($id, $state); }
    }
}
