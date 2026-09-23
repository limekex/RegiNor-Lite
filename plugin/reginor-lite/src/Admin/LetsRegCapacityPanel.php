<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\LetsRegAvailabilityStore;
use RegiNor\Lite\Frontend\CategoryCapacityPresenter as Labels;
use function RegiNor\Lite\translate as __;

final class LetsRegCapacityPanel
{
    public static function render(array $course, bool $compact = false): void
    {
        if (empty($course['letsreg_mapping'])) { return; }
        $view = LetsRegAvailabilityStore::view($course, new \DateTimeImmutable());
        $tag = $compact ? 'details' : 'section'; $heading = $compact ? 'summary' : 'h2';
        echo '<' . $tag . ' class="rnl-panel rnl-letsreg-capacity"><' . $heading . '>' . esc_html(__('Kapasitet fra LetsReg', 'reginor-lite')) . '</' . $heading . '>';
        if (!$view['checked_at']) {
            echo '<p role="status">' . esc_html(LetsRegAvailabilityStore::pendingExplanation($course)) . '</p></' . $tag . '>'; return;
        }
        echo '<div><p class="rnl-help">'
            . esc_html(__('0 i plassgrensen betyr ubegrenset og vises som ∞. En positiv grense er full når antall påmeldte når grensen. 0 påmeldte betyr ikke fullt. Parkategoriene vises samlet som hele par. Salgsvindu og partnerkategori kan begrense påmeldingen. Tallene skal ikke summeres.', 'reginor-lite')) . '</p><div class="rnl-scroll"><table class="widefat striped"><thead><tr>';
        foreach ([__('Kategori', 'reginor-lite'), __('Rolle og påmeldingsform', 'reginor-lite'), __('Påmeldte hos LetsReg', 'reginor-lite'), __('Rapportert ledig hos LetsReg', 'reginor-lite'), __('Kapasitetsvurdering', 'reginor-lite')] as $label) { echo '<th scope="col">' . esc_html($label) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach (\RegiNor\Lite\Domain\Capacity\LetsRegCategoryCapacity::displayRows($view['categories']) as $row) {
            echo '<tr><th scope="row">' . esc_html($row['role'] === 'pair' ? __('Parpåmelding', 'reginor-lite') : ($row['name'] ?: __('Uten kategorinavn', 'reginor-lite'))) . '</th><td>' . esc_html(Labels::role($row) . ' · ' . Labels::registration($row));
            $suggestion = LetsRegCategorySuggestion::fromName($row['name']);
            if ($suggestion && $suggestion['registration'] !== $row['registration']) {
                echo '<p class="rnl-help">' . esc_html(__('Kontroller påmeldingsformen i kursoppsettets LetsReg-kobling: kategorinavnet tyder på en annen påmeldingsform enn den som er valgt.', 'reginor-lite')) . '</p>';
            }
            echo '</td><td>'
                . esc_html($row['role'] === 'pair' ? '—' : ($row['reported_registered'] === null ? __('Ukjent', 'reginor-lite') : number_format_i18n($row['reported_registered']))) . '</td><td>'
                . esc_html($row['role'] === 'pair' ? '—' : ($row['reported_available'] === null ? __('Ukjent', 'reginor-lite') : ($row['reported_available'] === 0 ? '∞' : number_format_i18n($row['reported_available'])))) . '</td><td' . ($row['status'] === 'unlimited' ? ' aria-label="' . esc_attr(__('Ubegrenset kapasitet', 'reginor-lite')) . '"' : '') . '>' . esc_html(Labels::amount($row)) . '</td></tr>';
        }
        echo '</tbody></table></div><p class="rnl-small">' . esc_html(__('Sist hentet: ', 'reginor-lite') . wp_date('d.m.Y H:i:s', $view['checked_at'])) . '. ' . esc_html(__('Siste kjente kapasitet. Faktisk tilgjengelighet bekreftes hos LetsReg.', 'reginor-lite')) . '</p></div>';
        if ($course['registration_status'] !== 'automatic') { echo '<p class="rnl-notice">' . esc_html(__('Manuell påmeldingsstatus er valgt. Kapasitetstallene vises ikke offentlig. LetsReg-kontrollen endrer ikke ditt manuelle statusvalg.', 'reginor-lite')) . '</p>'; }
        echo '<p class="rnl-help">' . esc_html(__('Parpåmelding viser hele par og begrenses av både fører, følger og samlet kapasitet. Råtall fra parkategoriene summeres ikke.', 'reginor-lite')) . '</p></' . $tag . '>';
    }
}
