<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\LetsRegConnection;
use function RegiNor\Lite\translate as __;

final class LetsRegPage
{
    private static string $error = '';
    private static string $eventInput = '';
    private static string $queryInput = '';

    public static function register(): void
    {
        $hook = add_submenu_page('reginor-lite', __('LetsReg-tilkobling', 'reginor-lite'), __('LetsReg-tilkobling', 'reginor-lite'), 'manage_options', 'rnl-letsreg', [self::class, 'render']);
        add_action('load-' . $hook, static function (): void {
            nocache_headers();
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
            if (!current_user_can('manage_options')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
            check_admin_referer('rnl_letsreg_check');
            try {
                $action = $_POST['rnl_action'] ?? 'connection';
                $eventId = null;
                $search = null;
                if ($action === 'event') {
                    self::$eventInput = is_string($_POST['event_id'] ?? null) ? wp_unslash($_POST['event_id']) : '';
                    $eventId = filter_var(self::$eventInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
                    if ($eventId === false) { throw new \RuntimeException(__('Skriv inn arrangementets numeriske ID fra LetsReg, for eksempel 12345.', 'reginor-lite'), 400); }
                } elseif ($action === 'search') {
                    self::$queryInput = is_string($_POST['query'] ?? null) ? trim(wp_unslash($_POST['query'])) : '';
                    $offset = filter_var($_POST['offset'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10000]]);
                    if ($offset === false) { throw new \RuntimeException(__('Ugyldig resultatside.', 'reginor-lite'), 400); }
                    $search = ['query' => self::$queryInput, 'offset' => $offset];
                } elseif ($action !== 'connection') { throw new \RuntimeException(__('Ukjent handling. Oppdater siden og prøv igjen.', 'reginor-lite'), 400); }
                LetsRegConnection::check($eventId, $search);
                $course = self::returnCourse();
                wp_safe_redirect(admin_url('admin.php?page=rnl-letsreg' . ($course ? '&rnl_course=' . $course['id'] : '')), 303); exit;
            } catch (\RuntimeException $error) {
                self::$error = $error->getMessage();
                status_header(in_array($error->getCode(), [400, 403, 409, 429, 503], true) ? $error->getCode() : 400);
            }
        });
    }

    public static function message(string $error): string
    {
        return match ($error) {
            'authentication' => __('LetsReg godtok ikke tilgangen. Be driftsansvarlig kontrollere API-bruker, passord og affiliate.', 'reginor-lite'),
            'permission' => __('API-brukeren mangler tilgang. Kontroller rettighetene hos LetsReg.', 'reginor-lite'),
            'organization_mismatch' => __('LetsReg svarte med en annen arrangør eller affiliate enn forventet. Kontroller serveroppsettet.', 'reginor-lite'),
            'event_missing' => __('Arrangementet ble ikke funnet. Kontroller arrangementets numeriske ID hos LetsReg.', 'reginor-lite'),
            'event_mismatch' => __('Arrangementets ID, eier eller status kunne ikke bekreftes. Kontroller at arrangementet tilhører arrangøren i serveroppsettet.', 'reginor-lite'),
            'invalid_prices' => __('Priskategoriene kunne ikke bekreftes samlet. Ingen delvis liste er lagret. Be driftsansvarlig kontrollere API-svaret.', 'reginor-lite'),
            'invalid_events' => __('Søkeresultatet kunne ikke bekreftes samlet for riktig arrangør. Prøv et mer presist navn, eller kontroller API-oppsettet.', 'reginor-lite'),
            'rate_limit' => __('LetsReg ber oss vente før neste kontroll. Neste tillatte tidspunkt vises nedenfor.', 'reginor-lite'),
            'transport', 'temporary' => __('Vi fikk ikke kontakt med LetsReg akkurat nå. Prøv igjen etter ventetiden.', 'reginor-lite'),
            'invalid_token' => __('Tokensvaret kunne ikke brukes. Be driftsansvarlig kontrollere autentiseringsoppsettet med LetsReg.', 'reginor-lite'),
            'redirect' => __('API-adressen videresender forespørselen. Be driftsansvarlig kontrollere adressen med LetsReg.', 'reginor-lite'),
            'interrupted' => __('Kontrollen pågår eller ble avbrutt. Oppdater siden før du prøver igjen.', 'reginor-lite'),
            default => __('Svaret fra LetsReg kunne ikke bekreftes. Be driftsansvarlig kontrollere API-oppsettet.', 'reginor-lite'),
        };
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) { wp_die(__('Du har ikke tilgang.', 'reginor-lite'), '', ['response' => 403]); }
        $state = LetsRegConnection::inspect();
        echo '<div class="wrap rnl-ui rnl-admin"><span class="rnl-eyebrow">RegiNor Lite</span><h1>' . esc_html(__('LetsReg-tilkobling', 'reginor-lite')) . '</h1><p>' . esc_html(__('Kontroller at nettsiden får tilgang til riktig arrangør hos LetsReg. Kontrollen endrer ingen arrangementer eller påmeldinger.', 'reginor-lite')) . '</p>';
        $course = self::returnCourse();
        if ($course) { echo '<p><a class="rnl-button rnl-button-secondary" href="' . esc_url(admin_url('admin.php?page=reginor-lite&period=' . $course['period_id'] . '&group=' . $course['id'] . '#rnl-letsreg-course')) . '">' . esc_html(__('Tilbake til kursoppsettet', 'reginor-lite')) . '</a></p>'; }
        if (self::$error !== '') { echo '<p class="rnl-notice" role="alert">' . esc_html(self::$error) . '</p>'; }
        echo '<ol class="rnl-steps"><li>' . esc_html(__('Sett opp API-tilgang', 'reginor-lite')) . '</li><li>' . esc_html(__('Kontroller arrangør', 'reginor-lite')) . '</li><li>' . esc_html(__('Undersøk arrangement', 'reginor-lite')) . '</li></ol><section class="rnl-panel">';
        $heading = match ($state['state']) {
            'missing' => __('API-tilgang mangler', 'reginor-lite'), 'verified' => __('Tilgangen ble bekreftet', 'reginor-lite'),
            'error' => __('Tilgangen er ikke bekreftet', 'reginor-lite'), 'expired' => __('Kontroller tilgangen på nytt', 'reginor-lite'),
            default => __('Klar for første kontroll', 'reginor-lite'),
        };
        if ($state['state'] === 'error' && in_array($state['stage'] ?? '', ['event', 'prices', 'search'], true)) { $heading = __('Arrangementskontrollen ble ikke fullført', 'reginor-lite'); }
        echo '<h2>' . esc_html($heading) . '</h2>';
        if (!$state['configured']) {
            echo '<p>' . esc_html(__('Be driftsansvarlig legge inn API-brukeren og arrangøren i serveroppsettet. Når dette er klart, kan du kontrollere tilkoblingen her.', 'reginor-lite')) . '</p>';
        } else {
            if ($state['state'] === 'verified') {
                echo '<p>' . esc_html(sprintf(/* translators: %s: verified organizer name. */ __('Kontrollen bekreftet tilgang til %s.', 'reginor-lite'), $state['organizer_name'] ?: __('valgt arrangør', 'reginor-lite'))) . '</p>';
            } elseif ($state['state'] === 'error') {
                echo '<p class="rnl-notice">' . esc_html(self::message($state['error'])) . '</p>';
                if (($state['stage'] ?? '') === 'organizer') { echo '<p>' . esc_html(__('Token ble mottatt, men tilgangen til riktig arrangør ble ikke bekreftet.', 'reginor-lite')) . '</p>'; }
                if (in_array($state['stage'] ?? '', ['event', 'prices'], true)) { echo '<p>' . esc_html(__('Arrangøren ble kontrollert, men arrangementet eller priskategoriene kunne ikke bekreftes samlet.', 'reginor-lite')) . '</p>'; }
            }
            foreach (['last_attempt_at' => __('Siste forsøk', 'reginor-lite'), 'last_success_at' => __('Siste vellykkede kontroll', 'reginor-lite')] as $key => $label) {
                if (!empty($state[$key])) { echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html(wp_date('d.m.Y H:i:s', $state[$key])) . '</p>'; }
            }
            echo '<p>' . esc_html(__('Kontrollen bekrefter tilgangen på kontrolltidspunktet. Den starter ikke automatisk henting av kapasitet.', 'reginor-lite')) . '</p>';
        }
        $wait = $state['next_attempt_at'] > time();
        echo '<form method="post">'; wp_nonce_field('rnl_letsreg_check');
        echo '<button class="rnl-button"' . (!$state['configured'] || $wait ? ' disabled' : '') . '>' . esc_html(__('Kontroller tilkoblingen', 'reginor-lite')) . '</button></form>';
        if ($wait) { echo '<p>' . esc_html(sprintf(/* translators: %s: next allowed check date/time. */ __('Neste kontroll tidligst %s. Oppdater siden når ventetiden er over.', 'reginor-lite'), wp_date('d.m.Y H:i:s', $state['next_attempt_at']))) . '</p>'; }
        echo '</section>';
        self::searchPanel($state, $wait);
        self::eventPanel($state, $wait);
        echo '<details><summary>' . esc_html(__('Oppsett for driftsansvarlig', 'reginor-lite')) . '</summary><p>' . esc_html(__('Legg inn disse verdiene som miljøvariabler eller konstanter i wp-config.php. Brukernavn skal oppgis uten affiliate-prefiks; RegiNor setter dette sammen.', 'reginor-lite')) . '</p><ul>';
        foreach (['RNL_LETSREG_AFFILIATE_ID', 'RNL_LETSREG_ORGANIZER_ID', 'RNL_LETSREG_USERNAME', 'RNL_LETSREG_PASSWORD'] as $name) { echo '<li><code>' . esc_html($name) . '</code></li>'; }
        echo '</ul><p>' . esc_html(__('Valgfritt: RNL_LETSREG_CLIENT_ID når LetsReg har avklart klient-ID. Standard er å utelate feltet. Passord og token lagres ikke i WordPress-databasen eller vises på denne siden.', 'reginor-lite')) . '</p><p>' . esc_html(__('Tokenadresse:', 'reginor-lite')) . ' <code>' . esc_html(LetsRegConnection::TOKEN_URL) . '</code></p>';
        if ($state['configured']) { echo '<p>' . esc_html(sprintf(/* translators: 1: configured organizer ID, 2: configured affiliate ID. */ __('Forventet arrangør-ID: %1$d. Affiliate-ID: %2$d.', 'reginor-lite'), $state['organizer_id'], $state['affiliate_id'])) . '</p>'; }
        echo '</details></div>';
    }

    private static function eventPanel(array $state, bool $wait): void
    {
        echo '<section class="rnl-panel"><h2>' . esc_html(__('Undersøk et LetsReg-arrangement', 'reginor-lite')) . '</h2><p>' . esc_html(__('Hent navn og priskategorier for ett arrangement. Dette er en forhåndskontroll før kurskobling; ingen kurs endres, og ingen ledighet publiseres.', 'reginor-lite')) . '</p>';
        echo '<form method="post">'; wp_nonce_field('rnl_letsreg_check');
        echo '<input type="hidden" name="rnl_action" value="event"><p class="rnl-field-card"><label for="rnl-event-id"><strong>' . esc_html(__('Arrangement-ID hos LetsReg', 'reginor-lite')) . '</strong></label><br><input id="rnl-event-id" name="event_id" type="number" min="1" max="2147483647" step="1" required placeholder="12345" aria-describedby="rnl-event-help" value="' . esc_attr(self::$eventInput) . '"></p>';
        echo '<p id="rnl-event-help" class="rnl-help">' . esc_html(__('Bruk den numeriske API-ID-en fra LetsReg, for eksempel 12345. Påmeldingslenkens kursnavn er ikke en arrangement-ID. Be LetsReg om ID-en hvis du ikke finner den.', 'reginor-lite')) . '</p>';
        echo '<button class="rnl-button rnl-button-secondary"' . (!$state['configured'] || $wait ? ' disabled' : '') . '>' . esc_html(__('Hent arrangement og priskategorier', 'reginor-lite')) . '</button></form>';
        echo '<p>' . esc_html(__('Kontrollene deler ventetid. Resultatet nedenfor gjelder bare siste fullførte kontroll. Åpne kursoppsettet for å velge kategorier og lagre koblingen. Kapasitet er foreløpig ikke tilkoblet.', 'reginor-lite')) . '</p>';
        if (isset($state['event']) && in_array($state['state'], ['verified', 'expired'], true)) {
            $event = $state['event'];
            if ($state['state'] === 'expired') { echo '<p class="rnl-notice">' . esc_html(__('Dette er et tidligere resultat. Hent arrangementet på nytt for å kontrollere dagens oppsett.', 'reginor-lite')) . '</p>'; }
            echo '<h3>' . esc_html($event['name'] ?: __('Arrangement uten navn', 'reginor-lite')) . ' <small>#' . esc_html((string) $event['id']) . '</small></h3>';
            foreach (['active' => __('Aktivt', 'reginor-lite'), 'published' => __('Publisert hos LetsReg', 'reginor-lite'), 'isCancelled' => __('Avlyst', 'reginor-lite')] as $key => $label) {
                echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($event[$key] ? __('Ja', 'reginor-lite') : __('Nei', 'reginor-lite')) . '</p>';
            }
            if (!$event['prices']) { echo '<p>' . esc_html(__('LetsReg returnerte ingen priskategorier for dette arrangementet.', 'reginor-lite')) . '</p>'; }
            else {
                echo '<div class="rnl-scroll" tabindex="0" role="region" aria-labelledby="rnl-event-prices-caption"><table class="widefat striped"><caption id="rnl-event-prices-caption">' . esc_html(__('Priskategorier fra siste kontroll', 'reginor-lite')) . '</caption><thead><tr><th scope="col">' . esc_html(__('ID', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Navn', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Aktiv', 'reginor-lite')) . '</th></tr></thead><tbody>';
                foreach ($event['prices'] as $price) { echo '<tr><td>' . esc_html((string) $price['id']) . '</td><td>' . esc_html($price['name'] ?: __('Priskategori uten navn', 'reginor-lite')) . '</td><td>' . esc_html($price['active'] ? __('Ja', 'reginor-lite') : __('Nei', 'reginor-lite')) . '</td></tr>'; }
                echo '</tbody></table></div>';
            }
        }
        echo '</section>';
    }

    private static function returnCourse(): ?array
    {
        $id = is_scalar($_GET['rnl_course'] ?? null) ? absint($_GET['rnl_course']) : 0;
        if (!$id) { return null; }
        try {
            $state = (new \RegiNor\Lite\Infrastructure\CourseRepository())->get($id, 'group');
            return ['id' => $id, 'period_id' => $state['data']['period_id']];
        } catch (\RuntimeException) { return null; }
    }

    private static function searchPanel(array $state, bool $wait): void
    {
        echo '<section class="rnl-panel"><h2>' . esc_html(__('Finn arrangement hos LetsReg', 'reginor-lite')) . '</h2><p>' . esc_html(__('Søk på hele eller deler av navnet, for eksempel Rueda. Søket viser kommende og pågående arrangementer hos den valgte arrangøren. Velg et treff for å kontrollere priskategoriene.', 'reginor-lite')) . '</p><form method="post">';
        wp_nonce_field('rnl_letsreg_check');
        $query = self::$queryInput ?: ($state['search']['query'] ?? '');
        echo '<input type="hidden" name="rnl_action" value="search"><label for="rnl-event-query">' . esc_html(__('Arrangementsnavn', 'reginor-lite')) . '</label><p><input id="rnl-event-query" name="query" type="search" maxlength="120" placeholder="' . esc_attr(__('Eksempel: Rueda', 'reginor-lite')) . '" value="' . esc_attr($query) . '"> <button class="rnl-button"' . (!$state['configured'] || $wait ? ' disabled' : '') . '>' . esc_html(__('Søk hos LetsReg', 'reginor-lite')) . '</button></p></form>';
        if (isset($state['search']) && in_array($state['state'], ['verified', 'expired'], true)) {
            $search = $state['search'];
            echo '<p>' . esc_html(sprintf(/* translators: %d: search result page number. */ __('Resultatside %d. Hver side viser inntil 20 arrangementer; dette er ikke nødvendigvis hele kursutvalget.', 'reginor-lite'), 1 + intdiv($search['offset'], 20))) . '</p>';
            if (!$search['events']) { echo '<p>' . esc_html(__('Ingen treff på denne siden. Prøv et annet navn eller gå tilbake til forrige side.', 'reginor-lite')) . '</p>'; }
            else {
                echo '<form method="post">'; wp_nonce_field('rnl_letsreg_check');
                echo '<input type="hidden" name="rnl_action" value="event"><p><label for="rnl-search-choice">' . esc_html(__('Velg arrangement', 'reginor-lite')) . '</label><br><select id="rnl-search-choice" name="event_id" required><option value="">' . esc_html(__('Velg et treff', 'reginor-lite')) . '</option>';
                foreach ($search['events'] as $event) {
                    $status = $event['isCancelled'] ? __('Avlyst', 'reginor-lite') : ($event['active'] ? __('Aktivt', 'reginor-lite') : __('Inaktivt', 'reginor-lite'));
                    echo '<option value="' . (int) $event['id'] . '">' . esc_html(($event['name'] ?: __('Arrangement uten navn', 'reginor-lite')) . ' · ' . $status . ' · #' . $event['id']) . '</option>';
                }
                echo '</select></p><button class="rnl-button"' . ($wait ? ' disabled' : '') . '>' . esc_html(__('Kontroller valgt arrangement', 'reginor-lite')) . '</button></form>';
            }
            foreach ([-20 => __('Forrige side', 'reginor-lite'), 20 => __('Neste side', 'reginor-lite')] as $delta => $label) {
                $offset = $search['offset'] + $delta;
                if ($offset < 0 || $offset > 10000 || ($delta > 0 && !$search['has_more'])) { continue; }
                echo '<form method="post">'; wp_nonce_field('rnl_letsreg_check');
                echo '<input type="hidden" name="rnl_action" value="search"><input type="hidden" name="query" value="' . esc_attr($search['query']) . '"><input type="hidden" name="offset" value="' . (int) $offset . '"><button class="rnl-button rnl-button-secondary"' . ($wait ? ' disabled' : '') . '>' . esc_html($label) . '</button></form>';
            }
        }
        echo '<p class="rnl-help">' . esc_html(__('Søk og arrangementskontroll deler ventetid med tilkoblingskontrollen. Når ventetiden er over, oppdater siden for å fortsette. Valgt arrangement kontrolleres alltid på nytt før kurskobling.', 'reginor-lite')) . '</p></section>';
    }
}
