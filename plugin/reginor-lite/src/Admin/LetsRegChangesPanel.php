<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\{CourseRepository, ContentTypes, LetsRegChanges, LetsRegConnection, LetsRegMapping, ImportedDescriptions};
use function RegiNor\Lite\translate as __;

final class LetsRegChangesPanel
{
    public static function boot(): void
    {
        add_action('admin_post_rnl_letsreg_review', static function (): void {
            try {
                $input = wp_unslash($_POST); $id = CourseActions::integer($input['id'] ?? '');
                self::dispatch($input);
                $group = (new CourseRepository())->get($id, 'group');
                wp_safe_redirect(add_query_arg(['page'=>'reginor-lite','period'=>$group['data']['period_id'],'group'=>$id,'rnl_source_saved'=>1], admin_url('admin.php')) . '#rnl-source-review', 303); exit;
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                wp_die(esc_html($error->getMessage()), esc_html(__('LetsReg-endringen er ikke lagret', 'reginor-lite')), ['response'=>in_array($error->getCode(), [403,409,503], true) ? $error->getCode() : 400, 'back_link'=>true]);
            }
        });
    }
    public static function dispatch(array $input): void
    {
        LetsRegMapping::authorize();
        if (!is_string($input['_wpnonce'] ?? null) || !wp_verify_nonce($input['_wpnonce'], 'rnl_letsreg_review')) { throw new \RuntimeException(__('Skjemaet er utløpt. Last siden på nytt og vurder endringen igjen.', 'reginor-lite'), 403); }
        $repo = new CourseRepository(); $id = CourseActions::integer($input['id'] ?? '');
        $group = $repo->get($id, 'group'); $mapping = $group['data']['letsreg_mapping'] ?? null;
        if (!$mapping) { throw new \RuntimeException(__('Kurset mangler en tilgjengelig LetsReg-kobling.', 'reginor-lite'), 409); }
        $identity = LetsRegConnection::identity();
        if (!$identity || $mapping['affiliate_id'] !== $identity['affiliate_id'] || $mapping['organizer_id'] !== $identity['organizer_id']) { throw new \RuntimeException(__('Koblingen tilhører ikke konfigurert LetsReg-arrangør.', 'reginor-lite'), 409); }
        if (($input['operation'] ?? '') === 'check') {
            $result = LetsRegConnection::checkCourse($mapping['event_id']);
            if ($result['state'] !== 'verified') { throw new \RuntimeException(LetsRegPage::message($result['error'] ?? '') . ' ' . __('Lokale kursopplysninger er beholdt.', 'reginor-lite'), 503); }
            return;
        }
        $repo->reviewLetsRegSource($id, CourseActions::integer($input['version'] ?? ''), CourseActions::integer($input['description_version'] ?? ''), CourseActions::scalar($input, 'source_hash'), CourseActions::scalar($input, 'operation'));
    }
    private static function labels(): array
    {
        return ['name'=>__('Navn', 'reginor-lite'),'description'=>__('Kursbeskrivelse', 'reginor-lite'),'event_url'=>__('Påmeldingslenke', 'reginor-lite'),'startDate'=>__('Oppstart', 'reginor-lite'),'endDate'=>__('Sluttdato', 'reginor-lite'),'registrationStartDate'=>__('Påmelding åpner', 'reginor-lite'),'registrationEndDate'=>__('Påmelding stenger', 'reginor-lite'),'prices'=>__('Priskategorier og priser', 'reginor-lite'),'active'=>__('Aktivt arrangement', 'reginor-lite'),'published'=>__('Publisert hos LetsReg', 'reginor-lite'),'isCancelled'=>__('Avlyst', 'reginor-lite'),'lastUpdate'=>__('Sist oppdatert hos LetsReg', 'reginor-lite')];
    }
    private static function value(mixed $value): string
    {
        if ($value === null) { return __('Ikke oppgitt', 'reginor-lite'); }
        if (is_bool($value)) { return $value ? __('Ja', 'reginor-lite') : __('Nei', 'reginor-lite'); }
        if (is_array($value)) {
            return implode("\n", array_map(static fn ($p) => '#' . $p['id'] . ' ' . $p['name'] . ' · ' . (isset($p['price_minor']) ? number_format_i18n($p['price_minor']/100, 2) : __('Pris ikke oppgitt', 'reginor-lite')) . ' · ' . self::value($p['active']), $value));
        }
        return (string) $value;
    }
    public static function badge(int $id, array $data): void
    {
        $review = LetsRegChanges::inspect($id, $data);
        if (!in_array($review['state'], ['changed','baseline'], true)) { return; }
        $url = add_query_arg(['page'=>'reginor-lite','period'=>$data['period_id'],'group'=>$id], admin_url('admin.php')) . '#rnl-source-review';
        echo '<p class="rnl-help"><a href="' . esc_url($url) . '">' . esc_html($review['state'] === 'changed' ? __('Endret hos LetsReg – se forskjellene', 'reginor-lite') : __('LetsReg-grunnlag må gjennomgås', 'reginor-lite')) . '</a></p>';
    }
    public static function render(int $id, array $state): void
    {
        if (empty($state['data']['letsreg_mapping']) || !LetsRegMapping::canUse() || !current_user_can('edit_post', $id)) { return; }
        $review = LetsRegChanges::inspect($id, $state['data']); $description = get_post_meta($state['data']['course_id'], ContentTypes::META, true);
        echo '<section class="rnl-panel" id="rnl-source-review"><h3>' . esc_html(__('Endringer hos LetsReg', 'reginor-lite')) . '</h3><p>' . esc_html(__('Vi kontrollerer arrangementet i bakgrunnen. Endringer varsles her og i kursoversikten. Tekst, tid og pris overskrives ikke automatisk.', 'reginor-lite')) . '</p>';
        if (isset($_GET['rnl_source_saved'])) { echo '<p class="rnl-notice" role="status">' . esc_html(__('Handlingen er fullført. Se gjeldende sammenligning nedenfor.', 'reginor-lite')) . '</p>'; }
        self::form($id, $state, $description, $review);
        echo '<button class="rnl-button rnl-button-secondary" name="operation" value="check">' . esc_html(__('Kontroller LetsReg nå', 'reginor-lite')) . '</button></form>';
        if (empty($review['latest'])) { echo '<p>' . esc_html(__('Ingen nylig innholdskontroll. Hent opplysninger for å sammenligne.', 'reginor-lite')) . '</p></section>'; return; }
        echo '<p class="rnl-help">' . esc_html(sprintf(/* translators: %s: last local source check time. */ __('Sist kontrollert: %s.', 'reginor-lite'), wp_date('d.m.Y H:i', $review['latest']['at']))) . '</p>';
        if (!$review['fresh']) { echo '<p class="rnl-notice">' . esc_html(__('Opplysningene er utdaterte eller siste kontroll feilet. Kontroller LetsReg nå før du godkjenner endringer.', 'reginor-lite')) . '</p>'; }
        $source = $review['latest']['snapshot'];
        $template = \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::parse($source['description'] ?? '');
        $terms = $template['mode'] === 'structured' ? \RegiNor\Lite\Infrastructure\RichText::clean($template['fields']['price_terms']) : null;
        $termsChanged = $terms !== null && $terms !== $state['data']['price_terms'];
        $incoming = array_replace($description['data'], \RegiNor\Lite\Domain\LetsRegDescriptionTemplate::courseFields($template));
        $textChanged = !ImportedDescriptions::same($description['data'], $incoming);
        if ($review['state'] === 'unchanged') {
            echo '<p>' . esc_html(__('Ingen nye endringer siden siste gjennomgang.', 'reginor-lite')) . '</p>';
            if (!$textChanged && !$termsChanged) { echo '</section>'; return; }
        }
        echo '<details open>';
        if ($review['state'] !== 'unchanged') {
        echo '<summary>' . esc_html($review['state'] === 'baseline' ? __('Første sammenligning – tidligere kildegrunnlag mangler', 'reginor-lite') : __('Se hva som er endret', 'reginor-lite')) . '</summary><div class="rnl-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Felt', 'reginor-lite')) . '</th><th>' . esc_html(__('Sist gjennomgått hos LetsReg', 'reginor-lite')) . '</th><th>' . esc_html(__('Hos LetsReg nå', 'reginor-lite')) . '</th></tr></thead><tbody>';
        foreach (self::labels() as $field=>$label) {
            if ($review['previous'] !== null && !isset($review['changes'][$field])) { continue; }
            echo '<tr><th scope="row">' . esc_html($label) . '</th><td class="rnl-preserve-lines">' . ($field === 'description' ? \RegiNor\Lite\Infrastructure\RichText::html(self::value($review['previous'][$field] ?? null)) : esc_html(self::value($review['previous'][$field] ?? null))) . '</td><td class="rnl-preserve-lines">' . ($field === 'description' ? \RegiNor\Lite\Infrastructure\RichText::html(self::value($source[$field] ?? null)) : esc_html(self::value($source[$field] ?? null))) . '</td></tr>';
        }
        echo '</tbody></table></div><p class="rnl-help">' . esc_html(__('Datoer, priser og kategorier endres gjennom det vanlige kursoppsettet etter gjennomgang. «Behold lokalt» markerer bare dette kildegrunnlaget som vurdert; det endrer ingen lokale felt.', 'reginor-lite')) . '</p>';
        } else { echo '<summary>' . esc_html(__('Sammenlign lokale tekster med LetsReg', 'reginor-lite')) . '</summary>'; }
        if ($template['mode'] !== 'plain') { echo '<p class="rnl-notice">' . esc_html(LetsRegTemplateHelp::message($template)) . '</p>'; }
        if ($textChanged) {
            echo '<div class="rnl-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Felt', 'reginor-lite')) . '</th><th>' . esc_html(__('Lokalt nå', 'reginor-lite')) . '</th><th>' . esc_html(__('Etter godkjenning', 'reginor-lite')) . '</th></tr></thead><tbody>';
            foreach (['description'=>__('Kursbeskrivelse', 'reginor-lite'), 'dance_style'=>__('Dansestil', 'reginor-lite'), 'level_description'=>__('Nivå og forkunnskaper', 'reginor-lite'), 'partner_info'=>__('Partnerinformasjon', 'reginor-lite')] as $key=>$label) {
                if ($incoming[$key] === $description['data'][$key]) { continue; }
                echo '<tr><th>' . esc_html($label) . '</th><td class="rnl-preserve-lines">' . \RegiNor\Lite\Infrastructure\RichText::html($description['data'][$key]) . '</td><td class="rnl-preserve-lines">' . \RegiNor\Lite\Infrastructure\RichText::html($incoming[$key]) . '</td></tr>';
            }
            echo '</tbody></table></div>';
            $count = count(ImportedDescriptions::references($state['data']['course_id']));
            echo '<p class="rnl-notice">' . esc_html(sprintf(/* translators: %d: courses sharing a description, including drafts and trash. */ __('Beskrivelsen brukes av %d lokale kurs. Oppdater eksisterende endrer teksten for alle disse. Separat beskrivelse gjelder bare dette kurset. Egne lokale redigeringer blokkerer overskriving.', 'reginor-lite'), $count)) . '</p>';
            echo '<p class="rnl-help">' . esc_html(__('En gjenkjent mal oppdaterer kursbeskrivelse, dansestil, nivåforklaring og partnerinformasjon som vist over. Uten mal oppdateres bare kursbeskrivelsen. Prisvilkår godkjennes separat nedenfor og gjelder bare dette kurset. På publiserte kurs blir godkjent tekst synlig straks.', 'reginor-lite')) . '</p>';
        }
        if ($termsChanged) {
            echo '<h4>' . esc_html(__('Prisvilkår og tillegg', 'reginor-lite')) . '</h4><div class="rnl-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Lokalt nå', 'reginor-lite')) . '</th><th>' . esc_html(__('Etter godkjenning', 'reginor-lite')) . '</th></tr></thead><tbody><tr><td>' . \RegiNor\Lite\Infrastructure\RichText::html($state['data']['price_terms']) . '</td><td>' . \RegiNor\Lite\Infrastructure\RichText::html($terms) . '</td></tr></tbody></table></div><p class="rnl-help">' . esc_html(__('Godkjenning erstatter prisvilkårene på dette kurset, også eventuelle lokale redigeringer. Kontroller forskjellen først. Prisbeløp, andre beskrivelser, timeplan og publisering beholdes.', 'reginor-lite')) . '</p>';
        }
        self::form($id, $state, $description, $review); $disabled = $review['fresh'] ? '' : ' disabled';
        if ($termsChanged) { echo '<p><button class="rnl-button" name="operation" value="price_terms"' . $disabled . '>' . esc_html(__('Godkjenn prisvilkår for dette kurset', 'reginor-lite')) . '</button></p>'; }
        echo '<p><button class="rnl-button rnl-button-secondary" name="operation" value="keep"' . $disabled . '>' . esc_html(__('Gjennomgått – behold lokalt', 'reginor-lite')) . '</button></p>';
        if (!empty($source['description']) && $textChanged && $template['mode'] !== 'invalid') {
            echo '<p><button class="rnl-button" name="operation" value="description"' . $disabled . '>' . esc_html(__('Godkjenn tekst – oppdater eksisterende', 'reginor-lite')) . '</button></p>';
            if (LetsRegMapping::canImport()) { echo '<p><button class="rnl-button rnl-button-secondary" name="operation" value="separate"' . $disabled . '>' . esc_html(__('Godkjenn tekst – opprett separat beskrivelse', 'reginor-lite')) . '</button></p>'; }
        }
        echo '</form></details></section>';
    }
    private static function form(int $id, array $state, array $description, array $review): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('rnl_letsreg_review');
        foreach (['action'=>'rnl_letsreg_review','id'=>$id,'version'=>$state['version'],'description_version'=>$description['version'],'source_hash'=>isset($review['latest']) ? LetsRegChanges::hash($review['latest']['snapshot']) : ''] as $key=>$value) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">'; }
    }
}
