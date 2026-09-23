<?php

declare(strict_types=1);
namespace RegiNor\Lite\Frontend;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

/** Shared, translated category labels; no category or account identifiers in public output. */
final class CategoryCapacityPresenter
{
    public static function role(array $row): string
    {
        return match ($row['role']) { 'pair' => __('Parpåmelding', 'reginor-lite'), 'leader' => __('Fører', 'reginor-lite'), 'follower' => __('Følger', 'reginor-lite'), default => __('Uten rollefordeling', 'reginor-lite') };
    }
    public static function registration(array $row): string
    {
        if ($row['role'] === 'pair') { return __('To deltakere per par', 'reginor-lite'); }
        return $row['registration'] === 'pair' ? __('Parpåmelding · per deltaker', 'reginor-lite') : __('Enkeltpåmelding', 'reginor-lite');
    }
    public static function amount(array $row): string
    {
        if ($row['status'] === 'available' && $row['available'] !== null) {
            if ($row['role'] === 'pair') { return sprintf(/* translators: %s: maximum number of complete pairs. */ __('Opptil %s par', 'reginor-lite'), number_format_i18n($row['available'])); }
            return sprintf(/* translators: %s: maximum number of participant places. */ _n('Opptil %s plass', 'Opptil %s plasser', $row['available'], 'reginor-lite'), number_format_i18n($row['available']));
        }
        return match ($row['status']) {
            'unlimited' => '∞',
            'full' => __('Fullt', 'reginor-lite'), 'later' => __('Åpner senere', 'reginor-lite'),
            'closed' => __('Stengt', 'reginor-lite'), 'cancelled' => __('Avlyst', 'reginor-lite'),
            default => __('Antall ikke oppgitt', 'reginor-lite'),
        };
    }
    public static function render(array $capacity, bool $compact, int $now): void
    {
        if (empty($capacity['categories']) || empty($capacity['checked_at'])) { return; }
        echo $compact ? '<details class="rnl-category-capacity"><summary>' : '<section class="rnl-category-capacity"><h4>';
        echo esc_html(__('Ledige plasser per kategori', 'reginor-lite'));
        echo $compact ? '</summary>' : '</h4>';
        echo '<ul class="rnl-capacity-categories">';
        foreach (\RegiNor\Lite\Domain\Capacity\LetsRegCategoryCapacity::displayRows($capacity['categories']) as $row) {
            echo '<li><span><strong>' . esc_html(self::role($row)) . '</strong><span class="rnl-small">' . esc_html(self::registration($row)) . '</span>';
            // Full names disambiguate multiple mapped categories with the same role/form.
            if ($row['name'] !== '') { echo '<span class="rnl-capacity-category-name">' . esc_html($row['name']) . '</span>'; }
            echo '</span><strong' . ($row['status'] === 'unlimited' ? ' aria-label="' . esc_attr(__('Ubegrenset kapasitet', 'reginor-lite')) . '"' : '') . '>' . esc_html(self::amount($row)) . '</strong></li>';
        }
        echo '</ul><p class="rnl-small">' . esc_html(__('Tall fra LetsReg: ', 'reginor-lite') . wp_date('d.m.Y H:i', $capacity['checked_at'])) . '. '
            . esc_html(__('Siste kjente kapasitet. Kategorier kan dele plasser. Faktisk tilgjengelighet bekreftes hos LetsReg.', 'reginor-lite')) . '</p>';
        echo $compact ? '</details>' : '</section>';
    }
}
