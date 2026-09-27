<?php

declare(strict_types=1);
namespace RegiNor\Lite\Admin;

use RegiNor\Lite\Infrastructure\RichText;
use function RegiNor\Lite\translate as __;

/** Read-only comparison; the original rich previews and saved content stay intact. */
final class TextChanges
{
    private static function text(string $value, bool $rich): string
    {
        if (!$rich) { return $value; }
        // Include link destinations so an unchanged label cannot hide a changed URL.
        $value = preg_replace_callback('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is',
            static fn ($match) => $match[2] . ' (' . $match[1] . ')', RichText::clean($value));
        return RichText::plain($value);
    }

    public static function render(string $before, string $after, bool $rich = false): string
    {
        if ($before === $after) { return ''; }
        $old = self::text($before, $rich); $new = self::text($after, $rich);
        if ($old === $new) {
            return '<p class="rnl-help">' . esc_html(__('Teksten er lik, men formatering eller HTML er endret. Se før- og ettervisningen.', 'reginor-lite')) . '</p>';
        }
        require_once ABSPATH . WPINC . '/wp-diff.php';
        // Bound detailed work for unusually large provider descriptions.
        if (strlen($old) + strlen($new) > 40000) {
            $diff = '<del>' . esc_html($old) . '</del> <ins>' . esc_html($new) . '</ins>';
        } else {
            $renderer = new \Text_Diff_Renderer_inline();
            $diff = $renderer->render(new \Text_Diff('native', [explode("\n", $old), explode("\n", $new)]));
        }
        return '<div class="rnl-text-changes"><p class="rnl-help">' . esc_html(__('Uthevede endringer: fjernet tekst er rød og gjennomstreket; ny tekst er grønn og understreket.', 'reginor-lite')) . '</p><div class="rnl-text-changes-content">'
            . wp_kses($diff, ['del' => [], 'ins' => []]) . '</div></div>';
    }
}
