<?php

declare(strict_types=1);
namespace RegiNor\Lite\Infrastructure;

/** The same small HTML vocabulary is enforced on import, local save and public output. */
final class RichText
{
    public static function clean(string $text): string
    {
        $text = preg_replace('#<(script|style|iframe|object)\b[^>]*>.*?</\1\s*>#is', '', $text);
        // Provider layout and heading sizes must not override the site's design.
        $text = preg_replace('#<(?:div|h[1-6])\b[^>]*>#i', '<p>', $text);
        $text = preg_replace('#</(?:div|h[1-6])\s*>#i', '</p>', $text);
        $links = new \WP_HTML_Tag_Processor($text);
        while ($links->next_tag('A')) {
            $href = $links->get_attribute('href');
            if (is_string($href) && !str_starts_with($href, '#') && !in_array(strtolower((string) wp_parse_url($href, PHP_URL_SCHEME)), ['https', 'http', 'mailto', 'tel'], true)) { $links->remove_attribute('href'); }
        }
        $text = $links->get_updated_html();
        $allowed = array_fill_keys(['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote'], []);
        $allowed['a'] = ['href' => true, 'title' => true];
        $text = wp_kses($text, $allowed, ['https', 'http', 'mailto', 'tel']);
        // No provider-controlled target, CSS, event handlers, embeds or tracking images.
        $text = preg_replace('/<a\b([^>]*)>/i', '<a$1 target="_blank" rel="noopener noreferrer">', $text);
        return trim(force_balance_tags($text));
    }

    public static function html(string $text): string
    {
        return self::clean(wpautop(self::clean($text)));
    }

    public static function plain(string $text): string
    {
        $text = preg_replace('#</(?:p|li|blockquote|ul|ol)>|<br\s*/?>#i', "\n", self::clean($text));
        return trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
