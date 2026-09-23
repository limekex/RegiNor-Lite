<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use RegiNor\Lite\Infrastructure\CourseCalendar;
use RegiNor\Lite\Infrastructure\WebIdentity;
use function RegiNor\Lite\translate as __;

/** Secondary course actions; no external scripts, pixels or implicit consent. */
final class CourseTools
{
    private static function icon(string $kind): string
    {
        $path = match ($kind) {
            'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18M8 15h2m4 0h2"/>',
            'copy' => '<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V3H3v13h5"/>',
            'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
            default => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m9 10 6-3m-6 7 6 3"/>',
        };
        return '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }

    private static function copyField(string $value, string $label, string $button): void
    {
        $id = wp_unique_id('rnl-copy-');
        echo '<div class="rnl-copy-field"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label><div class="rnl-copy-row"><input id="' . esc_attr($id) . '" type="text" readonly value="' . esc_attr($value) . '"><button type="button" class="rnl-button rnl-button-secondary" data-rnl-copy hidden>' . self::icon('copy') . esc_html($button) . '</button></div></div>';
    }

    public static function calendar(array $group): void
    {
        try { $calendar = CourseCalendar::read((int) $group['id'], WebIdentity::language()); }
        catch (\Throwable) { $calendar = null; }
        if (!$calendar) { return; }
        $feed = CourseCalendar::url($calendar['identity'], WebIdentity::language());
        echo '<details class="rnl-course-tools" data-rnl-tools><summary>' . self::icon('calendar') . esc_html(__('Legg til i kalender', 'reginor-lite')) . '</summary><div class="rnl-tools-body">';
        echo '<p>' . esc_html(__('Å legge kurset i kalenderen melder deg ikke på. Påmelding skjer hos LetsReg.', 'reginor-lite')) . '</p>';
        if ($group['status'] === 'cancelled') { echo '<p class="rnl-notice">' . esc_html(__('Kurset er avlyst. Kalenderen viser kurskveldene som avlyst.', 'reginor-lite')) . '</p>'; }
        echo '<section class="rnl-tools-choice"><h4>' . esc_html(__('Abonner på kursdatoene', 'reginor-lite')) . '</h4><p>' . esc_html(__('Kalenderen din kan hente endringer i tider og steder automatisk så lenge kurset er offentlig. Oppdateringer kan ta tid.', 'reginor-lite')) . '</p>';
        self::copyField($feed, __('Abonnementslenke', 'reginor-lite'), __('Kopier abonnementslenke', 'reginor-lite'));
        echo '<details class="rnl-calendar-help"><summary>' . esc_html(__('Slik legger du til et abonnement', 'reginor-lite')) . '</summary>';
        $guides = [__('Apple Kalender', 'reginor-lite') => __('På Mac: velg Arkiv → Nytt kalenderabonnement og lim inn lenken. Velg iCloud hvis du vil se abonnementet på flere enheter. På iPhone/iPad: legg til en abonnementskalender i kalenderinnstillingene.', 'reginor-lite'),
            __('Google Kalender', 'reginor-lite') => __('Åpne Google Kalender i nettleseren på en datamaskin. Ved Andre kalendere velger du + → Fra nettadresse. Lim inn abonnementslenken. Du kan deretter vise kalenderen i mobilappen.', 'reginor-lite'),
            __('Outlook', 'reginor-lite') => __('Åpne kalenderen i Outlook på nett. Velg Legg til kalender → Abonner fra nettet, og lim inn lenken.', 'reginor-lite'),
            __('Annen kalender', 'reginor-lite') => __('Se etter Abonner på kalender eller Legg til fra nettadresse i kalenderappen. Lim inn abonnementslenken. Hvis appen bare kan importere filer, bruk nedlasting nedenfor.', 'reginor-lite')];
        foreach ($guides as $name => $help) { echo '<details class="rnl-calendar-guide" name="rnl-calendar-client-' . (int) $group['id'] . '"><summary>' . esc_html($name) . '</summary><p>' . esc_html($help) . '</p></details>'; }
        echo '</details></section><section class="rnl-tools-choice"><h4>' . esc_html(__('Last ned kursdatoene', 'reginor-lite')) . '</h4><p>' . esc_html(__('Legg alle kurskveldene til én gang. Denne kopien oppdateres ikke automatisk. Gjentatt import kan lage doble oppføringer.', 'reginor-lite')) . '</p><a class="rnl-button rnl-button-secondary" href="' . esc_url(add_query_arg('rnl_calendar_download', '1', $feed)) . '">' . self::icon('download') . esc_html(__('Last ned kalenderfil (.ics)', 'reginor-lite')) . '</a><p class="rnl-help">' . esc_html(__('Åpne filen i kalenderappen og velg kalenderen du vil legge datoene i. I Google Kalender på datamaskin bruker du Innstillinger → Importér og eksportér.', 'reginor-lite')) . '</p></section><p role="status" aria-live="polite" data-rnl-tools-status></p></div></details>';
    }

    public static function share(array $group): void
    {
        $url = PublicSite::url((int) $group['id']);
        if (!$url) { return; }
        echo '<section class="rnl-panel rnl-course-tools" data-rnl-tools data-rnl-share-url="' . esc_url($url) . '" data-rnl-share-title="' . esc_attr($group['title']) . '"><h3>' . esc_html(__('Del kurset', 'reginor-lite')) . '</h3><div class="rnl-tools-actions"><button type="button" class="rnl-button rnl-button-secondary" data-rnl-native-share hidden>' . self::icon('share') . esc_html(__('Del …', 'reginor-lite')) . '</button>';
        $links = ['Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url), 'WhatsApp' => 'https://wa.me/?text=' . rawurlencode($group['title'] . ' ' . $url)];
        foreach ($links as $name => $destination) {
            echo '<a class="rnl-button rnl-button-secondary" href="' . esc_url($destination) . '" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="' . esc_attr(sprintf(/* translators: %s: social platform. */ __('Del på %s (åpnes i ny fane)', 'reginor-lite'), $name)) . '">' . self::icon('share') . esc_html($name) . '</a>';
        }
        echo '</div>';
        self::copyField($url, __('Lenke til dette kurset', 'reginor-lite'), __('Kopier lenke', 'reginor-lite'));
        echo '<p role="status" aria-live="polite" data-rnl-tools-status></p></section>';
    }
}
