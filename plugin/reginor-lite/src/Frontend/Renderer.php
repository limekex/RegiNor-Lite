<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\Appearance;
use RegiNor\Lite\Infrastructure\RichText;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class Renderer
{
    private static bool $schemaPrinted = false;
    private static function days(): array { return [1 => __('Mandag', 'reginor-lite'), __('Tirsdag', 'reginor-lite'), __('Onsdag', 'reginor-lite'), __('Torsdag', 'reginor-lite'), __('Fredag', 'reginor-lite'), __('Lørdag', 'reginor-lite'), __('Søndag', 'reginor-lite')]; }
    private static function weekdays(): array { return [1 => __('Mandager', 'reginor-lite'), __('Tirsdager', 'reginor-lite'), __('Onsdager', 'reginor-lite'), __('Torsdager', 'reginor-lite'), __('Fredager', 'reginor-lite'), __('Lørdager', 'reginor-lite'), __('Søndager', 'reginor-lite')]; }
    private static function status(): array { return ['external' => __('Påmelding Tilgjengelig', 'reginor-lite'), 'dropin' => __('Drop-in', 'reginor-lite'), 'available' => __('Påmelding Tilgjengelig', 'reginor-lite'), 'waiting' => __('Fullt - Venteliste aktiv', 'reginor-lite'), 'full' => __('Fullt', 'reginor-lite'), 'later' => __('Åpner snart', 'reginor-lite'),
        'closed' => __('Stengt', 'reginor-lite'), 'cancelled' => __('Avlyst', 'reginor-lite'), 'unknown' => __('Må avklares', 'reginor-lite'), 'ended' => __('Avsluttet', 'reginor-lite')]; }
    private array $query = [];
    private int $now = 0;
    private array $allowedViews = ['list', 'week'];

    public function render(array $catalog, array $input, string $defaultView = 'list', array $allowedViews = ['list', 'week']): string
    {
        $this->allowedViews = $allowedViews;
        $this->now = (int) $catalog['now'];
        $period = isset($input['rnl_period']) && is_scalar($input['rnl_period']) ? (int) $input['rnl_period'] : $catalog['default'];
        $day = isset($input['rnl_day']) && is_scalar($input['rnl_day']) ? (int) $input['rnl_day'] : 0;
        $level = isset($input['rnl_level']) && is_scalar($input['rnl_level']) && preg_match('/^[1-9][0-9]{0,9}$/D', (string) $input['rnl_level']) ? (int) $input['rnl_level'] : 0;
        $view = ($input['rnl_view'] ?? ($defaultView === 'period' ? ($catalog['periods'][$period]['default_view'] ?? 'list') : $defaultView)) === 'week' ? 'week' : 'list';
        if (!in_array($view, $this->allowedViews, true)) { $view = $this->allowedViews[0]; }
        $this->query = array_filter(['rnl_period' => $period, 'rnl_day' => $day >= 1 && $day <= 7 ? $day : 0, 'rnl_level' => $level, 'rnl_view' => $view]);
        ob_start();
        $appearance = Appearance::settings();
        echo ('<section style="' . esc_attr(Appearance::variables($appearance)) . '" class="rnl-ui rnl-public' . ($appearance['layout'] === 'stacked' ? ' rnl-days-stacked' : '') . (!Appearance::dayColorsApply('week') ? ' rnl-day-colors-off' : '') . '" aria-label="' . esc_attr(__('Kursoversikt', 'reginor-lite')) . '" data-rnl-expiry="') . esc_attr((string) ($catalog['expires_at'] ?? '')) . '" data-rnl-now="' . (int) $catalog['now'] . '">';
        if (isset($input['rnl_course'])) {
            $id = is_scalar($input['rnl_course']) ? (int) $input['rnl_course'] : 0;
            $g = $catalog['groups'][$id] ?? null;
            if (!$g) { $this->empty(__('Dette kurset er ikke tilgjengelig nå', 'reginor-lite'), __('Se kursoversikten for andre muligheter.', 'reginor-lite')); }
            else { $this->query['rnl_period'] = $g['period_id']; echo '<div class="rnl-course-detail' . $this->featuredClass($id) . '" style="' . esc_attr(Appearance::courseStyle($id)) . '" data-rnl-track-course="' . (int) $id . '">'; $this->detail($g, $catalog['periods'][$g['period_id']]); echo '</div>'; $this->schema(SchemaPresenter::group($g, PublicSite::url($id))); }
        } else {
            echo ('<header class="rnl-hero"><span class="rnl-eyebrow">' . esc_html(__('SalsaNor · Dans sammen', 'reginor-lite')) . '</span><h2>' . esc_html(__('Finn et kurs som passer deg', 'reginor-lite')) . '</h2><p>' . esc_html(__('Du trenger ikke kunne trinnene på forhånd. Finn ditt nivå og en dag som passer – vi hjelper deg i gang.', 'reginor-lite')) . '</p></header>');
            if (!$period || !isset($catalog['periods'][$period])) { $this->empty(__('Nye kurs er ikke publisert ennå', 'reginor-lite'), __('Kom gjerne tilbake senere. Her finner du kursene når neste periode er klar.', 'reginor-lite')); }
            else {
                $p = $catalog['periods'][$period];
                $all = array_filter($catalog['groups'], static fn ($g) => $g['period_id'] === $period);
                $groups = array_filter($all, fn ($g) => (!isset($this->query['rnl_day']) || $g['weekday'] === $this->query['rnl_day']) && (!$level || ($g['level_id'] ?? 0) === $level));
                uasort($groups, static fn ($a, $b) => [$a['weekday'], $a['start_time'], $a['title']] <=> [$b['weekday'], $b['start_time'], $b['title']]);
                echo '<div data-rnl-track-list="' . (int) $period . '">';
                $this->filters($catalog, $p, $all, $view, $level);
                echo '<div class="rnl-results-heading"><h3>' . esc_html($p['title']) . '</h3><p role="status" aria-live="polite">' . esc_html(sprintf(/* translators: %d: number of matching courses. */ _n('%d kurs passer valgene dine', '%d kurs passer valgene dine', count($groups), 'reginor-lite'), count($groups))) . '</p></div>';
                if ($p['cancelled']) { echo ('<p class="rnl-notice">' . esc_html(__('Denne kursperioden er avlyst. Se kursdetaljene for informasjon.', 'reginor-lite')) . '</p>'); }
                elseif ($p['last'] && strtotime($p['last']) <= $catalog['now']) { echo ('<p class="rnl-notice">' . esc_html(__('Denne kursperioden er avsluttet.', 'reginor-lite')) . '</p>'); }
                if (!$groups) { $this->empty(__('Ingen kurs passer akkurat disse valgene', 'reginor-lite'), __('Prøv en annen dag eller velg «Vis alle kurs».', 'reginor-lite')); }
                elseif ($view === 'week') { $this->week($groups); }
                else { echo '<div class="rnl-cards">'; foreach ($groups as $g) { $this->card($g); } echo '</div>'; }
                $this->schema(SchemaPresenter::listing($groups, PublicSite::url(null, ['rnl_period' => $period]))); echo '</div>';
            }
        }
        echo '</section>';
        return (string) ob_get_clean();
    }

    private function filters(array $catalog, array $period, array $groups, string $view, int $level): void
    {
        echo '<div class="rnl-toolbar"><form method="get" action="' . esc_url(PublicSite::url()) . '" class="rnl-filter-form">';
        // Plain WordPress permalinks carry page_id in the query.
        $baseQuery = []; parse_str((string) wp_parse_url(PublicSite::url(), PHP_URL_QUERY), $baseQuery);
        foreach ($baseQuery as $key => $value) { if (is_scalar($value)) { echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr((string) $value) . '">'; } }
        echo '<input type="hidden" name="rnl_view" value="' . esc_attr($view) . '">';
        echo ('<label>' . esc_html(__('Kursperiode', 'reginor-lite')) . '<select name="rnl_period">');
        $choices = array_values(array_unique(array_merge($catalog['current'], $catalog['upcoming'], [$period['id']])));
        foreach ($choices as $id) { $p = $catalog['periods'][$id]; $suffix = in_array($id, $catalog['current'], true) ? __(' · Pågående', 'reginor-lite') : (in_array($id, $catalog['upcoming'], true) ? __(' · Kommende', 'reginor-lite') : ''); echo '<option value="' . (int) $id . '"' . selected($period['id'], $id, false) . '>' . esc_html($p['title'] . $suffix) . '</option>'; }
        echo '</select></label><label>' . esc_html(__('Kursnivå', 'reginor-lite')) . '<select name="rnl_level"><option value="0">' . esc_html(__('Alle nivåer', 'reginor-lite')) . '</option>';
        $levels = [];
        foreach ($groups as $g) { if (!empty($g['level_id'])) { $levels[$g['level_id']] = ['title' => $g['level_name'], 'order' => $g['level_order']]; } }
        uasort($levels, static fn ($a, $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);
        $selectedLevel = $this->query['rnl_level'] ?? 0;
        if ($selectedLevel && !isset($levels[$selectedLevel])) { echo '<option value="' . (int) $selectedLevel . '" selected>' . esc_html(__('Valgt nivå har ingen kurs i denne perioden', 'reginor-lite')) . '</option>'; }
        foreach ($levels as $id => $item) { echo '<option value="' . (int) $id . '"' . selected($selectedLevel, $id, false) . '>' . esc_html($item['title']) . '</option>'; }
        echo ('</select></label><label>' . esc_html(__('Hvilken dag passer?', 'reginor-lite')) . '<select name="rnl_day"><option value="0">' . esc_html(__('Alle dager', 'reginor-lite')) . '</option>');
        $days = array_unique(array_column($groups, 'weekday')); sort($days);
        foreach ($days as $day) { echo '<option value="' . (int) $day . '"' . selected($this->query['rnl_day'] ?? 0, $day, false) . '>' . esc_html(self::days()[$day]) . '</option>'; }
        echo ('</select></label><button class="rnl-button rnl-button-secondary" type="submit">' . esc_html(__('Vis kurs', 'reginor-lite')) . '</button></form>');
        echo ('<nav class="rnl-view-switch" aria-label="' . esc_attr(__('Velg visning', 'reginor-lite')) . '">');
        foreach (['list' => __('Kursliste', 'reginor-lite'), 'week' => __('Ukeskalender', 'reginor-lite')] as $v => $label) { if (!in_array($v, $this->allowedViews, true)) { continue; } echo '<a href="' . esc_url($this->url(['rnl_view' => $v])) . '#rnl-results"' . ($view === $v ? ' aria-current="true" class="is-selected"' : '') . '>' . esc_html($label) . '</a>'; }
        echo '</nav></div><div id="rnl-results" tabindex="-1"></div>';
    }
    private function url(array $changes = [], ?int $group = null): string { return PublicSite::url($group, array_filter(array_replace($this->query, $changes))); }
    private function date(?string $date, string $timezone): string
    {
        if (!$date) { return __('Dato avklares', 'reginor-lite'); }
        $local = (new \DateTimeImmutable($date))->setTimezone(new \DateTimeZone($timezone));
        $months = [1 => __('januar', 'reginor-lite'), __('februar', 'reginor-lite'), __('mars', 'reginor-lite'), __('april', 'reginor-lite'), __('mai', 'reginor-lite'), __('juni', 'reginor-lite'), __('juli', 'reginor-lite'), __('august', 'reginor-lite'), __('september', 'reginor-lite'), __('oktober', 'reginor-lite'), __('november', 'reginor-lite'), __('desember', 'reginor-lite')];
        return sprintf(/* translators: 1: day of month, 2: month name, 3: year. */ __('%1$s. %2$s %3$s', 'reginor-lite'), $local->format('j'), $months[(int) $local->format('n')], $local->format('Y'));
    }
    private function price(array $g): string { if (!empty($g['dropin_only'])) { return sprintf(/* translators: %s: price per evening. */ __('%s kr per person', 'reginor-lite'), number_format_i18n(($g['dropin_price_minor'] ?? 0) / 100, ($g['dropin_price_minor'] ?? 0) % 100 ? 2 : 0)); } return (!empty($g['price_from']) ? __('Fra ', 'reginor-lite') : '') . sprintf(/* translators: 1: formatted price in NOK, 2: per-person or per-pair price basis. */ __('%1$s kr %2$s', 'reginor-lite'), number_format_i18n($g['price_minor'] / 100, $g['price_minor'] % 100 === 0 ? 0 : 2), $g['price_basis'] === 'pair' ? __('per par', 'reginor-lite') : __('per person', 'reginor-lite')); }
    private function badge(array $g): void
    {
        $label = self::status()[$g['status']] ?? __('Må avklares', 'reginor-lite');
        echo '<span class="rnl-badge rnl-status-' . esc_attr($g['status']) . '">' . esc_html($label) . '</span>';
    }
    private function featuredClass(int $id): string { return Appearance::course($id)['featured'] ? ' rnl-featured' : ''; }
    private function dropin(array $g): void
    {
        if (empty($g['dropin_enabled']) || !isset($g['dropin_price_minor'])) { return; }
        echo '<details class="rnl-dropin"><summary aria-label="' . esc_attr(__('Drop-in – se pris og informasjon', 'reginor-lite')) . '"><span>' . esc_html(__('Drop-in', 'reginor-lite')) . '</span></summary><div class="rnl-dropin-popover"><strong>' . esc_html(__('Dette kurset tilbyr drop-in.', 'reginor-lite')) . '</strong><p>' . esc_html(sprintf(/* translators: %s: drop-in price in NOK. */ __('%s kr per person og kurskveld.', 'reginor-lite'), number_format_i18n($g['dropin_price_minor'] / 100, $g['dropin_price_minor'] % 100 ? 2 : 0))) . '</p></div></details>';
    }
    private function coursePills(array $g, bool $includeStyle = false): void
    {
        $pills = [];
        if ($includeStyle && !empty($g['dance_style'])) { $pills[] = ['style', __('Dansestil', 'reginor-lite'), $g['dance_style']]; }
        if (!empty($g['level_name'])) { $pills[] = ['level', __('Nivå', 'reginor-lite'), $g['level_name']]; }
        if (!$pills) { return; }
        echo '<div class="rnl-course-pills">';
        foreach ($pills as [$kind, $label, $value]) {
            echo '<span class="rnl-course-pill rnl-course-pill-' . esc_attr($kind) . '"><span class="screen-reader-text">' . esc_html($label) . ': </span>' . esc_html($value) . '</span>';
        }
        echo '</div>';
    }
    private function textSection(string $key, string $title, string $text, string $help = ''): void
    {
        if (RichText::plain($text) === '' && trim($help) === '') { return; }
        echo '<section class="rnl-panel rnl-text-section rnl-section-' . esc_attr($key) . '"><h3>' . esc_html($title) . '</h3>' . RichText::html($text);
        if ($help !== '' && trim($help) !== RichText::plain($text)) { echo '<p class="rnl-help">' . esc_html($help) . '</p>'; }
        echo '</section>';
    }
    private function card(array $g): void
    {
        echo '<article style="' . esc_attr(Appearance::dayCardStyle($g['weekday']) . Appearance::courseStyle($g['id'])) . '" class="rnl-card' . $this->featuredClass($g['id']) . (!empty($g['dropin_enabled']) ? ' rnl-has-dropin' : '') . '" id="rnl-course-' . (int) $g['id'] . '" tabindex="-1"><div class="rnl-card-top"><span class="rnl-eyebrow">' . esc_html($g['dance_style']) . '</span>'; $this->badge($g);
        echo '</div>'; $this->dropin($g); echo '<h3>' . esc_html($g['title']) . '</h3>'; $this->coursePills($g); echo ('<dl class="rnl-facts"><div><dt>' . esc_html(__('Når', 'reginor-lite')) . '</dt><dd>') . esc_html(self::weekdays()[$g['weekday']] . ' · ' . $g['start_time'] . '–' . $g['end_time']) . ('</dd></div><div><dt>' . esc_html(__('Oppstart', 'reginor-lite')) . '</dt><dd>') . esc_html($this->date($g['first'], $g['timezone']) . ' · ' . sprintf(/* translators: %d: number of course evenings. */ _n('%d kveld', '%d kvelder', $g['count'], 'reginor-lite'), $g['count'])) . ('</dd></div><div><dt>' . esc_html(__('Hvor', 'reginor-lite')) . '</dt><dd>') . '<strong class="rnl-room-name">' . esc_html($g['room']) . '</strong><span>' . esc_html($g['venue']) . '</span><span>' . esc_html($g['address']) . '</span></dd></div></dl>';
        echo '<p class="rnl-price">' . esc_html($this->price($g)) . ('<span>' . esc_html((!empty($g['dropin_only']) ? __('per kurskveld', 'reginor-lite') : __('for hele kurset', 'reginor-lite'))) . '</span></p>');
        if ($g['changed']) { echo ('<p class="rnl-small">' . esc_html(__('Enkelte kurskvelder er endret – se kursdatoene.', 'reginor-lite')) . '</p>'); }

        $this->courseActions($g); echo '</article>';
    }
    private function canRegister(array $g): bool
    {
        return !empty($g['registration_url']) && in_array($g['status'], ['available', 'external', 'waiting'], true);
    }
    private function registrationLink(array $g, bool $plain = false, bool $detail = false): void
    {
        if (!$this->canRegister($g)) { return; }
        $external = $g['status'] === 'external';
        $label = $external ? __('Meld meg på', 'reginor-lite') : ($g['status'] === 'waiting' ? __('Se venteliste hos LetsReg', 'reginor-lite')
            : ($detail ? __('Meld deg på hos LetsReg', 'reginor-lite') : __('Meld meg på', 'reginor-lite')));
        $description = sprintf(/* translators: 1: link action, 2: course title. */ ($external ? __('%1$s – %2$s. Åpnes i en ny fane.', 'reginor-lite') : __('%1$s – %2$s. Åpnes hos LetsReg i en ny fane.', 'reginor-lite')), $label, $g['title']);
        // Keep the original destination and its parameters; native navigation also permits GTM link decoration.
        echo '<a class="' . ($plain ? 'rnl-registration-link' : 'rnl-button') . '"' . ($external ? '' : ' data-rnl-letsreg="' . (int) $g['id'] . '"')
            . ' href="' . esc_url($g['registration_url']) . '" target="_blank" rel="noopener" aria-label="' . esc_attr($description)
            . '" title="' . esc_attr($description) . '">' . esc_html($label) . '<span aria-hidden="true"> ↗</span></a>';
    }
    private function courseActions(array $g, bool $calendar = false): void
    {
        echo '<div class="rnl-course-actions' . ($calendar ? ' rnl-course-actions-links' : '') . '"><a'
            . ($calendar ? '' : ' class="rnl-button rnl-button-secondary"') . ' href="' . esc_url($this->url([], $g['id']))
            . '" aria-label="' . esc_attr(sprintf(/* translators: %s: course title. */ __('Se kurset: %s', 'reginor-lite'), $g['title']))
            . '">' . esc_html(__('Se kurset', 'reginor-lite')) . '<span aria-hidden="true"> →</span></a>';
        $this->registrationLink($g, $calendar); echo '</div>';
        if ($this->canRegister($g) && $g['registration_scope'] === 'period') {
            echo '<p class="rnl-small">' . esc_html(sprintf(/* translators: %s: course title to choose on LetsReg. */ __('Lenken viser flere kurs. Velg «%s» på påmeldingssiden.', 'reginor-lite'), $g['title'])) . '</p>';
        }
    }
    private function detail(array $g, array $period): void
    {
        echo '<a class="rnl-back" href="' . esc_url($this->url()) . '#rnl-course-' . (int) $g['id'] . ('">' . esc_html(__('← Tilbake til kursene', 'reginor-lite')) . '</a><header class="rnl-hero"><span class="rnl-eyebrow">') . esc_html($period['title']) . '</span><h2>' . esc_html($g['title']) . '</h2>'; $this->coursePills($g, true); $this->badge($g); echo '</header>';
        echo '<div class="rnl-detail-layout">'; $this->booking($g, $period);
        echo '<div class="rnl-course-description"><section class="rnl-panel rnl-text-section rnl-section-description' . (!empty($g['dropin_enabled']) ? ' rnl-has-dropin' : '') . '">'; $this->dropin($g);
        echo '<h3>' . esc_html(__('Kursbeskrivelse', 'reginor-lite')) . '</h3>' . RichText::html($g['description']) . '</section>';
        $this->textSection('level', __('Nivå og forkunnskaper', 'reginor-lite'), $g['level'], $g['level_help'] ?? '');
        $this->textSection('partner', __('Partnerinformasjon', 'reginor-lite'), $g['partner_info']);
        $this->textSection('price-terms', __('Prisvilkår og tillegg', 'reginor-lite'), $g['price_terms']);
        if ($g['instructors']) { echo '<section class="rnl-panel rnl-text-section"><h3>' . esc_html(__('Instruktører', 'reginor-lite')) . '</h3><p>' . esc_html(implode(', ', $g['instructors'])) . '</p></section>'; }
        echo '<section class="rnl-panel rnl-location" id="rnl-location-' . (int) $g['id'] . '" tabindex="-1"><h3>' . esc_html(__('Kurssted og kart', 'reginor-lite')) . '</h3><p><strong>' . esc_html($g['venue']) . '</strong><br>' . esc_html($g['address']) . '<br><strong class="rnl-room-name">' . esc_html($g['room']) . '</strong></p>';
        \RegiNor\Lite\Infrastructure\VenueMap::display($g);
        echo '</section>';
        echo '<section class="rnl-panel"><h3>' . esc_html(__('Alle kurskveldene', 'reginor-lite')) . '</h3><p>' . esc_html(__('Her ser du datoene du skal møte opp. Eventuelle endringer står ved den enkelte kvelden.', 'reginor-lite')) . '</p><ol class="rnl-session-list">';
        foreach ($g['sessions'] as $s) { echo '<li' . ($s['status'] === 'cancelled' ? ' class="is-cancelled"' : '') . '><div><strong>' . esc_html($this->date($s['starts_at'], $s['timezone'])) . '</strong><span>' . esc_html($s['start_time'] . '–' . $s['end_time'] . ' · ' . $s['room']) . '</span><span>' . esc_html($s['venue'] . ' · ' . $s['address']) . '</span></div>';
            if ($s['status'] !== 'scheduled') { echo '<p class="rnl-notice">' . esc_html(($s['status'] === 'cancelled' ? __('Avlyst. ', 'reginor-lite') : __('Endret kurskveld. ', 'reginor-lite')) . $s['reason']) . ($s['date'] !== $s['original_date'] ? esc_html(__(' Opprinnelig dato: ', 'reginor-lite')) . esc_html($this->date($s['original_date'] . 'T12:00:00', $s['timezone'])) . '.' : '') . '</p>'; }
            echo '</li>'; }
        echo '</ol>';
        if ($g['breaks']) { echo ('<details><summary>' . esc_html(__('Kursfrie dager', 'reginor-lite')) . '</summary><ul>'); foreach ($g['breaks'] as $b) { echo '<li>' . esc_html($this->date($b['from'] . 'T12:00:00', $g['timezone']) . ($b['until'] !== $b['from'] ? '–' . $this->date($b['until'] . 'T12:00:00', $g['timezone']) : '') . ': ' . $b['reason']) . '</li>'; } echo '</ul></details>'; }
        CourseTools::calendar($g);
        echo '</section>';
        CourseTools::share($g);
        echo '</div></div>';
    }
    private function booking(array $g, array $period): void
    {
        $now = $this->now;
        echo ('<aside class="rnl-panel rnl-booking" aria-label="' . esc_attr(__('Pris og påmelding', 'reginor-lite')) . '"><h3>' . esc_html(__('Dette får du', 'reginor-lite')) . '</h3><p><strong>') . esc_html(sprintf(/* translators: %d: number of course evenings. */ _n('%d kurskveld', '%d kurskvelder', $g['count'], 'reginor-lite'), $g['count'])) . '</strong><br>' . esc_html(__('Oppstart: ', 'reginor-lite') . $this->date($g['first'], $g['timezone'])) . '<br>' . esc_html(self::weekdays()[$g['weekday']] . ' ' . $g['start_time'] . '–' . $g['end_time']) . '</p><p>' . esc_html($g['venue']) . '<br>' . '<a href="#rnl-location-' . (int) $g['id'] . '">' . esc_html($g['address']) . '</a><br><strong class="rnl-room-name">' . esc_html($g['room']) . '</strong>' . '</p><p class="rnl-price">' . esc_html($this->price($g)) . ('<span>' . esc_html((!empty($g['dropin_only']) ? __('per kurskveld', 'reginor-lite') : __('for hele kurset', 'reginor-lite'))) . '</span></p>');
        if (($g['registration_source'] ?? 'local') === 'letsreg') {
            $opens = empty($g['effective_registration_from']) ? null : new \DateTimeImmutable($g['effective_registration_from']);
            $closes = empty($g['effective_registration_until']) ? null : new \DateTimeImmutable($g['effective_registration_until']);
        } else {
            [$opens, $closes] = \RegiNor\Lite\Domain\Publication\SalesWindow::bounds($period, $g);
            if ($opens && !empty($g['effective_registration_from'])) { $opens = max($opens, new \DateTimeImmutable($g['effective_registration_from'])); }
            if ($closes && !empty($g['effective_registration_until'])) { $closes = min($closes, new \DateTimeImmutable($g['effective_registration_until'])); }
        }
        // Editorial «opens later» does not establish a date. Only show a future, usable opening.
        if ($g['status'] === 'later' && $opens && (!$closes || $opens < $closes) && $opens->getTimestamp() > $now) {
            echo '<p>' . esc_html(__('Påmeldingen åpner ', 'reginor-lite') . $this->date($opens->format(DATE_ATOM), $g['timezone']) . __(' kl. ', 'reginor-lite') . $opens->setTimezone(new \DateTimeZone($g['timezone']))->format('H:i')) . '.</p>';
        }
        CategoryCapacityPresenter::render($g['capacity'] ?? [], false, $this->now);
        if ($g['registration_url'] && in_array($g['status'], ['available', 'external', 'waiting'], true)) {
            if ($g['registration_scope'] === 'period') { echo ('<p class="rnl-notice">' . esc_html(sprintf(/* translators: %s: course title to choose on LetsReg. */ __('Lenken viser flere kurs. Velg «%s» på påmeldingssiden.', 'reginor-lite'), $g['title']))) . ('' . '</p>'); }
            $this->registrationLink($g, false, true);
            echo '<p class="rnl-small">' . esc_html(($g['status'] === 'external' ? __('Du går videre til kursets påmeldingsside i en ny fane.', 'reginor-lite') : __('Du går videre til LetsReg for påmelding og betaling. Der ser du oppdatert tilgjengelighet.', 'reginor-lite'))) . '</p>';
        } else { echo '<p class="rnl-notice">' . esc_html(self::status()[$g['status']] ?? __('Påmelding avklares', 'reginor-lite')) . '.</p>'; }
        echo '</aside>';
    }
    private function week(array $groups): void
    {
        echo ('<p class="rnl-help">' . esc_html(__('En vanlig kursuke. Kursfrie dager og endringer finner du under «Se kurset».', 'reginor-lite')) . '</p><div class="rnl-week">');
        $days = array_unique(array_column($groups, 'weekday')); sort($days);
        $minutes = static fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $start = min(array_map(static fn ($g) => $minutes($g['start_time']), $groups));
        $end = max(array_map(static fn ($g) => $minutes($g['end_time']), $groups));
        foreach ($days as $day) {
            $daily = array_filter($groups, static fn ($g) => $g['weekday'] === $day); $rooms = [];
            foreach ($daily as $g) { $rooms[$g['room_id']] = ['room' => $g['room'], 'venue' => $g['venue']]; }
            asort($rooms); $tracks = []; $columns = [];
            // Split overlapping fixed slots into lanes, even if actual-session dates never overlap.
            foreach ($rooms as $roomId => $label) {
                $slots = array_values(array_filter($daily, static fn ($g) => $g['room_id'] === $roomId));
                usort($slots, static fn ($a, $b) => strcmp($a['start_time'], $b['start_time'])); $ends = [];
                foreach ($slots as $g) { $lane = 0; while (isset($ends[$lane]) && $ends[$lane] > $minutes($g['start_time'])) { $lane++; } $ends[$lane] = $minutes($g['end_time']); $tracks[$g['id']] = [$roomId, $lane]; }
                for ($lane = 0; $lane < count($ends); $lane++) { $columns[$roomId . ':' . $lane] = ['room' => $label['room'] . ($lane ? __(' · samtidig kurs', 'reginor-lite') : ''), 'venue' => $label['venue']]; }
            }
            $cols = array_keys($columns);
            echo '<section style="' . esc_attr(Appearance::dayStyle((int) $day)) . '" class="rnl-day' . (count($columns) > 2 ? ' rnl-day-list' : '') . '"><h3>' . esc_html(self::weekdays()[$day]) . '</h3><div class="rnl-timetable" style="--rnl-columns:' . count($columns) . ';--rnl-rows:' . max(1, $end - $start) . '"><div class="rnl-time-heading">Kl.</div>';
            foreach ($columns as $key => $label) { $column = array_search($key, $cols, true) + 2; echo '<h4 class="rnl-room-heading" style="' . esc_attr(Appearance::roomStyle((int) explode(':', (string) $key)[0])) . 'grid-column:' . $column . '">' . '<span class="rnl-room-name">' . esc_html($label['room']) . '</span><span class="rnl-room-venue">' . esc_html($label['venue']) . '</span></h4>'; }
            foreach ($columns as $key => $label) { $column = array_search($key, $cols, true) + 2; echo '<div class="rnl-room-fill" aria-hidden="true" style="' . esc_attr(Appearance::roomStyle((int) explode(':', (string) $key)[0])) . 'grid-column:' . $column . ';grid-row:2 / ' . ($end - $start + 2) . '"></div>'; }
            // Block elements stay direct grid children when a theme applies wpautop after rendering.
            // Span the interval so the label's line height does not stretch a single minute.
            for ($time = $start; $time < $end; $time += 30) { echo '<div class="rnl-time-tick" style="grid-row:' . ($time - $start + 2) . ' / span ' . min(30, $end - $time) . '">' . esc_html(sprintf('%02d:%02d', intdiv($time, 60), $time % 60)) . '</div>'; }
            foreach ($daily as $g) {
                [$roomId, $lane] = $tracks[$g['id']]; $col = array_search($roomId . ':' . $lane, $cols, true) + 2;
                echo '<article class="rnl-week-course' . $this->featuredClass($g['id']) . (!empty($g['dropin_enabled']) ? ' rnl-has-dropin' : '') . '" id="rnl-course-' . (int) $g['id'] . '" tabindex="-1" style="' . esc_attr(Appearance::courseStyle($g['id'])) . 'grid-column:' . $col . ';grid-row:' . ($minutes($g['start_time']) - $start + 2) . ' / ' . ($minutes($g['end_time']) - $start + 2) . '"><h4>' . esc_html($g['title']) . '</h4>'; $this->coursePills($g); echo '<p><strong>' . esc_html($g['start_time'] . '–' . $g['end_time']) . '</strong><br>' . esc_html($g['room']) . '</p><p>' . esc_html(implode(', ', $g['instructors'])) . '</p>'; $this->badge($g);
                $this->dropin($g);
                if ($g['delayed_start'] ?? false) { echo '<p class="rnl-start-note"><strong>' . esc_html(sprintf(/* translators: %s: actual first course date. */ __('Senere oppstart: %s', 'reginor-lite'), $this->date($g['first'], $g['timezone']))) . '</strong></p>'; }
                if ($g['breaks'] || $g['changed']) { echo ('<p class="rnl-small">' . esc_html(__('Enkelte kurskvelder er endret eller har fri.', 'reginor-lite')) . '</p>'); }

                $this->courseActions($g, true); echo '</article>';
            }
            echo '</div></section>';
        }
        echo '</div>';
    }
    private function empty(string $title, string $message): void { echo '<div class="rnl-empty"><h3>' . esc_html($title) . '</h3><p>' . esc_html($message) . '</p><a class="rnl-button rnl-button-secondary" href="' . esc_url(PublicSite::url()) . ('">' . esc_html(__('Vis alle kurs', 'reginor-lite')) . '</a></div>'); }
    private function schema(array $schema): void { if (!self::$schemaPrinted) { self::$schemaPrinted = true; echo SchemaPresenter::script($schema); } }
}
