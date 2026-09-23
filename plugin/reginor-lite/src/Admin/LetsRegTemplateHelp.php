<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use function RegiNor\Lite\translate as __;

final class LetsRegTemplateHelp
{
    public static function message(array $parsed): string
    {
        if ($parsed['mode'] === 'structured') {
            return __('Beskrivelsesmalen er gjenkjent. Teksten fordeles på kursbeskrivelse, dansestil, nivå/forkunnskaper, partnerinformasjon og prisvilkår. «Praktisk informasjon» tas ikke med. Kontroller feltene før lagring.', 'reginor-lite');
        }
        if ($parsed['mode'] === 'invalid') {
            $message = __('Beskrivelsesmalen er ufullstendig eller feil. Hele teksten er beholdt som kursbeskrivelse; ingen seksjoner er fjernet. Bruk hver av de fem overskriftene én gang på egen linje, start med Kursbeskrivelse og fyll ut kursbeskrivelse og dansestil (maks 250 tegn). Praktisk informasjon må stå til slutt.', 'reginor-lite');
            if ($parsed['missing']) { $message .= ' ' . __('Mangler: ', 'reginor-lite') . implode(', ', $parsed['missing']) . '.'; }
            if ($parsed['duplicates']) { $message .= ' ' . __('Gjentatte overskrifter: ', 'reginor-lite') . implode(', ', $parsed['duplicates']) . '.'; }
            return $message;
        }
        return __('Ingen beskrivelsesmal funnet. Hele teksten foreslås som kursbeskrivelse; øvrige tekstfelt fylles ut manuelt.', 'reginor-lite');
    }

    public static function render(): void
    {
        echo '<details class="rnl-field-section"><summary>' . esc_html(__('Mal for beskrivelse hos LetsReg', 'reginor-lite')) . '</summary><p>' . esc_html(__('Bruk overskriftene nedenfor, hver på egen linje. De kan være fete eller formateres som overskrifter i LetsReg. Behold alle fem; tom partnerinformasjon eller tomme prisvilkår betyr at feltet er tomt. Legg informasjon som bare skal vises hos LetsReg under Praktisk informasjon til slutt.', 'reginor-lite')) . '</p><pre class="rnl-preserve-lines">' . esc_html(implode("\n\n", array_keys(\RegiNor\Lite\Domain\LetsRegDescriptionTemplate::HEADINGS))) . '</pre><p class="rnl-help">' . esc_html(__('Nivå og forkunnskaper fyller forklaringsteksten. Kursnivået i filteret velges fortsatt separat. Tekst om sal, datoer og instruktører endrer ikke timeplanen.', 'reginor-lite')) . '</p></details>';
    }
}
