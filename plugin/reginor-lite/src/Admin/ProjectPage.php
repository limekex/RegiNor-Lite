<?php

declare(strict_types=1);

namespace RegiNor\Lite\Admin;

/** Read-only project status within the shared administration shell. */
final class ProjectPage
{
    public static function register(): void
    {
        add_submenu_page(
            'reginor-lite',
            __('RegiNor Lite', 'reginor-lite'),
            __('RegiNor Lite', 'reginor-lite'),
            'manage_options',
            'reginor-lite-status',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Du har ikke tilgang til denne siden.', 'reginor-lite'),
                '',
                ['response' => 403]
            );
        }

        echo '<div class="wrap rnl-ui rnl-admin">';
        echo '<h1>' . esc_html__('RegiNor Lite', 'reginor-lite') . '</h1>';
        echo '<p>' . esc_html__('Kursadministrasjon, offentlig kursvisning og kapasitetsdemonstrasjon er installert. Løsningen er fortsatt under utvikling; ekte LetsReg-kapasitet er ikke aktivert.', 'reginor-lite') . '</p>';
        echo '<h2>' . esc_html__('Neste steg: staging og brukertest', 'reginor-lite') . '</h2>';
        echo '<p>' . esc_html__('RegiNor tas i bruk fra neste nye kursperiode. Kursarbeidsflaten gir opprettelse av perioder og kurs, forhåndsvisning og kontrollert publisering. Offentlig kursvisning kan bygges inn med blokk eller shortcode. Faktisk Avada/TEC-installasjon og brukervennlighet må prøves før lansering.', 'reginor-lite') . '</p>';
        echo '<p>' . esc_html__('Roadmap og kartleggingsmal ligger i prosjektets docs-mappe.', 'reginor-lite') . '</p>';
        echo '</div>';
    }
}
