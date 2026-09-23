<?php

declare(strict_types=1);

namespace RegiNor\Lite\Admin;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

use RegiNor\Lite\Infrastructure\CourseRepository;
use RegiNor\Lite\Infrastructure\StateSchema;

/** Server-rendered forms. No JavaScript or generic CPT editor is needed. */
final class CoursePage
{
    private CourseRepository $repo;
    private int $field = 0;
    private int $periodId = 0;
    private int $groupId = 0;
    private string $step = 'courses';
    private string $formCommand = '';
    private int $formId = 0;
    private string $formSession = '';
    private string $formKind = '';
    private static array $result = [];
    private static string $error = '';
    private static array $submitted = [];
    private static function labels(): array { return [
        'level_id' => __('Kursnivå', 'reginor-lite'), 'sort_order' => __('Rekkefølge', 'reginor-lite'), 'active' => __('Tilgjengelig for nye kurs', 'reginor-lite'),
        'price_from' => __('Vis som fra-pris', 'reginor-lite'),
        'registration_from' => __('Påmelding åpner', 'reginor-lite'), 'registration_until' => __('Påmelding stenger', 'reginor-lite'),
        'default_view' => __('Slik vises kursene først', 'reginor-lite'), 'title' => __('Navn', 'reginor-lite'), 'description' => __('Kursbeskrivelse', 'reginor-lite'), 'level_description' => __('Nivå og forkunnskaper', 'reginor-lite'), 'dance_style' => __('Dansestil', 'reginor-lite'), 'partner_info' => __('Partnerinformasjon', 'reginor-lite'),
        'end_date' => __('Siste tillatte kursdato (valgfritt)', 'reginor-lite'), 'from' => __('Fra dato', 'reginor-lite'), 'until' => __('Til dato (valgfritt)', 'reginor-lite'), 'course_id' => __('Kursbeskrivelse', 'reginor-lite'), 'status' => __('Endring', 'reginor-lite'),
        'calendar_enabled' => __('Vis hele kursperioden i arrangementskalenderen', 'reginor-lite'),
        'address' => __('Adresse', 'reginor-lite'), 'venue_id' => __('Kurssted', 'reginor-lite'), 'timezone' => __('Tidssone', 'reginor-lite'), 'start_date' => __('Foreslått startdato', 'reginor-lite'),
        'default_session_count' => __('Standard antall kvelder', 'reginor-lite'), 'default_room_id' => __('Standard sal', 'reginor-lite'), 'default_price_minor' => __('Standard pris (kr)', 'reginor-lite'),
        'default_price_basis' => __('Standard prisgrunnlag', 'reginor-lite'), 'visible_from' => __('Synlig fra', 'reginor-lite'), 'visible_until' => __('Synlig til', 'reginor-lite'), 'sales_from' => __('Påmelding fra', 'reginor-lite'), 'sales_until' => __('Påmelding til', 'reginor-lite'),
        'show_as_upcoming' => __('Vis som kommende periode', 'reginor-lite'), 'cancelled' => __('Perioden er avlyst', 'reginor-lite'), 'first_date' => __('En annen første kursdato (valgfritt)', 'reginor-lite'), 'latest_date' => __('Absolutt siste dato (valgfritt)', 'reginor-lite'),
        'weekday' => __('Ukedag', 'reginor-lite'), 'start_time' => __('Start', 'reginor-lite'), 'end_time' => __('Slutt', 'reginor-lite'), 'session_count' => __('Antall undervisningskvelder', 'reginor-lite'), 'room_id' => __('Sal', 'reginor-lite'),
        'instructor_ids' => __('Instruktører (valgfritt)', 'reginor-lite'), 'price_minor' => __('Pris (kr)', 'reginor-lite'), 'price_basis' => __('Prisgrunnlag', 'reginor-lite'), 'currency' => __('Valuta', 'reginor-lite'), 'price_terms' => __('Prisvilkår og tillegg', 'reginor-lite'), 'dropin_price_minor' => __('Drop-in-pris per person og kurskveld (kr)', 'reginor-lite'),
        'registration_url' => __('Påmeldingslenke', 'reginor-lite'), 'registration_scope' => __('Lenken gjelder', 'reginor-lite'), 'registration_status' => __('Påmeldingsstatus', 'reginor-lite'), 'reason' => __('Forklaring', 'reginor-lite'), 'date' => __('Dato', 'reginor-lite'),
    ]; }
    private static function help(): array { return [
        'level_id' => __('Velg nivået for dette kurset. Nivået vises på nettsiden og brukes i nivåfilteret. Administrator lager nivåutvalget under Kursinnhold og ressurser.', 'reginor-lite'),
        'sort_order' => __('Laveste tall vises først. Eksempel: 10 for Nybegynner, 20 for Øvet 1 og 30 for Videregående.', 'reginor-lite'),
        'active' => __('Slå av for å skjule nivået i nye valg. Kurs som allerede bruker nivået, beholder det og kan fortsatt filtreres.', 'reginor-lite'),
        'price_from' => __('Vis for eksempel «Fra 1200 kr per person» når LetsReg har flere priser.', 'reginor-lite'),
        'registration_from' => __('Åpning for dette kurset i valgt tidssone. Tomt felt følger kursperioden.', 'reginor-lite'),
        'registration_until' => __('Stenging for dette kurset i valgt tidssone. Påmelding etter kursstart er tillatt.', 'reginor-lite'),
        'featured' => __('Gjør kurset tydeligere i oversikten med nettstedets fremhevingsfarge.', 'reginor-lite'),
        'dropin_enabled' => __('Vis et Drop-in-banner. Oppgi pris for én person og én kurskveld nedenfor.', 'reginor-lite'),
        'calendar_enabled' => __('Én oppføring fra periodens start til slutt, med lenke til alle kursene. Også fremtidige perioder vises før kursstart når de er publisert og synlighetsvinduet er åpent. Hvis sluttdato mangler, brukes siste kurskveld. Krever The Events Calendar.', 'reginor-lite'),
        'end_date' => __('Undervisning og kursfrie dager må være innenfor denne datoen. La stå tomt hvis slutten ikke er bestemt ennå. Dette endrer ikke synlighet eller påmelding.', 'reginor-lite'),
        'from' => __('Første kursfrie dato, innenfor kursperioden. Eksempel: første dag i høstferien.', 'reginor-lite'),
        'until' => __('Siste kursfrie dato, også inkludert. La stå tomt for bare én fridag.', 'reginor-lite'),
        'reason' => __('Forklar kort hvorfor. Eksempel: Høstferie eller salen er opptatt.', 'reginor-lite'),
        'date' => __('Velg ny kursdato innenfor perioden. Eksempel: neste ledige undervisningsdag.', 'reginor-lite'),
        'description' => __('Beskriv hva deltakerne lærer. Eksempel: Grunnsteg og enkle turer i salsa.', 'reginor-lite'),
        'dance_style' => __('Navnet på dansen. Eksempel: Salsa eller Bachata.', 'reginor-lite'),
        'address' => __('Gateadresse til kursstedet. Eksempel: Dansegata 12, Trondheim.', 'reginor-lite'),
        'venue_id' => __('Velg stedet der salen ligger. Eksempel: Kulturhuset.', 'reginor-lite'),
        'course_id' => __('Velg beskrivelsen deltakerne skal se. Eksempel: Salsa nybegynner.', 'reginor-lite'),
        'timezone' => __('Tidene tolkes i denne tidssonen. Bruk Europe/Oslo for kurs i Norge.', 'reginor-lite'),
        'default_price_basis' => __('Velg om standardprisen gjelder én person eller ett par. Eksempel: 1200 kr per person.', 'reginor-lite'),
        'price_basis' => __('Velg hva prisen dekker. Eksempel: Per par betyr at prisen gjelder to deltakere.', 'reginor-lite'),
        'weekday' => __('Ukedagen dere vanligvis møtes. Eksempel: Mandag hver uke.', 'reginor-lite'),
        'start_time' => __('Når undervisningen begynner. Eksempel: 18:00.', 'reginor-lite'),
        'end_time' => __('Når undervisningen slutter, senere samme dag. Eksempel: 19:00.', 'reginor-lite'),
        'session_count' => __('Totalt antall undervisningskvelder i hele kursrekken, fra 1 til 104. Tell fra første kursdato, også datoer før i dag. Eksempel: 8 totalt, selv om bare 3 gjenstår. Kursfrie dager teller ikke med.', 'reginor-lite'),
        'room_id' => __('Velg salen dere bruker. Eksempel: Sal A. Sal må være valgt før publisering.', 'reginor-lite'),
        'currency' => __('Prisen oppgis i norske kroner (NOK).', 'reginor-lite'),
        'registration_scope' => __('Velg om lenken gjelder dette kurset eller hele kursperioden.', 'reginor-lite'),
        'registration_status' => __('Velg Automatisk fra LetsReg for oppdatert salgsvindu og kapasitet i koblede priskategorier. Egen påmeldingslenke og Kun drop-in trenger ingen LetsReg-kobling. Kun drop-in krever pris per kveld (0 for gratis) og følger ikke salgsvinduet. De øvrige valgene overstyrer API-statusen manuelt. Automatisk status bruker LetsRegs salgsdatoer, ikke periodens. Egne påmeldingsdatoer på kurset kan begrense vinduet. Avlysning av perioden og kursets slutt gjelder fortsatt.', 'reginor-lite'),
        'status' => __('Velg Flyttet når tid eller sted endres, eller Avlyst når denne kvelden utgår. Oppgi en forklaring.', 'reginor-lite'),
        'show_as_upcoming' => __('Kryss av hvis perioden skal vises som kommende når den er publisert og synlig.', 'reginor-lite'),
        'cancelled' => __('Kryss bare av hvis hele perioden er avlyst. Kursene blir da ikke tilbudt for påmelding.', 'reginor-lite'),
        'title' => __('Bruk et kort navn som er lett å kjenne igjen og forstå.', 'reginor-lite'),
        'start_date' => __('Vi finner første valgte ukedag på eller etter denne datoen.', 'reginor-lite'),
        'default_session_count' => __('Totalt antall undervisningskvelder fra kursets første dato, ikke antallet som gjenstår fra i dag. Kursfrie dager kommer i tillegg.', 'reginor-lite'),
        'default_room_id' => __('Foreslås for nye kurs. Du kan velge en annen sal i hvert kurs.', 'reginor-lite'),
        'default_price_minor' => __('Prisen som foreslås for nye kurs. Skriv kroner, for eksempel 1200 eller 1200,50.', 'reginor-lite'),
        'visible_from' => __('Fra dette tidspunktet kan deltakerne se de publiserte kursene.', 'reginor-lite'),
        'visible_until' => __('Da skjules kursene og detaljsidene. Velg gjerne dagen etter siste kurskveld.', 'reginor-lite'),
        'sales_from' => __('Standard åpning for kurs med manuell status. Kurs med Automatisk fra LetsReg følger LetsRegs datoer. Kursene kan vises før påmeldingen åpner.', 'reginor-lite'),
        'sales_until' => __('Da stenger påmeldingsknappen, selv om kursene fortsatt er synlige.', 'reginor-lite'),
        'price_minor' => __('Pris for hele kurset i kroner. Oppgi eventuelle tillegg nedenfor.', 'reginor-lite'),
        'dropin_price_minor' => __('Eksempel: 200,00 kr for én kveld. Prisen må fylles ut når drop-in er aktivert. 0 betyr gratis.', 'reginor-lite'),
        'price_terms' => __('Skriv hva som er inkludert, og om medlemskap eller andre tillegg kommer utenom.', 'reginor-lite'),
        'registration_url' => __('Lim inn hele HTTPS-lenken til påmelding. Velg Egen påmeldingslenke for andre nettsider enn LetsReg. Kun drop-in krever ingen lenke.', 'reginor-lite'),
        'first_date' => __('La stå tomt for første valgte ukedag fra kursperiodens start. Fyll bare ut hvis kurset starter senere. Datoen må passe med ukedagen og være innenfor perioden.', 'reginor-lite'),
        'latest_date' => __('Valgfritt. Bruk dette hvis undervisningen må være ferdig innen en bestemt dato.', 'reginor-lite'),
        'level_description' => __('Forklar hvem kurset passer for med vanlige ord, ikke bare en nivåkode.', 'reginor-lite'),
        'partner_info' => __('Forklar om man kan komme alene, og hvordan partnerbytte fungerer.', 'reginor-lite'),
        'default_view' => __('Kursliste er enklest for nye deltakere. Besøkende kan alltid bytte visning selv.', 'reginor-lite'),
    ]; }
    private static function options(): array { return ['person' => __('Per person', 'reginor-lite'), 'pair' => __('Per par', 'reginor-lite'), 'group' => __('Valgt kurs', 'reginor-lite'), 'period' => __('Felles periode', 'reginor-lite'),
        'mixed' => __('Flere nivåer / må avklares', 'reginor-lite'), 'beginner' => __('Nybegynnere', 'reginor-lite'), 'experienced' => __('Deltakere med erfaring', 'reginor-lite'), 'list' => __('Kursliste – anbefalt', 'reginor-lite'), 'week' => __('Ukeskalender', 'reginor-lite'), 'unknown' => __('Må avklares', 'reginor-lite'), 'external' => __('Egen påmeldingslenke', 'reginor-lite'), 'dropin' => __('Kun drop-in', 'reginor-lite'), 'automatic' => __('Automatisk fra LetsReg', 'reginor-lite'), 'later' => __('Åpner senere', 'reginor-lite'), 'available' => __('Påmelding tilgjengelig', 'reginor-lite'), 'full' => __('Fullt', 'reginor-lite'), 'waiting' => __('Venteliste', 'reginor-lite'), 'closed' => __('Stengt', 'reginor-lite'), 'cancelled' => __('Avlyst', 'reginor-lite'), 'moved' => __('Flyttet', 'reginor-lite')]; }

    public function __construct() { $this->repo = new CourseRepository(); }

    public static function register(): void
    {
        $hook = add_menu_page('RegiNor Lite', 'RegiNor Lite', 'rnl_edit_periods', 'reginor-lite', [self::class, 'render'], 'dashicons-calendar-alt');
        add_action('load-' . $hook, [self::class, 'processRequest']);
        add_submenu_page('reginor-lite', __('Kursperioder', 'reginor-lite'), __('Kursperioder', 'reginor-lite'), 'rnl_edit_periods', 'reginor-lite', [self::class, 'render']);
        $resources = add_submenu_page('reginor-lite', __('Kursinnhold og ressurser', 'reginor-lite'), __('Kursinnhold og ressurser', 'reginor-lite'), 'manage_options', 'rnl-resources', [self::class, 'render']);
        add_action('load-' . $resources, [self::class, 'processRequest']);
    }

    public static function render(): void
    {
        if (!current_user_can('rnl_edit_periods')) { wp_die(__('Du har ikke tilgang til kursoppsettet.', 'reginor-lite'), '', ['response' => 403]); }
        if (isset($_GET['import']) && !\RegiNor\Lite\Infrastructure\LetsRegMapping::canImport()) { wp_die(__('Du har ikke tilgang til å importere kurs fra LetsReg.', 'reginor-lite'), '', ['response' => 403]); }
        nocache_headers();
        $page = new self();
        $result = self::$result;
        $error = self::$error;
        $id = (int) ($result['id'] ?? (is_scalar($_GET['period'] ?? null) ? $_GET['period'] : 0));
        $group = (int) ($result['group'] ?? (is_scalar($_GET['group'] ?? null) ? $_GET['group'] : 0));
        $page->periodId = $id; $page->groupId = $group;
        $page->step = in_array($_GET['step'] ?? '', ['period', 'courses', 'publish', 'manage'], true) ? $_GET['step'] : 'courses';
        echo ('<div class="wrap rnl-ui rnl-admin"><span class="rnl-eyebrow">' . esc_html(__('RegiNor Lite · Kursadministrasjon', 'reginor-lite')) . '</span><h1>' . esc_html(($_GET['page'] ?? '') === 'rnl-resources' ? __('Kursinnhold og ressurser', 'reginor-lite') : __('Kursperioder', 'reginor-lite')) . '</h1><p>' . esc_html(__('Lag et godt kurstilbud, ett steg om gangen. Du kan lagre underveis og kontrollere alt før kursene blir synlige.', 'reginor-lite')) . '</p>');
        echo '<p class="rnl-back"><a href="' . esc_url(admin_url('admin.php?page=reginor-lite')) . ('">' . esc_html(__('Alle perioder', 'reginor-lite')) . '</a></p>');
        if ($error !== '') { echo '<div class="notice notice-error" role="alert"><p>' . esc_html($error) . ('</p><p>' . esc_html(__('Endringen er ikke bekreftet. Rett feltene og prøv igjen. Ved versjonskonflikt: åpne siden på nytt og sammenlign med siste lagrede oppsett.', 'reginor-lite')) . '</p></div>'); }
        if (isset($_GET['updated'])) { echo ('<div class="notice notice-success" role="status"><p>' . esc_html(__('Handlingen er fullført. Oppsettet nedenfor viser lagrede verdier.', 'reginor-lite')) . '</p></div>'); }
        if (isset($result['message'])) { echo '<div class="notice notice-success" role="status"><p>' . esc_html($result['message']) . '</p></div>'; }
        try {
            if (isset($result['proposal'])) { $page->preview($result['proposal']); }
            elseif (isset($result['publication'])) { $page->publication($result['publication']); }
            elseif (($_GET['page'] ?? '') === 'rnl-resources') { $page->resources(); }
            elseif ($group) {
                $state = $page->repo->get($group, 'group');
                if ($id && $state['data']['period_id'] !== $id) { throw new \RuntimeException(__('Kurset tilhører en annen periode.', 'reginor-lite')); }
                $page->group($group, $state);
            } elseif ($id) { $page->period($id); }
            elseif (isset($_GET['new'])) { $page->createPeriod(); }
            else { $page->index(); }
        } catch (\Throwable $exception) { echo '<div class="notice notice-error" role="alert"><p>' . esc_html($exception->getMessage()) . '</p></div>'; }
        echo '</div>';
    }

    public static function processRequest(): void
    {
        nocache_headers();
        // Reject before WordPress prints the admin header, so HTTP status also reflects denied access.
        if (isset($_GET['import']) && !\RegiNor\Lite\Infrastructure\LetsRegMapping::canImport()) { wp_die(__('Du har ikke tilgang til å importere kurs fra LetsReg.', 'reginor-lite'), '', ['response' => 403]); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { return; }
        self::$submitted = wp_unslash($_POST);
        try {
            self::$result = (new CourseActions())->handle(self::$submitted);
            if (isset(self::$result['message'])) {
                $page = ($_GET['page'] ?? '') === 'rnl-resources' ? 'rnl-resources' : 'reginor-lite';
                wp_safe_redirect(add_query_arg(array_filter(['page' => $page, 'period' => self::$result['id'] ?? 0,
                    'group' => self::$result['group'] ?? 0, 'updated' => 1]), admin_url('admin.php')), 303);
                exit;
            }
        } catch (\Throwable $error) {
            self::$error = $error->getMessage();
            if ($error->getCode() === 403) { status_header(403); }
        }
    }

    private function start(string $command, int $id = 0, int $version = 0, string $formId = ''): void
    {
        $this->formCommand = $command; $this->formId = $id; $this->formSession = ''; $this->formKind = '';
        $page = ($_GET['page'] ?? '') === 'rnl-resources' ? 'rnl-resources' : 'reginor-lite';
        $bounds = $this->periodId && $command !== 'copy' ? $this->repo->get($this->periodId, 'period')['data'] : [];
        echo '<form' . ($formId !== '' ? ' id="' . esc_attr($formId) . '"' : '') . ' class="rnl-edit-form" data-period-start="' . esc_attr($bounds['start_date'] ?? '') . '" data-period-end="' . esc_attr($bounds['end_date'] ?? '') . '" data-registration-hosts="' . esc_attr(wp_json_encode(\RegiNor\Lite\Infrastructure\RegistrationDomains::allowed())) . '" method="post" action="' . esc_url(add_query_arg(array_filter(['page' => $page, 'period' => $this->periodId, 'group' => $this->groupId, 'step' => $this->step, 'new' => isset($_GET['new']) ? 1 : 0]), admin_url('admin.php'))) . '">';
        if ($bounds && in_array($command, ['preview_group', 'preview_import', 'session', 'replacement', 'add_group'], true)) {
            $from = (new \DateTimeImmutable($bounds['start_date']))->format('d.m.Y');
            $until = !empty($bounds['end_date']) ? (new \DateTimeImmutable($bounds['end_date']))->format('d.m.Y') : __('ingen sluttdato valgt', 'reginor-lite');
            echo '<p class="rnl-help">' . esc_html(sprintf(/* translators: 1: first allowed teaching date; 2: last allowed date or an unset explanation. */ __('Kursperiodens datogrenser: %1$s – %2$s. Datoer utenfor grensen krever at perioden endres først.', 'reginor-lite'), $from, $until)) . '</p>';
        }
        wp_nonce_field('rnl_course_command');
        $this->hidden('command', $command); $this->hidden('id', (string) $id); $this->hidden('version', (string) $version);
    }
    private function hidden(string $name, string $value): void { if ($name === 'session_id') { $this->formSession = $value; } echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">'; }
    private function end(string $label): void { submit_button($label, 'primary'); echo '</form>'; }
    private function encoded(array $data): string { return base64_encode(wp_json_encode($data, JSON_THROW_ON_ERROR)); }
    private function link(int $period, int $group = 0, string $step = 'courses'): string { return esc_url(add_query_arg(array_filter(['page' => 'reginor-lite', 'period' => $period, 'group' => $group, 'step' => $step]), admin_url('admin.php'))); }

    private function submittedValue(string $name, mixed $fallback): mixed
    {
        if (str_contains($name, '[breaks]') || self::$error === '' || $this->formCommand !== (self::$submitted['command'] ?? '')
            || (string) $this->formId !== (self::$submitted['id'] ?? '')
            || $this->formSession !== (self::$submitted['session_id'] ?? '')) { return $fallback; }
        $value = self::$submitted;
        foreach (preg_split('/[\[\]]+/', $name, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) { return $fallback; }
            $value = $value[$part];
        }
        return is_scalar($value) ? $value : $fallback;
    }
    private function attributes(string $key, string $id): string
    {
        $required = in_array($key, ['title', 'start_date', 'date', 'start_time', 'end_time', 'timezone', 'default_session_count', 'session_count', 'price_minor', 'default_price_minor', 'dance_style', 'address'], true)
            || ($key === 'reason' && in_array($this->formCommand, ['session', 'replacement'], true));
        return ' data-field="' . esc_attr($key) . '" aria-describedby="' . $id . '-help ' . $id . '-error"' . ($required ? ' required' : '');
    }
    private function fieldHelp(string $key, string $id): void
    {
        $help = self::help()[$key] ?? '';
        if ($this->formKind === 'level' && $key === 'description') { $help = __('Valgfritt. Forklar nivået med vanlige ord. Forklaringen vises på kursdetaljen når dette nivået er valgt.', 'reginor-lite'); }
        if ($this->formKind === 'period' && $key === 'start_date') { $help = __('Tidligste undervisningsdag i perioden. Kurs og kursfrie dager må være på eller etter denne datoen.', 'reginor-lite'); }
        echo '<span class="rnl-help" id="' . $id . '-help">' . esc_html($help) . '</span><span class="rnl-field-error" id="' . $id . '-error" aria-live="polite"></span>';
    }
    private function input(string $key, mixed $value, string $type = 'text', string $name = ''): void
    {
        $name = $name ?: 'data[' . $key . ']';
        $value = $this->submittedValue($name, $value);
        $id = 'rnl-field-' . ++$this->field;
        $examples = ['title' => __('Eksempel: Salsa nybegynner – høst', 'reginor-lite'), 'registration_url' => 'https://www.letsreg.com/event/ditt-kurs',
            'reason' => __('Eksempel: Høstferie', 'reginor-lite'), 'price_minor' => '1200,00', 'default_price_minor' => '1200,00',
            'price_terms' => __('Eksempel: Seks kurskvelder. Medlemskap kommer i tillegg.', 'reginor-lite'),
            'level_description' => __('Eksempel: Du trenger ingen danseerfaring.', 'reginor-lite'), 'partner_info' => __('Eksempel: Du kan komme alene. Vi bytter partner underveis.', 'reginor-lite'),
            'description' => __('Eksempel: Lær grunnsteg og enkle turer i salsa.', 'reginor-lite'), 'dance_style' => 'Salsa', 'address' => __('Eksempel: Dansegata 12, Trondheim', 'reginor-lite'), 'timezone' => 'Europe/Oslo'];
        if ($this->formKind === 'level') { $examples['title'] = __('Eksempel: Øvet 1', 'reginor-lite'); $examples['description'] = __('Eksempel: For deg som har fullført nybegynnerkurset.', 'reginor-lite'); }
        if ($this->formKind === 'period' || $this->formCommand === 'copy') { $examples['title'] = __('Eksempel: Høst 2030 – Trondheim', 'reginor-lite'); }
        $attributes = $this->attributes($key, $id) . ' placeholder="' . esc_attr($examples[$key] ?? '') . '"';
        if ($key === 'registration_url') { $type = 'url'; }
        if (str_ends_with($key, 'price_minor')) { $attributes .= ' inputmode="decimal"'; }
        if ($type === 'number') { $attributes .= $key === 'sort_order' ? ' min="0" max="999" step="1"' : ' min="1" max="104" step="1"'; }
        $label = $this->formKind === 'level' && $key === 'description' ? __('Kort forklaring av nivået', 'reginor-lite') : (self::labels()[$key] ?? $key);
        if ($type === 'textarea' && $this->formKind !== 'level' && in_array($key, ['description', 'level_description', 'partner_info', 'price_terms'], true)) {
            echo '<div class="rnl-field-card rnl-wide rnl-rich-field"><label for="' . $id . '">' . esc_html($label) . '</label>';
            echo '<textarea data-rnl-rich-text class="large-text" rows="8" id="' . $id . '"' . $attributes . ' name="' . esc_attr($name) . '">' . esc_textarea((string) $value) . '</textarea>';
            $this->fieldHelp($key, $id);
            echo '<p class="rnl-help">' . esc_html(__('Bruk Visuell for avsnitt, fet/kursiv tekst, lister og lenker. HTML-fanen viser koden. Lenker åpnes i en ny fane på kurssiden.', 'reginor-lite')) . '</p></div>';
            return;
        }
        echo '<p class="rnl-field-card' . ($type === 'textarea' ? ' rnl-wide' : '') . '"><label for="' . $id . '">' . esc_html($label . (str_contains($attributes, ' required') ? __(' (påkrevd)', 'reginor-lite') : '')) . '</label>';
        if ($type === 'textarea') { echo '<textarea class="large-text" rows="3" id="' . $id . '"' . $attributes . ' name="' . esc_attr($name) . '">' . esc_textarea((string) $value) . '</textarea>'; }
        else { echo '<input class="regular-text" type="' . esc_attr($type) . '" id="' . $id . '"' . $attributes . ' name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">'; }
        $this->fieldHelp($key, $id);
        echo '</p>';
    }
    private function select(string $key, mixed $value, array $options, string $name = ''): void
    {
        $id = 'rnl-field-' . ++$this->field; $name = $name ?: 'data[' . $key . ']';
        $value = $this->submittedValue($name, $value);
        echo '<p class="rnl-field-card"><label for="' . $id . '">' . esc_html(self::labels()[$key] ?? $key) . '</label><select id="' . $id . '"' . $this->attributes($key, $id) . ' name="' . esc_attr($name) . '">';
        foreach ($options as $v => $label) { echo '<option value="' . esc_attr((string) $v) . '"' . selected((string) $value, (string) $v, false) . '>' . esc_html((string) $label) . '</option>'; }
        echo '</select>'; $this->fieldHelp($key, $id); echo '</p>';
    }
    private function checkbox(string $name, string $label, bool $checked = false): void
    {
        $id = 'rnl-field-' . ++$this->field;
        $key = preg_replace('/^data\[([^]]+)\]$/', '$1', $name);
        echo '<p class="rnl-field-card"><label><input type="checkbox" aria-describedby="' . $id . '-help" name="' . esc_attr($name) . '" value="1"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label><span class="rnl-help" id="' . $id . '-help">' . esc_html(self::help()[$key] ?? __('Valget gjelder bare denne oppføringen.', 'reginor-lite')) . '</span></p>';
    }
    private function choices(string $kind): array
    {
        $options = [0 => __('Velg …', 'reginor-lite')];
        foreach ($this->repo->listing($kind) as $id => $row) { $options[$id] = $row['data']['title']; }
        return $options;
    }
    private function levelChoices(int $selected): array
    {
        $options = [0 => __('Ikke valgt', 'reginor-lite')];
        $levels = $this->repo->listing('level');
        uasort($levels, static fn ($a, $b) => [$a['data']['sort_order'], $a['data']['title']] <=> [$b['data']['sort_order'], $b['data']['title']]);
        foreach ($levels as $id => $row) {
            if (!$row['data']['active'] && $id !== $selected) { continue; }
            $options[$id] = $row['data']['title'] . (!$row['data']['active'] ? __(' (ikke tilgjengelig for nye kurs)', 'reginor-lite') : '');
        }
        if ($selected && !isset($options[$selected])) { $options[$selected] = __('Nivået finnes ikke lenger – velg et annet', 'reginor-lite'); }
        return $options;
    }
    private function instructors(array $selected): void
    {
        echo ('<fieldset><legend>' . esc_html(__('Instruktører (valgfritt)', 'reginor-lite')) . '</legend><p>' . esc_html(__('Du kan publisere kurset uten å velge instruktør.', 'reginor-lite')) . '</p>');
        $profiles = $this->repo->instructors();
        if (!$profiles) { echo ('<p>' . esc_html(__('Ingen instruktørprofiler er valgt. Du kan fortsette uten instruktør.', 'reginor-lite')) . '</p>'); }
        foreach ($profiles as $profile) { echo '<label><input type="checkbox" name="data[instructor_ids][]" value="' . (int) $profile['id'] . '"' . checked(in_array($profile['id'], $selected, true), true, false) . '> ' . esc_html($profile['title']) . '</label><br>'; }
        echo '</fieldset>';
    }
    private function fields(string $kind, array $data, ?string $part = null): void
    {
        $this->formKind = $kind;
        $sticky = self::$error !== '' && $this->formCommand === (self::$submitted['command'] ?? '')
            && (string) $this->formId === (self::$submitted['id'] ?? '') && is_array(self::$submitted['data'] ?? null)
            && ($kind === 'group' || $kind === (self::$submitted['kind'] ?? ''));
        $raw = $sticky ? self::$submitted['data'] : [];
        $sections = match ($kind) {
            'period' => [__('Om kursperioden', 'reginor-lite') => ['title', 'start_date', 'end_date', 'default_session_count', 'default_room_id', 'default_price_minor', 'default_price_basis'],
                __('Synlighet og påmelding', 'reginor-lite') => ['visible_from', 'visible_until', 'sales_from', 'sales_until', 'show_as_upcoming', 'default_view', 'calendar_enabled'],
                __('Flere valg', 'reginor-lite') => ['timezone', 'cancelled', 'breaks']],
            'group' => [__('Undervisning', 'reginor-lite') => ['title', 'level_id', 'weekday', 'start_time', 'end_time', 'session_count', 'room_id', 'course_features'],
                __('Pris og påmelding', 'reginor-lite') => ['price_minor', 'price_from', 'price_basis', 'price_terms', 'registration_status', 'registration_dates', 'registration_url', 'registration_scope'],
                __('Flere valg', 'reginor-lite') => ['first_date', 'latest_date', 'instructor_ids', 'timezone', 'currency', 'breaks']],
            'venue' => [__('Om kursstedet', 'reginor-lite') => ['title', 'address']],
            'room' => [__('Om salen', 'reginor-lite') => ['title', 'venue_id']],
            default => [__('Om oppføringen', 'reginor-lite') => array_keys(StateSchema::data($kind)['properties'])],
        };
        foreach ($sections as $section => $keys) {
            if ($kind === 'group' && $part !== null) {
                $current = in_array('registration_url', $keys, true) ? 'registration' : (in_array('first_date', $keys, true) ? 'advanced' : 'teaching');
                if ($part !== $current) { continue; }
            }
            $advanced = $section === __('Flere valg', 'reginor-lite');
            if ($advanced) { echo '<details' . ($sticky ? ' open' : '') . ('><summary>' . esc_html(__('Kursfrie dager og flere valg', 'reginor-lite')) . '</summary>'); }
            else { echo '<fieldset class="rnl-field-section"' . ($kind === 'group' && in_array('registration_status', $keys, true) ? ' id="rnl-course-registration"' : '') . '><legend>' . esc_html($section) . '</legend>'; }
            echo '<div class="rnl-field-grid">';
            foreach ($keys as $key) {
            if ($key === 'registration_dates') {
                echo '</div><details><summary>' . esc_html(__('Egne påmeldingsdatoer (valgfritt)', 'reginor-lite')) . '</summary><p class="rnl-help">' . esc_html(__('La datoene stå tomme for å følge LetsReg i automatisk modus, eller perioden ved manuell status. Egne datoer begrenser dette vinduet.', 'reginor-lite')) . '</p><div class="rnl-field-grid">';
                foreach (['registration_from', 'registration_until'] as $dateKey) {
                    $dateValue = $data[$dateKey] ?? null;
                    $this->input($dateKey, $dateValue ? (new \DateTimeImmutable($dateValue))->setTimezone(new \DateTimeZone($data['timezone']))->format('Y-m-d\TH:i') : '', 'datetime-local');
                }
                echo '</div></details><div class="rnl-field-grid">'; continue;
            }
            if ($key === 'course_features') { $this->courseFeatures($this->formCommand === 'preview_import' ? 0 : $this->formId, $data); continue; }
            $schema = StateSchema::data($kind)['properties'][$key];
            if (in_array($key, ['sessions', 'period_id', 'course_id', 'period_version', 'audience'], true)) { continue; }
            $value = $sticky ? ($raw[$key] ?? '') : ($data[$key] ?? '');
            if (!in_array($key, ['breaks', 'instructor_ids'], true) && !is_scalar($value) && $value !== null) { $value = ''; }
            if ($key === 'breaks') { echo '</div>'; $this->breaks(is_array($value) ? $value : []); echo '<div class="rnl-field-grid">'; }
            elseif ($key === 'instructor_ids') { $this->instructors(array_map('intval', (array) $value)); }
            elseif (in_array($key, ['room_id', 'default_room_id', 'venue_id'], true)) { $this->select($key, $value, $this->choices($key === 'venue_id' ? 'venue' : 'room')); }
            elseif ($key === 'level_id') { $this->select($key, $value, $this->levelChoices((int) $value)); }
            elseif ($key === 'weekday') { $this->select($key, $value, [1 => __('Mandag', 'reginor-lite'), __('Tirsdag', 'reginor-lite'), __('Onsdag', 'reginor-lite'), __('Torsdag', 'reginor-lite'), __('Fredag', 'reginor-lite'), __('Lørdag', 'reginor-lite'), __('Søndag', 'reginor-lite')]); }
            elseif (isset($schema['enum'])) { $this->select($key, $value, array_combine($schema['enum'], array_map(static fn ($v) => self::options()[$v] ?? $v, $schema['enum']))); }
            elseif ($schema['type'] === 'boolean') { if (in_array($key, ['calendar_enabled', 'price_from'], true)) { $this->hidden('data[' . $key . ']', '0'); } $this->checkbox('data[' . $key . ']', self::labels()[$key], (bool) $value); }
            elseif (str_ends_with($key, 'price_minor')) { $this->input($key, $sticky ? $value : number_format((int) $value / 100, 2, ',', '')); }
            elseif (in_array($key, ['visible_from', 'visible_until', 'sales_from', 'sales_until'], true)) {
                $local = $sticky ? $value : ($value ? (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone($data['timezone']))->format('Y-m-d\TH:i') : '');
                $this->input($key, $local, 'datetime-local');
            } else {
                $type = str_ends_with($key, '_date') ? 'date' : (str_ends_with($key, '_time') ? 'time' : ($schema['type'] === 'integer' ? 'number' : 'text'));
                if (in_array($key, ['description', 'level_description', 'partner_info', 'price_terms'], true)) { $type = 'textarea'; }
                $this->input($key, $value, $type);
            }
            }
            echo '</div>' . ($advanced ? '</details>' : '</fieldset>');
        }
        if ($kind === 'venue') {
            $values = $data;
            foreach (['address', 'latitude', 'longitude'] as $key) { $values[$key] = $this->submittedValue('data[' . $key . ']', $data[$key] ?? ''); }
            \RegiNor\Lite\Infrastructure\VenueMap::picker($values);
        }
        if ($kind === 'room') {
            echo '<section class="rnl-field-section" data-rnl-appearance-root><h3>' . esc_html(__('Salens farge i kalenderen', 'reginor-lite')) . '</h3>';
            $this->hidden('data[appearance_custom]', '0');
            $this->checkbox('data[appearance_custom]', __('Bruk egen farge for denne salen', 'reginor-lite'), (bool) $this->submittedValue('data[appearance_custom]', $data['appearance_custom'] ?? false));
            $choice = [];
            foreach (['source' => '', 'alpha' => 100, 'tone' => 'auto'] as $key => $default) { $choice[$key] = $this->submittedValue('data[appearance_' . $key . ']', $data['appearance_' . $key] ?? $default); }
            AppearancePage::color('room', __('Salbakgrunn', 'reginor-lite'), __('Gjelder denne salen på alle dager. Fjern haken for å bruke standardfargen under Utseende.', 'reginor-lite'), (string) $this->submittedValue('data[appearance_color]', $data['appearance_color'] ?? '#f1e9e4'), $choice, true, 'data');
            if (\RegiNor\Lite\Infrastructure\ColorPalette::avada()) { echo '<iframe hidden data-rnl-palette-frame title="' . esc_attr(__('Henter nettstedets globale farger', 'reginor-lite')) . '" src="' . esc_url(add_query_arg('rnl_palette', wp_create_nonce('rnl_palette'), home_url('/'))) . '"></iframe>'; }
            echo '</section>';
        }
    }
    private function breaks(array $breaks): void
    {
        echo '<fieldset class="rnl-breaks"><legend>' . esc_html(__('Kursfrie dager og perioder', 'reginor-lite')) . '</legend><p class="rnl-help">' . esc_html(__('Legg til ett opphold om gangen, for eksempel høstferien. Datoene må ligge innenfor kursperioden. Opphold kan flytte siste kurskveld; kontroller forslaget før du lagrer.', 'reginor-lite')) . '</p>';
        // Never re-append submitted empty rows. Retain partially completed rows after an error.
        $breaks = array_values(array_filter($breaks, static fn ($b) => is_array($b) && !isset($b['remove']) && array_filter($b, static fn ($v) => is_string($v) && $v !== '')));
        echo '<div data-break-rows>';
        foreach ($breaks as $i => $row) { $this->breakRow($row, (string) $i); }
        echo '</div><details data-break-fallback><summary>' . esc_html(__('Legg til kursfri dag eller periode', 'reginor-lite')) . '</summary>';
        $this->breakRow([], (string) count($breaks));
        echo '</details><button type="button" class="rnl-button rnl-button-secondary" data-add-break hidden>' . esc_html(__('Legg til kursfri dag eller periode', 'reginor-lite')) . '</button><p data-break-status class="rnl-help" aria-live="polite"></p><noscript><p class="rnl-help">' . esc_html(__('Uten JavaScript: legg til ett opphold, lagre og åpne skjemaet igjen for å legge til flere.', 'reginor-lite')) . '</p></noscript></fieldset>';
    }
    private function breakRow(array $row, string $index): void
    {
        echo '<div class="rnl-break-row" data-break-row><div class="rnl-field-grid">';
        $base = 'data[breaks][' . $index . ']'; $this->hidden($base . '[id]', is_string($row['id'] ?? null) ? $row['id'] : '');
        foreach (['from', 'until', 'reason'] as $key) {
            $this->input($key, is_string($row[$key] ?? null) ? $row[$key] : '', $key === 'reason' ? 'text' : 'date', $base . '[' . $key . ']');
        }
        $this->checkbox($base . '[remove]', __('Fjern dette oppholdet', 'reginor-lite'));
        echo '</div></div>';
    }

    private function index(): void
    {
        echo '<p><a class="rnl-button" href="' . esc_url(admin_url('admin.php?page=reginor-lite&new=1')) . ('"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html(__(' Opprett kursperiode', 'reginor-lite')) . '</a></p>');
        $rows = (new PeriodOverview($this->repo))->rows();
        if (!$rows) { echo ('<p>' . esc_html(__('Ingen kursperioder ennå. Start med å opprette den første perioden.', 'reginor-lite')) . '</p>'); }
        else {
            echo ('<div class="rnl-scroll" role="region" aria-label="' . esc_attr(__('Alle kursperioder', 'reginor-lite')) . '" tabindex="0"><table class="widefat rnl-period-table"><caption>' . esc_html(__('Alle kursperioder – nyeste øverst', 'reginor-lite')) . '</caption><thead><tr><th scope="col">' . esc_html(__('Periode', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Sted', 'reginor-lite')) . '</th><th scope="col">Fra–til</th><th scope="col">' . esc_html(__('Status', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Dager', 'reginor-lite')) . '</th><th scope="col">' . esc_html(__('Handlinger', 'reginor-lite')) . '</th></tr></thead><tbody>');
            foreach ($rows as $id => $row) {
                $status = $row['cancelled'] ? __('Avlyst', 'reginor-lite') : ($row['active'] ? __('Aktiv nå', 'reginor-lite') : ($row['next'] ? __('Neste', 'reginor-lite') : ($row['upcoming'] ? __('Kommende', 'reginor-lite') : __('Avsluttet', 'reginor-lite'))));
                if (!$row['published']) { $status = ($row['next'] ? __('Neste · ', 'reginor-lite') : '') . __('Kladd', 'reginor-lite'); }
                $days = $row['days'] === null ? '–' : ($row['active']
                    ? sprintf(/* translators: %d: calendar days remaining in the course period. */ _n('%d dag igjen', '%d dager igjen', $row['days'], 'reginor-lite'), $row['days'])
                    : sprintf(/* translators: %d: calendar days until the course period starts. */ _n('Oppstart om %d dag', 'Oppstart om %d dager', $row['days'], 'reginor-lite'), $row['days']));
                if ($row['active'] && $row['days'] === 0) { $days = __('Siste kursdag i dag', 'reginor-lite'); }
                echo '<tr class="' . ($row['active'] ? 'rnl-period-active' : ($row['next'] ? 'rnl-period-next' : '')) . '"><th scope="row"><a href="' . $this->link($id) . '">' . esc_html($row['title']) . '</a></th><td>' . esc_html(implode(', ', $row['places']) ?: __('Sted velges senere', 'reginor-lite')) . '</td><td>' . esc_html(wp_date(get_option('date_format'), strtotime($row['from'] . 'T12:00:00Z'), new \DateTimeZone('UTC')) . ' – ' . ($row['until'] ? wp_date(get_option('date_format'), strtotime($row['until'] . 'T12:00:00Z'), new \DateTimeZone('UTC')) : __('Sluttdato beregnes fra kursene', 'reginor-lite'))) . '</td><td>' . esc_html($status) . '</td><td>' . esc_html($days) . '</td><td><div class="rnl-row-actions">';
                $this->iconLink($this->link($id), 'visibility', __('Åpne', 'reginor-lite'), __('Se kursene og følg opp denne kursperioden.', 'reginor-lite'));
                $this->iconLink($this->link($id, 0, 'period'), 'edit', __('Rediger', 'reginor-lite'), __('Endre periodens datoer, standardvalg og kursfrie dager.', 'reginor-lite'));
                $this->iconLink($this->link($id, 0, 'manage') . '#rnl-copy', 'admin-page', __('Kopier', 'reginor-lite'), __('Lag en ny periode som kladd. Påmeldingslenker og tidsvinduer fylles ut på nytt.', 'reginor-lite'));
                if (!$row['published']) { $this->iconLink($this->link($id, 0, 'manage') . '#rnl-trash', 'trash', __('Papirkurv', 'reginor-lite')); }
                echo '</div></td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo ('<details><summary>' . esc_html(__('Papirkurv', 'reginor-lite')) . '</summary>');
        foreach ($this->repo->listing('period', true) as $id => $state) {
            echo '<h3>' . esc_html($state['data']['title']) . '</h3>';
            $groups = array_filter($this->repo->listing('group', true), static fn ($g) => $g['data']['period_id'] === $id && ($g['event'] ?? '') === 'trashed');
            $this->lifecycleForm($id, $state, $groups, 'restore', __('Gjenopprett som kladd', 'reginor-lite'));
        }
        echo '</details>';
    }
    private function iconLink(string $url, string $icon, string $label, string $help = '', bool $external = false): void
    {
        $tip = 'rnl-action-' . ++$this->field;
        echo '<span class="rnl-action-wrap"><a href="' . esc_url($url) . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . ' class="rnl-icon-action" aria-label="' . esc_attr($label) . '" aria-describedby="' . $tip . '"><span class="dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span></a><span id="' . $tip . '" class="rnl-action-tip" role="tooltip"><strong>' . esc_html($label) . '</strong>' . ($help ? '<br>' . esc_html($help) : '') . '</span></span>';
    }
    private function courseBadges(int $id, array $data): string
    {
        $badges = [];
        if (\RegiNor\Lite\Infrastructure\Appearance::course($id)['featured']) { $badges[] = ['star-filled', __('Fremhevet kurs', 'reginor-lite'), __('Dette kurset er fremhevet i kursoversikten.', 'reginor-lite')]; }
        if (!empty($data['dropin_enabled'])) { $badges[] = ['tickets-alt', __('Drop-in', 'reginor-lite'), sprintf(/* translators: %s: drop-in price in NOK. */ __('Dette kurset tilbyr drop-in. %s kr per person og kurskveld.', 'reginor-lite'), number_format_i18n(($data['dropin_price_minor'] ?? 0) / 100, 2))]; }
        $html = '<div class="rnl-course-badges">';
        foreach ($badges as [$icon, $label, $help]) {
            $tip = 'rnl-action-' . ++$this->field;
            $html .= '<span class="rnl-action-wrap"><button type="button" class="rnl-status-icon" aria-label="' . esc_attr($label) . '" aria-describedby="' . $tip . '"><span class="dashicons dashicons-' . $icon . '" aria-hidden="true"></span></button><span id="' . $tip . '" class="rnl-action-tip" role="tooltip">' . esc_html($help) . '</span></span>';
        }
        return $html . ($badges ? '' : '<span aria-label="' . esc_attr(__('Ingen merker', 'reginor-lite')) . '">–</span>') . '</div>';
    }
    private function createPeriod(): void
    {
        echo ('<h2>' . esc_html(__('1. Opprett kursperiode', 'reginor-lite')) . '</h2><p>' . esc_html(__('Fyll ut perioden én gang. Nye kurs får disse forslagene automatisk.', 'reginor-lite')) . '</p>');
        $this->start('save'); $this->hidden('kind', 'period');
        $this->fields('period', ['timezone' => 'Europe/Oslo', 'default_session_count' => 6, 'default_price_basis' => 'person']);
        $this->end(__('Lagre og legg til kurs', 'reginor-lite'));
    }
    private function period(int $id): void
    {
        $state = $this->repo->get($id, 'period'); $groups = $this->repo->groups($id); $draft = get_post_status($id) === 'draft';
        echo '<h2>' . esc_html($state['data']['title']) . '</h2><p>' . esc_html($draft ? __('Kladd', 'reginor-lite') : __('Publisert i kursoppsettet', 'reginor-lite')) . '</p>';
        echo ('<nav class="rnl-step-nav" aria-label="' . esc_attr(__('Oppsett av kursperioden', 'reginor-lite')) . '">');
        foreach (['period' => __('1. Periode', 'reginor-lite'), 'courses' => __('2. Kurs', 'reginor-lite'), 'publish' => __('3. Publiser', 'reginor-lite')] as $step => $label) {
            echo '<a href="' . $this->link($id, 0, $step) . '"' . ($this->step === $step ? ' aria-current="step"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        if (isset($_GET['import']) && $this->step === 'courses') { $this->importCourse($id, $state); return; }
        $calendarStatus = \RegiNor\Lite\Infrastructure\TecBridge::status($id);
        if ($calendarStatus !== '') {
            $calendarLinks = CalendarSettings::periodActions($id);
            echo '<p class="rnl-notice">' . esc_html($calendarStatus) . (isset($calendarLinks['rnl_view_period']) ? ' ' . $calendarLinks['rnl_view_period'] : '') . '</p>';
        }
        if ($draft && $this->step === 'courses') { echo ('<div class="rnl-next-step"><strong>' . esc_html(__('Neste steg', 'reginor-lite')) . '</strong><p>') . esc_html($groups ? __('Åpne kursene for å kontrollere datoene. Når alt er klart, bruker du publiseringskontrollen nedenfor.', 'reginor-lite') : __('Legg til det første kurset nedenfor. Perioden er lagret som kladd og vises ikke for deltakerne.', 'reginor-lite')) . '</p></div>'; }
        elseif (!$draft) { echo ('<p>' . esc_html(__('Kursene er tilgjengelige på den valgte kurssiden innenfor tidspunktene du har valgt.', 'reginor-lite')) . '</p>'); }

        if ($this->step === 'period') {
        SharingSettings::render($id);
        if ($draft) {
            echo ('<h3>' . esc_html(__('Rediger perioden og felles opphold', 'reginor-lite')) . '</h3>');
            $this->start('save', $id, $state['version']); $this->hidden('kind', 'period'); $this->fields('period', $state['data']); $this->end(__('Lagre og gå til kurs', 'reginor-lite'));
        } else { $this->lifecycleForm($id, $state, $groups, 'draft', __('Ta hele perioden tilbake til kladd', 'reginor-lite')); }
        return;
        }
        if ($this->step === 'courses') {
        if ($draft && \RegiNor\Lite\Infrastructure\LetsRegMapping::canImport()) { echo '<p><a class="rnl-button rnl-button-secondary" href="' . esc_url(add_query_arg('import', '1', html_entity_decode($this->link($id), ENT_QUOTES))) . '">' . esc_html(__('Hent nytt kurs fra LetsReg', 'reginor-lite')) . '</a></p>'; }
        echo ('<h2>' . esc_html(__('Kurs i perioden', 'reginor-lite')) . '</h2><div class="rnl-scroll" role="region" aria-label="' . esc_attr(__('Kursoversikt', 'reginor-lite')) . '" tabindex="0"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Kurs', 'reginor-lite')) . '</th><th>' . esc_html(__('Dag', 'reginor-lite')) . '</th><th>' . esc_html(__('Tid', 'reginor-lite')) . '</th><th>' . esc_html(__('Sal', 'reginor-lite')) . '</th><th><span class="screen-reader-text">' . esc_html(__('Merker', 'reginor-lite')) . '</span></th><th>' . esc_html(__('Påmelding', 'reginor-lite')) . '</th><th>' . esc_html(__('Kontroll', 'reginor-lite')) . '</th><th>' . esc_html(__('Handlinger', 'reginor-lite')) . '</th></tr></thead><tbody>');
        $review = $this->repo->periodReview($id);
        foreach ($groups as $groupId => $g) {
            $d = $g['data']; $room = $d['room_id'] ? $this->repo->resource($d['room_id'], 'room')['title'] : __('Mangler', 'reginor-lite');
            echo '<tr><td><div class="rnl-course-name"><a href="' . $this->link($id, $groupId) . '">' . esc_html($d['title']) . '</a>';
            if ($d['registration_url'] !== '' && in_array(strtolower((string) wp_parse_url($d['registration_url'], PHP_URL_HOST)), \RegiNor\Lite\Infrastructure\RegistrationDomains::allowed(), true)) {
                $this->iconLink($d['registration_url'], 'external', sprintf(/* translators: %s: course title. */ __('Åpne %s hos LetsReg (ny fane)', 'reginor-lite'), $d['title']), __('Kontroller kursets påmeldingsside hos LetsReg. Åpnes i en ny fane.', 'reginor-lite'), true);
            }
            echo '</div></td><td>' . esc_html([1 => __('Man', 'reginor-lite'), __('Tirs', 'reginor-lite'), __('Ons', 'reginor-lite'), __('Tors', 'reginor-lite'), __('Fre', 'reginor-lite'), __('Lør', 'reginor-lite'), __('Søn', 'reginor-lite')][$d['weekday']]) . '</td><td>' . esc_html($d['start_time'] . '–' . $d['end_time']) . '</td><td>' . esc_html($room) . '</td><td>' . $this->courseBadges($groupId, $d) . '</td><td>'; RegistrationStatusControl::render($groupId, $g, $state['data']); echo '</td><td>' . esc_html($review[$groupId]['needs_schedule_review'] ? __('Må gjennomgås etter periodeendring', 'reginor-lite') : __('Planen er oppdatert', 'reginor-lite')); LetsRegChangesPanel::badge($groupId, $d); echo '</td><td>';
            $this->iconLink($this->link($id, $groupId), 'edit', __('Åpne kurset', 'reginor-lite')); echo '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($draft) {
            echo '<details' . (!$groups || (self::$error !== '' && (self::$submitted['command'] ?? '') === 'add_group') ? ' open' : '') . ('><summary>' . esc_html(__('Legg til kurs', 'reginor-lite')) . '</summary><p>' . esc_html(__('Velg kurset deltakerne skal gå på, og hvilken dag dere underviser.', 'reginor-lite')) . '</p>'); $this->start('add_group', $id, $state['version']); $this->select('course_id', 0, $this->choices('course'), 'course_id');
            $this->select('weekday', 1, [1 => __('Mandag', 'reginor-lite'), __('Tirsdag', 'reginor-lite'), __('Onsdag', 'reginor-lite'), __('Torsdag', 'reginor-lite'), __('Fredag', 'reginor-lite'), __('Lørdag', 'reginor-lite'), __('Søndag', 'reginor-lite')]); $this->input('start_time', '18:00', 'time'); $this->input('end_time', '19:00', 'time'); $this->end(__('Opprett kurs', 'reginor-lite')); echo '</details>';
        }
        echo '<p><a class="rnl-button" href="' . $this->link($id, 0, 'publish') . ('">' . esc_html(__('Gå til publisering →', 'reginor-lite')) . '</a></p>');
        }
        if ($this->step === 'publish' && $draft) {
            echo ('<h2>' . esc_html(__('Kontroll før publisering', 'reginor-lite')) . '</h2><p>' . esc_html(__('Kontroller alle kurs, faktiske datoer og vinduer før hele perioden publiseres.', 'reginor-lite')) . '</p>');
            foreach (['visible_from', 'visible_until', 'sales_from', 'sales_until'] as $key) { echo '<p>' . esc_html(self::labels()[$key] . ': ' . $this->local($state['data'][$key], $state['data']['timezone'])) . '</p>'; }
            $this->start('preview_publication', $id, $state['version']);
            $this->hidden('windows', '1');
            $this->end(__('Kontroller og forhåndsvis publisering', 'reginor-lite'));
        }
        if ($this->step !== 'manage') { echo '<p><a href="' . $this->link($id, 0, 'manage') . ('">' . esc_html(__('Kopiering, historikk og papirkurv', 'reginor-lite')) . '</a></p>'); return; }
        if ($draft) { echo '<div id="rnl-trash">'; $this->lifecycleForm($id, $state, $groups, 'trash', __('Flytt kladd og kurs til papirkurven', 'reginor-lite')); echo '</div>'; }
        if ($draft) {
            echo ('<h3>' . esc_html(__('Enkeltkurs i papirkurven', 'reginor-lite')) . '</h3>');
            foreach ($this->repo->listing('group', true) as $gid => $g) {
                if ($g['data']['period_id'] !== $id || ($g['event'] ?? '') !== 'group_trashed') { continue; }
                echo '<p>' . esc_html($g['data']['title']) . '</p>';
                $this->start('group_lifecycle', $gid, $g['version']); $this->hidden('period_version', (string) $state['version']); $this->hidden('target', 'restore'); $this->end(__('Gjenopprett kurset', 'reginor-lite'));
            }
        }
        echo ('<details id="rnl-copy" open><summary>' . esc_html(__('Kopier til neste periode', 'reginor-lite')) . '</summary><p>' . esc_html(__('Du får en ny kladd med nye datoer. Påmeldingslenker og tidsvinduer fylles ut på nytt.', 'reginor-lite')) . '</p>'); $this->start('copy', $id, $state['version']); $this->input('title', ''); $this->input('start_date', '', 'date'); $this->end(__('Opprett ny kopi som kladd', 'reginor-lite')); echo '</details>';
        $this->history($state);
    }
    private function local(?string $utc, string $timezone): string { return $utc ? (new \DateTimeImmutable($utc))->setTimezone(new \DateTimeZone($timezone))->format('d.m.Y H:i') . ' (' . $timezone . ')' : __('Ikke satt', 'reginor-lite'); }
    private function lifecycleForm(int $id, array $state, array $groups, string $target, string $label): void
    {
        $danger = $target !== 'restore';
        if ($danger) { echo '<details class="rnl-danger"><summary>' . esc_html($target === 'trash' ? __('Flytt perioden til papirkurven', 'reginor-lite') : __('Rediger en publisert periode', 'reginor-lite')) . '</summary><p>' . esc_html($target === 'trash' ? __('Hele kladden og kursene i den flyttes til papirkurven. Du kan gjenopprette den senere.', 'reginor-lite') : __('Perioden blir skjult for deltakerne mens du redigerer. Kontroller og publiser den på nytt når du er ferdig.', 'reginor-lite')) . '</p>'; }
        $this->start('lifecycle', $id, $state['version']); $this->hidden('target', $target); $this->hidden('groups', $this->encoded(array_map(static fn ($g) => $g['version'], $groups))); $this->end($label); if ($danger) { echo '</details>'; }
    }
    private function importCourse(int $id, array $state): void
    {
        \RegiNor\Lite\Infrastructure\LetsRegMapping::authorizeImport();
        $data = array_replace($this->repo->newGroupDefaults($id), ['weekday' => 1, 'start_time' => '18:00', 'end_time' => '19:00', 'room_id' => 0]);
        echo '<p><a href="' . $this->link($id) . '">' . esc_html(__('Tilbake til kursene i perioden', 'reginor-lite')) . '</a></p><h2>' . esc_html(__('Hent nytt kurs fra LetsReg', 'reginor-lite')) . '</h2><p>' . esc_html(__('Velg ett eller flere arrangementer. Kontroller beskrivelse, sal og datoer for hvert kurs før du oppretter kursutkastene samlet. Ingenting publiseres automatisk.', 'reginor-lite')) . '</p>';
        echo '<noscript><p>' . esc_html(__('Import krever JavaScript. Du kan også gå tilbake og bruke Legg til kurs for å opprette kurset manuelt.', 'reginor-lite')) . '</p></noscript><div hidden data-rnl-import-workspace data-rnl-course-editor>';
        LetsRegCoursePicker::render($id, ['version' => $state['version'], 'data' => $data], true);
        echo '<h3>' . esc_html(__('Kursoppsett for det nye utkastet', 'reginor-lite')) . '</h3>';
        $this->start('preview_import', $id, $state['version'], 'rnl-course-editor');
        echo '<section class="rnl-field-section"><h3>' . esc_html(__('Kursbeskrivelse', 'reginor-lite')) . '</h3><p class="rnl-field-card"><label for="rnl-description-mode">' . esc_html(__('Beskrivelsen deltakerne skal se', 'reginor-lite')) . '</label><select id="rnl-description-mode" name="description_mode"><option value="new">' . esc_html(__('Hent og gjenbruk fra LetsReg', 'reginor-lite')) . '</option><option value="existing">' . esc_html(__('Bruk eksisterende lokal beskrivelse', 'reginor-lite')) . '</option></select></p><div data-existing-description hidden>';
        $this->select('course_id', 0, $this->choices('course'), 'course_id');
        echo '</div><div data-new-description><p class="rnl-help">' . esc_html(__('Teksten hentes som ren tekst. Identiske importerte beskrivelser gjenbrukes. Endringer på samme arrangement vises før lagring; lokale redigeringer beskyttes. Oppgi dansestil og kontroller nivå og partnerinformasjon.', 'reginor-lite')) . '</p>';
        LetsRegTemplateHelp::render();
        foreach (['title', 'description', 'dance_style', 'level_description', 'partner_info'] as $key) { $this->input($key, '', in_array($key, ['description', 'level_description', 'partner_info'], true) ? 'textarea' : 'text', 'new_course[' . $key . ']'); }
        echo '<p class="rnl-field-card"><label for="rnl-description-choice">' . esc_html(__('Når beskrivelsen finnes fra før', 'reginor-lite')) . '</label><select id="rnl-description-choice" name="description_choice"><option value="auto">' . esc_html(__('Foreslå gjenbruk eller oppdatering', 'reginor-lite')) . '</option><option value="update">' . esc_html(__('Oppdater eksisterende også ved større avvik', 'reginor-lite')) . '</option><option value="separate">' . esc_html(__('Opprett separat hvis teksten er forskjellig', 'reginor-lite')) . '</option></select><span class="rnl-help">' . esc_html(__('Du får se hva som skjer før import. Identisk tekst lager aldri en ny kopi. Lokalt redigerte beskrivelser må flettes manuelt eller beholdes med en separat import.', 'reginor-lite')) . '</span></p></div></section>';
        $this->fields('group', $data, 'teaching'); $this->fields('group', $data, 'registration');
        $this->fields('group', $data, 'advanced'); $this->coursePresentation(0, $data);
        $this->end(__('Forhåndsvis kursutkastet', 'reginor-lite'));
        echo '<section data-import-preview hidden tabindex="-1" class="rnl-panel"></section><section data-import-batch hidden class="rnl-panel" tabindex="-1"></section></div>';
    }

    public static function importPreview(array $proposal): string
    {
        $page = new self(); ob_start();
        echo '<h3>' . esc_html(__('Kontroller kursutkastet', 'reginor-lite')) . '</h3>';
        $page->groupSummary($proposal['data'], $proposal['new_course'] ?? null); $page->sessions($proposal['data']['sessions']);
        if (isset($proposal['new_course'])) {
            $plan = $proposal['description_plan'] ?? ['action'=>'create'];
            $labels = ['create'=>__('Ny kursbeskrivelse', 'reginor-lite'), 'reuse'=>__('Eksisterende beskrivelse gjenbrukes uten endring', 'reginor-lite'), 'update'=>__('Eksisterende beskrivelse oppdateres ved bekreftelse', 'reginor-lite'), 'conflict'=>__('Velg hvordan beskrivelsen skal behandles', 'reginor-lite')];
            echo '<h4>' . esc_html($labels[$plan['action']]) . '</h4>';
            if (isset($plan['before']) && $plan['action'] !== 'reuse') {
                echo '<p class="rnl-notice">' . esc_html(sprintf(/* translators: %d: number of local courses sharing this description. */ __('Denne beskrivelsen brukes av %d lokale kurs. En oppdatering påvirker alle, også publiserte kurs. Sammenlign feltene før du bekrefter.', 'reginor-lite'), count($plan['references']))) . '</p><div class="rnl-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Felt', 'reginor-lite')) . '</th><th>' . esc_html(__('Lokalt nå', 'reginor-lite')) . '</th><th>' . esc_html(__('Etter godkjenning', 'reginor-lite')) . '</th></tr></thead><tbody>';
                foreach (['title'=>__('Navn', 'reginor-lite'),'description'=>__('Beskrivelse', 'reginor-lite'),'dance_style'=>__('Dansestil', 'reginor-lite'),'level_description'=>__('Nivåforklaring', 'reginor-lite'),'partner_info'=>__('Partnerinformasjon', 'reginor-lite')] as $key=>$label) {
                    if (($plan['before'][$key] ?? '') === ($proposal['new_course'][$key] ?? '')) { continue; }
                    echo '<tr><th>' . esc_html($label) . '</th><td class="rnl-preserve-lines">' . \RegiNor\Lite\Infrastructure\RichText::html($plan['before'][$key] ?? '') . '</td><td class="rnl-preserve-lines">' . \RegiNor\Lite\Infrastructure\RichText::html($proposal['new_course'][$key] ?? '') . '</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            if ($plan['action'] === 'create') {
                echo '<dl>';
                foreach (['description'=>__('Kursbeskrivelse', 'reginor-lite'), 'dance_style'=>__('Dansestil', 'reginor-lite'), 'level_description'=>__('Nivå og forkunnskaper', 'reginor-lite'), 'partner_info'=>__('Partnerinformasjon', 'reginor-lite')] as $key=>$label) {
                    echo '<dt>' . esc_html($label) . '</dt><dd class="rnl-preserve-lines">' . \RegiNor\Lite\Infrastructure\RichText::html($proposal['new_course'][$key] ?? '') . '</dd>';
                }
                echo '</dl>';
            }
        }
        if ($proposal['issues']) {
            echo '<div data-import-issues class="rnl-validation-summary"><h4>' . esc_html(__('Dette må rettes før import', 'reginor-lite')) . '</h4>';
            $page->messages($proposal['issues']); echo '</div>';
        }
        if ($proposal['warnings']) {
            echo '<section class="rnl-notice"><h4>' . esc_html(__('Til informasjon – disse punktene blokkerer ikke import', 'reginor-lite')) . '</h4>';
            $page->messages($proposal['warnings']); echo '</section>';
        }
        if ($proposal['conflicts']) { echo '<p class="rnl-notice">' . esc_html(__('Timeplanen har en sal- eller instruktørkonflikt. Du kan opprette kladden, men konflikten må løses før publisering.', 'reginor-lite')) . '</p>'; }
        if (!$proposal['issues']) { echo '<button type="button" class="rnl-button" data-confirm-import>' . esc_html(__('Opprett kursutkast', 'reginor-lite')) . '</button>'; }
        return ob_get_clean();
    }

    private function courseFeatures(int $id, array $data): void
    {
        $c = \RegiNor\Lite\Infrastructure\Appearance::course($id);
        foreach (['featured' => [__('Fremhev dette kurset', 'reginor-lite'), $c['featured']], 'dropin_enabled' => [__('Dette kurset tilbyr drop-in', 'reginor-lite'), $data['dropin_enabled'] ?? false]] as $key => [$label, $value]) {
            $this->hidden('data[' . $key . ']', '0'); $this->checkbox('data[' . $key . ']', $label, (bool) $this->submittedValue('data[' . $key . ']', $value));
        }
        $this->input('dropin_price_minor', isset($data['dropin_price_minor']) ? number_format($data['dropin_price_minor'] / 100, 2, ',', '') : '');
    }
    private function coursePresentation(int $id, array $data): void
    {
        $c = \RegiNor\Lite\Infrastructure\Appearance::course($id);
        echo '<section class="rnl-field-section" data-rnl-appearance-root><h3>' . esc_html(__('Utseende', 'reginor-lite')) . '</h3><p class="rnl-help">' . esc_html(__('Velg en egen bakgrunn for dette kurset. Fremheving velges under Undervisning og bruker fremhevingsfargen fra nettstedets Utseende-oppsett.', 'reginor-lite')) . '</p>';
        $this->hidden('data[appearance_custom]', '0');
        $this->checkbox('data[appearance_custom]', __('Bruk egen bakgrunn på kurset', 'reginor-lite'), (bool) $this->submittedValue('data[appearance_custom]', $c['color'] !== ''));
        foreach (['color', 'source', 'alpha', 'tone'] as $key) { $c[$key] = $this->submittedValue('data[appearance_' . $key . ']', $c[$key]); }
        \RegiNor\Lite\Admin\AppearancePage::color('course-' . $id, __('Kursets bakgrunnsfarge', 'reginor-lite'), __('Velg egen farge eller global fargeprøve. Fjern avkrysningen for egen bakgrunn for å bruke standardfargen igjen.', 'reginor-lite'), $c['color'] ?: '#ffffff', $c, true, 'data');
        if (\RegiNor\Lite\Infrastructure\ColorPalette::avada()) { echo '<iframe hidden data-rnl-palette-frame title="' . esc_attr(__('Henter globale farger', 'reginor-lite')) . '" src="' . esc_url(add_query_arg('rnl_palette', wp_create_nonce('rnl_palette'), home_url('/'))) . '"></iframe>'; }
        echo '</section>';
    }
    private function group(int $id, array $state): void
    {
        $d = $state['data'];
        $this->periodId = $d['period_id'];
        echo '<p><a href="' . $this->link($d['period_id']) . ('">' . esc_html(__('Tilbake til perioden', 'reginor-lite')) . '</a></p><h2>') . esc_html($d['title']) . '</h2>';
        echo ('<p>' . esc_html(__('Deltakerne ser nå: ', 'reginor-lite'))) . esc_html(self::options()[$this->repo->salesStatus($id)] ?? __('Skjult', 'reginor-lite')) . '.</p>';
        \RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore::summary($d);
        LetsRegCapacityPanel::render($d, true);
        if (get_post_status($id) !== 'draft') { LetsRegCoursePicker::render($id, $state); }
        if ($d['sessions']) { echo ('<details><summary>' . esc_html(__('Se lagrede kursdatoer', 'reginor-lite')) . '</summary>'); $this->sessions($d['sessions']); echo '</details>'; }
        if (get_post_status($id) === 'draft') {
            echo ('<h2>' . esc_html(__('Kursoppsett', 'reginor-lite')) . '</h2><div data-rnl-course-editor>');
            $periodState = $this->repo->get($d['period_id'], 'period');
            $period = $periodState['data'];
            $inheritedStart = false;
            foreach (array_merge($periodState['history'], [$periodState]) as $snapshot) {
                if ($snapshot['version'] === $d['period_version'] && $snapshot['data']['start_date'] === $d['start_date']) { $inheritedStart = true; break; }
            }
            // Preserve an earlier explicit baseline as the single visible override.
            if (!$inheritedStart && !$d['first_date'] && $d['start_date'] > $period['start_date']) {
                $date = new \DateTimeImmutable($d['start_date']);
                $d['first_date'] = $date->modify('+' . (($d['weekday'] - (int) $date->format('N') + 7) % 7) . ' days')->format('Y-m-d');
            }
            $this->start('preview_group', $id, $state['version'], 'rnl-course-editor');
            $this->fields('group', $d, 'teaching');
            $this->fields('group', $d, 'registration');
            // Keep the picker directly below registration without nesting its forms in the editor.
            echo '</form>';
            LetsRegCoursePicker::render($id, $state);
            // Associate every trailing control explicitly: valid HTML, natural tab order, and no nested forms.
            ob_start();
            $this->fields('group', $d, 'advanced'); $this->coursePresentation($id, $d);
            $tail = new \WP_HTML_Tag_Processor(ob_get_clean());
            while ($tail->next_tag()) {
                if (in_array($tail->get_tag(), ['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'], true)) { $tail->set_attribute('form', 'rnl-course-editor'); }
            }
            echo $tail->get_updated_html();
            submit_button(__('Forhåndsvis endringer og datoer', 'reginor-lite'), 'primary', 'submit', true, ['form' => 'rnl-course-editor']);
            echo '</div>';
            echo ('<details><summary>' . esc_html(__('Behold kursdatoene etter en periodeendring', 'reginor-lite')) . '</summary>'); $this->start('review', $id, $state['version']); echo ('<p>' . esc_html(__('Etter en periodeendring kan du beholde den faktiske planen, inkludert avlysninger og erstatningskvelder. Opphold kontrolleres på nytt.', 'reginor-lite')) . '</p>'); $this->end(__('Kontroller og behold datoene', 'reginor-lite')); echo '</details>';
            if ($d['sessions']) { echo '<details class="rnl-session-tools"' . (self::$error !== '' && isset(self::$submitted['session_id']) ? ' open' : '') . '><summary>' . esc_html(__('Endre én kurskveld', 'reginor-lite')) . '</summary><p class="rnl-help">' . esc_html(__('Velg datoen du vil flytte eller avlyse. Resten av kursplanen beholdes. Endringen vises før du bekrefter.', 'reginor-lite')) . '</p>'; }
            foreach ($d['sessions'] as $s) {
                echo '<details class="rnl-session-row"' . (self::$error !== '' && (self::$submitted['session_id'] ?? '') === $s['id'] ? ' open' : '') . '><summary>' . esc_html((new \DateTimeImmutable($s['date']))->format('d.m.Y') . ' · ' . $s['start_time'] . '–' . $s['end_time']) . '</summary>';
                $this->start('session', $id, $state['version']); $this->hidden('session_id', $s['id']);
                $this->input('date', $s['date'], 'date'); $this->input('start_time', $s['start_time'], 'time'); $this->input('end_time', $s['end_time'], 'time');
                $this->select('room_id', $s['room_id'], $this->choices('room')); $this->instructors($s['instructor_ids']);
                $this->select('status', $s['status'], ['moved' => __('Flyttet', 'reginor-lite'), 'cancelled' => __('Avlyst', 'reginor-lite')], 'data[status]'); $this->input('reason', $s['reason']); $this->end(__('Forhåndsvis øktendring', 'reginor-lite'));
                if ($s['status'] === 'cancelled') {
                    $this->start('replacement', $id, $state['version']); $this->hidden('session_id', $s['id']); $this->input('date', '', 'date'); $this->input('reason', ''); $this->end(__('Forhåndsvis erstatningskveld', 'reginor-lite'));
                }
                echo '</details>';
            }
            if ($d['sessions']) { echo '</details>'; }
            $parent = $this->repo->get($d['period_id'], 'period');
            echo ('<details class="rnl-danger"><summary>' . esc_html(__('Fjern dette kursutkastet', 'reginor-lite')) . '</summary>'); $this->start('group_lifecycle', $id, $state['version']); $this->hidden('period_version', (string) $parent['version']); $this->hidden('target', 'trash'); $this->end(__('Flytt dette kursutkastet til papirkurven', 'reginor-lite')); echo '</details>';
        } else { echo ('<p>' . esc_html(__('Du kan godkjenne LetsReg-tekster og redigere beskrivelsen mens kurset er publisert. Endringer i timeplan og øvrig kursoppsett krever at perioden tas tilbake til kladd.', 'reginor-lite')) . '</p>'); }
        $this->courseDescription($id, $state);
        SharingSettings::render($id);
        $this->history($state);
    }
    private function courseDescription(int $id, array $state): void
    {
        $courseId = $state['data']['course_id'];
        $course = $this->repo->resource($courseId, 'course');
        echo '<section class="rnl-panel" id="rnl-course-description"><h3>' . esc_html(__('Kursbeskrivelse, nivå og forkunnskaper', 'reginor-lite')) . '</h3><p class="rnl-help">' . esc_html(__('Dette er tekstene deltakerne ser. På publiserte kurs vises lagrede endringer straks, uten ny publisering. Kursbeskrivelse og nivåforklaring må være utfylt.', 'reginor-lite')) . '</p>';
        $uses = count(array_filter($this->repo->groups(), static fn (array $group): bool => $group['data']['course_id'] === $courseId));
        if ($uses > 1) {
            echo '<p class="rnl-notice">' . esc_html(sprintf(/* translators: %d: number of courses sharing this description. */ __('Denne beskrivelsen brukes av %d kurs. Endringer i tekstene gjelder alle disse kursene.', 'reginor-lite'), $uses)) . '</p>';
        }
        if (current_user_can('manage_options')) {
            $description = $this->repo->get($courseId, 'course');
            $this->start('save_course_description', $id, $state['version']);
            $this->hidden('course_version', (string) $description['version']);
            $this->input('description', $course['description'], 'textarea');
            $this->input('level_description', $course['level_description'], 'textarea');
            $this->input('dance_style', $course['dance_style']);
            $this->input('partner_info', $course['partner_info'], 'textarea');
            $this->input('price_terms', $state['data']['price_terms'], 'textarea');
            echo '<p class="rnl-help">' . esc_html(__('Prisvilkår og tillegg gjelder bare dette kurset. Prisbeløp og påmeldingsoppsett endres ikke her.', 'reginor-lite')) . '</p>';
            $this->end(__('Lagre beskrivelsestekster', 'reginor-lite'));
        } else {
            foreach (['description', 'level_description', 'partner_info', 'price_terms'] as $key) {
                $text = $key === 'price_terms' ? $state['data']['price_terms'] : $course[$key];
                echo '<h4>' . esc_html(self::labels()[$key]) . '</h4><div>' . \RegiNor\Lite\Infrastructure\RichText::html($text) . '</div>';
            }
            echo '<p class="rnl-help">' . esc_html(__('Be en administrator fylle ut eller endre disse felles tekstene. Administrator finner feltene her på kurssiden og under Kursinnhold og ressurser.', 'reginor-lite')) . '</p>';
        }
        echo '<p><a href="' . $this->link($state['data']['period_id'], 0, 'publish') . '">' . esc_html(__('Til publiseringskontrollen', 'reginor-lite')) . '</a></p></section>';
    }

    private function sessions(array $sessions): void
    {
        echo ('<h3>' . esc_html(__('Kursdatoer', 'reginor-lite')) . '</h3><div class="rnl-scroll" role="region" aria-label="' . esc_attr(__('Kursoversikt', 'reginor-lite')) . '" tabindex="0"><table class="widefat striped"><thead><tr><th>' . esc_html(__('Dato', 'reginor-lite')) . '</th><th>' . esc_html(__('Lokal tid', 'reginor-lite')) . '</th><th>' . esc_html(__('Sal', 'reginor-lite')) . '</th><th>' . esc_html(__('Instruktører', 'reginor-lite')) . '</th><th>' . esc_html(__('Status og forklaring', 'reginor-lite')) . '</th></tr></thead><tbody>');
        $ordered = $sessions; usort($ordered, static fn ($a, $b) => strcmp($a['starts_at'], $b['starts_at']));
        foreach ($ordered as $s) {
            $room = $s['room_id'] ? $this->repo->resource($s['room_id'], 'room')['title'] : __('Mangler', 'reginor-lite');
            echo '<tr><td>' . esc_html($s['date']) . '</td><td>' . esc_html($s['start_time'] . '–' . $s['end_time'] . ' ' . $s['timezone']) . '</td><td>' . esc_html($room) . '</td><td>' . esc_html(implode(', ', array_map('get_the_title', $s['instructor_ids']))) . '</td><td>' . esc_html((self::options()[$s['status']] ?? __('Planlagt', 'reginor-lite')) . ' ' . $s['reason']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        $active = array_filter($ordered, static fn ($s) => $s['status'] !== 'cancelled');
        if ($active) { echo ('<p>' . esc_html(__('Siste undervisningsdag: ', 'reginor-lite')) . '<strong>') . esc_html(max(array_column($active, 'date'))) . '</strong>. ' . esc_html(sprintf(/* translators: %d: number of teaching evenings. */ _n('%d undervisningskveld.', '%d undervisningskvelder.', count($active), 'reginor-lite'), count($active))) . '</p>'; }
    }
    private function preview(array $p): void
    {
        if (!empty($p['mapping_change'])) {
            echo '<h2>' . esc_html(__('Kontroller LetsReg-koblingen før lagring', 'reginor-lite')) . '</h2><h3>' . esc_html($p['data']['title']) . '</h3><h4>' . esc_html(__('Nåværende kobling', 'reginor-lite')) . '</h4>';
            LetsRegCoursePanel::summary($this->repo->get($p['id'], 'group')['data']['letsreg_mapping'] ?? null);
            echo '<h4>' . esc_html(__('Etter lagring', 'reginor-lite')) . '</h4>'; LetsRegCoursePanel::summary($p['data']['letsreg_mapping']);
            $this->messages($p['issues']);
            if (!$p['issues']) { $this->start('confirm'); $this->hidden('proposal', $this->encoded($p)); $this->end(__('Lagre LetsReg-koblingen', 'reginor-lite')); }
            echo '<p><a href="' . esc_url($this->link($p['data']['period_id'], $p['id'])) . '">' . esc_html(__('Tilbake uten å lagre', 'reginor-lite')) . '</a></p>'; return;
        }
        echo ('<h2>' . esc_html(__('Forhåndsvisning – ikke lagret', 'reginor-lite')) . '</h2>'); $this->groupSummary($p['data']); $this->messages(array_merge($p['issues'], $p['warnings']));
        $this->sessions($p['data']['sessions']);
        echo ('<h3>' . esc_html(__('Endringer', 'reginor-lite')) . '</h3><div class="rnl-scroll" role="region" aria-label="' . esc_attr(__('Kursoversikt', 'reginor-lite')) . '" tabindex="0"><table class="widefat"><thead><tr><th>' . esc_html(__('Før', 'reginor-lite')) . '</th><th>' . esc_html(__('Etter', 'reginor-lite')) . '</th></tr></thead><tbody>');
        foreach ($p['changes'] as $change) { echo '<tr>'; foreach (['before', 'after'] as $side) { $s = $change[$side]; echo '<td>' . esc_html($s ? $s['date'] . ' ' . $s['start_time'] . '–' . $s['end_time'] . ' / ' . (self::options()[$s['status']] ?? __('Planlagt', 'reginor-lite')) . ' / ' . $s['reason'] : ($side === 'before' ? __('Ny økt', 'reginor-lite') : __('Fjernes', 'reginor-lite'))) . '</td>'; } echo '</tr>'; }
        echo '</tbody></table></div>';
        if ($p['conflicts']) { echo ('<h3>' . esc_html(__('Ressurskonflikter', 'reginor-lite')) . '</h3><p>' . esc_html(__('Kan lagres i kladd, men må avklares før publisering.', 'reginor-lite')) . '</p>'); $this->messages(array_map(fn ($c) => $this->conflictLabel($c['first'], $p) . __(' og ', 'reginor-lite') . $this->conflictLabel($c['second'], $p) . ($c['room'] ? __(' bruker samme sal samtidig.', 'reginor-lite') : __(' har samme instruktør samtidig.', 'reginor-lite')), $p['conflicts'])); }
        if (!$p['issues']) { $this->start('confirm'); $this->hidden('proposal', $this->encoded($p)); $this->end(__('Lagre disse endringene', 'reginor-lite')); }
        echo '<p><a href="' . $this->link($p['data']['period_id'], $p['id']) . ('">' . esc_html(__('Tilbake uten å lagre', 'reginor-lite')) . '</a></p>');
    }
    private function conflictLabel(string $reference, array $proposal): string
    {
        [$groupId, $sessionId] = explode('/', $reference, 2);
        $data = (int) $groupId === $proposal['id'] ? $proposal['data'] : $this->repo->get((int) $groupId, 'group')['data'];
        foreach ($data['sessions'] as $session) {
            if ($session['id'] === $sessionId) { return '«' . $data['title'] . '» ' . $session['date'] . __(' kl. ', 'reginor-lite') . $session['start_time']; }
        }
        return '«' . $data['title'] . '»';
    }

    private function groupSummary(array $data, ?array $newCourse = null): void
    {
        $course = $newCourse ?? $this->repo->resource($data['course_id'], 'course');
        $level = !empty($data['level_id']) ? $this->repo->resource($data['level_id'], 'level')['title'] : __('Ikke valgt', 'reginor-lite');
        echo '<p><strong>' . esc_html(__('Kursnivå:', 'reginor-lite')) . '</strong> ' . esc_html($level) . '</p>';
        echo ('<dl><dt>' . esc_html(__('Kurs', 'reginor-lite')) . '</dt><dd>') . esc_html($data['title']) . ('</dd><dt>' . esc_html(__('Nivå', 'reginor-lite')) . '</dt><dd>') . \RegiNor\Lite\Infrastructure\RichText::html($course['level_description']) . ('</dd><dt>' . esc_html(__('Pris', 'reginor-lite')) . '</dt><dd>')
            . esc_html((!empty($data['price_from']) ? __('Fra ', 'reginor-lite') : '') . number_format($data['price_minor'] / 100, 2, ',', ' ') . __(' kr ', 'reginor-lite') . (self::options()[$data['price_basis']] ?? '') . '. ' . \RegiNor\Lite\Infrastructure\RichText::plain($data['price_terms']))
            . ('</dd><dt>' . esc_html(__('Påmelding', 'reginor-lite')) . '</dt><dd>') . esc_html((self::options()[$data['registration_status']] ?? '') . ' · ' . (self::options()[$data['registration_scope']] ?? '') . ' · ' . $data['registration_url']) . '</dd></dl>';
        if (!empty($data['registration_from']) || !empty($data['registration_until'])) {
            echo '<p><strong>' . esc_html(__('Kursets påmeldingsdatoer:', 'reginor-lite')) . '</strong> ' . esc_html($this->local($data['registration_from'] ?? null, $data['timezone']) . ' – ' . $this->local($data['registration_until'] ?? null, $data['timezone'])) . '. ' . esc_html(__('Ved automatisk status gjelder LetsRegs salgsvindu. Ved manuell status gjelder periodens påmeldingsvindu.', 'reginor-lite')) . '</p>';
        }
        echo '<p>' . esc_html(__('Fremhevet kurs:', 'reginor-lite') . ' ' . (!empty($data['featured']) ? __('Ja', 'reginor-lite') : __('Nei', 'reginor-lite'))) . '</p>';
        echo '<p>' . esc_html(!empty($data['dropin_enabled']) ? sprintf(/* translators: %s: drop-in price in NOK. */ __('Drop-in: %s kr per person og kurskveld.', 'reginor-lite'), number_format((int) $data['dropin_price_minor'] / 100, 2, ',', ' ')) : __('Drop-in er ikke aktivert.', 'reginor-lite')) . '</p>';
        if (!empty($data['letsreg_mapping'])) { echo '<h4>' . esc_html(__('LetsReg-kobling', 'reginor-lite')) . '</h4>'; LetsRegCoursePanel::summary($data['letsreg_mapping']); }
    }

    private function publication(array $p): void
    {
        echo '<h2>' . esc_html(__('Publiseringskontroll', 'reginor-lite')) . '</h2>';
        if ($p['errors']) {
            echo '<h3>' . esc_html(__('Dette må rettes før publisering', 'reginor-lite')) . '</h3><ul>';
            foreach ($p['errors'] as $message) {
                $links = array_filter($p['field_errors'] ?? [], static fn (array $error): bool => $error['message'] === $message);
                if (!$links) { echo '<li>' . esc_html($message) . '</li>'; continue; }
                foreach ($links as $error) {
                    $anchor = $error['field'] === 'registration_status' ? 'rnl-course-registration' : 'rnl-course-description';
                    echo '<li><a href="' . $this->link($p['id'], $error['group_id']) . '#' . $anchor . '">' . esc_html($message) . '</a></li>';
                }
            }
            echo '</ul>';
        }
        if ($p['warnings']) { echo '<details><summary>' . esc_html(__('Til informasjon – hindrer ikke publisering', 'reginor-lite')) . '</summary>'; $this->messages($p['warnings']); echo '</details>'; }
        foreach ($this->repo->groups($p['id']) as $g) { echo '<h3>' . esc_html($g['data']['title']) . '</h3>'; $this->groupSummary($g['data']); $this->sessions($g['data']['sessions']); }
        if (!$p['errors']) { echo ('<p>' . esc_html(__('Kontrollene er bestått. Periode og kurs publiseres samlet. Kursene vises på kurssiden innenfor det valgte synlighetsvinduet.', 'reginor-lite')) . '</p>'); $this->start('publish'); $this->hidden('proposal', $this->encoded($p)); $this->end(__('Bekreft publisering av hele perioden', 'reginor-lite')); }
        echo '<p><a href="' . $this->link($p['id']) . ('">' . esc_html(__('Tilbake til perioden', 'reginor-lite')) . '</a></p>');
    }
    private function messages(array $messages): void { if ($messages) { echo '<ul>'; foreach ($messages as $message) { echo '<li>' . esc_html($message) . '</li>'; } echo '</ul>'; } }
    private function history(array $state): void
    {
        echo ('<details><summary>' . esc_html(__('Endringshistorikk', 'reginor-lite')) . '</summary><div class="rnl-scroll" role="region" aria-label="' . esc_attr(__('Kursoversikt', 'reginor-lite')) . '" tabindex="0"><table class="widefat"><thead><tr><th>' . esc_html(__('Versjon', 'reginor-lite')) . '</th><th>' . esc_html(__('Tid (UTC)', 'reginor-lite')) . '</th><th>' . esc_html(__('Endret av', 'reginor-lite')) . '</th><th>' . esc_html(__('Handling', 'reginor-lite')) . '</th></tr></thead><tbody>');
        foreach (array_reverse(array_merge($state['history'], [$state])) as $s) { echo '<tr><td>' . (int) $s['version'] . '</td><td>' . esc_html($s['changed_at']) . '</td><td>' . esc_html(get_userdata($s['actor_id'])?->display_name ?? __('Tidligere bruker', 'reginor-lite')) . '</td><td>' . esc_html(['saved' => __('Lagret', 'reginor-lite'), 'published' => __('Publisert', 'reginor-lite'), 'unpublished' => __('Avpublisert', 'reginor-lite'), 'trashed' => __('Til papirkurv', 'reginor-lite'), 'restored' => __('Gjenopprettet', 'reginor-lite'), 'group_trashed' => __('Kurs til papirkurv', 'reginor-lite'), 'group_restored' => __('Kurs gjenopprettet', 'reginor-lite')][$s['event'] ?? 'saved']) . '</td></tr>'; }
        echo '</tbody></table></div>';
        foreach (array_reverse($state['history']) as $snapshot) {
            echo ('<details><summary>' . esc_html(__('Vis oppsett i versjon ', 'reginor-lite'))) . (int) $snapshot['version'] . '</summary>';
            if (isset($snapshot['data']['sessions'])) { $this->groupSummary($snapshot['data']); $this->sessions($snapshot['data']['sessions']); }
            else {
                foreach ($snapshot['data'] as $key => $value) {
                    if (!is_scalar($value) && $value !== null) { continue; }
                    if (str_ends_with($key, 'price_minor')) { $display = number_format((int) $value / 100, 2, ',', ' '); }
                    elseif (in_array($key, ['visible_from', 'visible_until', 'sales_from', 'sales_until'], true)) { $display = $this->local($value, $snapshot['data']['timezone']); }
                    elseif ($key === 'default_room_id') { $display = $value ? $this->repo->resource($value, 'room')['title'] : __('Ikke satt', 'reginor-lite'); }
                    else { $display = is_bool($value) ? ($value ? __('Ja', 'reginor-lite') : __('Nei', 'reginor-lite')) : (self::options()[$value ?? ''] ?? (string) $value); }
                    echo '<p><strong>' . esc_html(self::labels()[$key] ?? $key) . ':</strong> ' . esc_html($display) . '</p>';
                }
            }
            foreach ($snapshot['data']['breaks'] ?? [] as $break) { echo '<p>' . esc_html(__('Opphold: ', 'reginor-lite')) . esc_html($break['from'] . '–' . $break['until'] . ' · ' . $break['reason']) . '</p>'; }
            echo '</details>';
        }
        echo '</details>';
    }
    private function resources(): void
    {
        if (!current_user_can('manage_options')) { throw new \RuntimeException(__('Denne siden krever administratorrettigheter.', 'reginor-lite'), 403); }
        echo ('<p>' . esc_html(__('Her samler du nivåer, kursbeskrivelser, steder og saler. Kursansvarlige kan velge disse oppføringene. Bare administrator kan opprette og endre dem.', 'reginor-lite')) . '</p>');
        foreach (['level' => __('Kursnivåer', 'reginor-lite'), 'venue' => __('Kurssteder', 'reginor-lite'), 'room' => __('Saler', 'reginor-lite'), 'course' => __('Kursbeskrivelser', 'reginor-lite')] as $kind => $label) {
            echo '<h2>' . esc_html($label) . '</h2>';
            if ($kind === 'level') { echo '<p class="rnl-help">' . esc_html(__('Lag nivåene deltakerne skal kunne velge mellom, for eksempel Nybegynner, Øvet 1 og Videregående. Velg deretter et nivå under Undervisning på hvert kurs. Forklaringen er valgfri.', 'reginor-lite')) . '</p>'; }
            $rows = $this->repo->listing($kind);
            if ($kind === 'level') { uasort($rows, static fn ($a, $b) => [$a['data']['sort_order'], $a['data']['title']] <=> [$b['data']['sort_order'], $b['data']['title']]); }
            foreach ($rows as $id => $row) {
                $state = $this->repo->get($id, $kind);
                echo '<details><summary>' . esc_html($state['data']['title']) . '</summary>'; $this->start('save', $id, $state['version']); $this->hidden('kind', $kind); $this->fields($kind, $state['data']); $this->end(__('Lagre oppføring', 'reginor-lite')); echo '</details>';
            }
            echo ('<details><summary>' . esc_html(__('Opprett ny oppføring', 'reginor-lite')) . '</summary>'); $this->start('save'); $this->hidden('kind', $kind); $this->fields($kind, $kind === 'level' ? ['sort_order' => 10, 'active' => true] : []); $this->end(__('Opprett oppføring', 'reginor-lite')); echo '</details>';
        }
        echo ('<h2>' . esc_html(__('Eksisterende instruktørprofiler', 'reginor-lite')) . '</h2><p>' . esc_html(__('Velg profiltypen etter kartlegging. Ingen profiler importeres eller dupliseres.', 'reginor-lite')) . '</p>');
        $this->start('instructor_types');
        foreach (get_post_types(['public' => true], 'objects') as $type) {
            if (!str_starts_with($type->name, 'rnl_')) { echo '<label><input type="checkbox" name="types[]" value="' . esc_attr($type->name) . '"' . checked(in_array($type->name, get_option('rnl_instructor_types', []), true), true, false) . '> ' . esc_html($type->label . ' (' . $type->name . ')') . '</label><br>'; }
        }
        $this->end(__('Lagre tillatte profiltyper', 'reginor-lite'));
    }
}
