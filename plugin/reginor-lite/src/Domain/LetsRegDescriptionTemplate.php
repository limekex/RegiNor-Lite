<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain;

/** Exact standalone headings form the v1 contract; prose is never guessed into fields. */
final class LetsRegDescriptionTemplate
{
    public const HEADINGS = [
        'Kursbeskrivelse' => 'description',
        'Dansestil' => 'dance_style',
        'Nivå og forkunnskaper' => 'level_description',
        'Partnerinformasjon' => 'partner_info',
        'Prisvilkår og tillegg' => 'price_terms',
        'Praktisk informasjon' => 'ignored',
    ];

    public static function parse(string $text): array
    {
        $original = $text;
        // Split at block boundaries while retaining inline formatting and links in each field.
        $text = preg_replace('#(<(?:p|div|h[1-6]|ul|ol|blockquote)\b[^>]*>)#i', "\n$1", $text);
        $text = preg_replace('#(</(?:p|div|h[1-6]|ul|ol|blockquote)>|<br\s*/?>)#i', "$1\n", $text);
        $lines = explode("\n", str_replace(["\r\n", "\r", "\u{00a0}"], ["\n", "\n", ' '], trim($text)));
        $lookup = [];
        foreach (self::HEADINGS as $heading => $key) { $lookup[mb_strtolower($heading)] = $key; }
        $sections = []; $active = null; $duplicates = []; $prefix = [];
        foreach ($lines as $line) {
            $heading = mb_strtolower(trim(preg_replace('/^#{1,6}\s+/', '', trim(str_replace("\u{00a0}", ' ', html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8')))), " \t*:"));
            if (isset($lookup[$heading])) {
                $active = $lookup[$heading];
                if (isset($sections[$active])) { $duplicates[] = array_search($active, self::HEADINGS, true); }
                $sections[$active] ??= [];
            } elseif ($active !== null) { $sections[$active][] = $line; }
            elseif ($heading !== '') { $prefix[] = $line; }
        }
        $fallback = ['mode' => 'plain', 'fields' => ['description' => $original], 'missing' => [], 'duplicates' => [], 'ignored' => false];
        // A single heading in an old free-text description does not opt it into the template.
        if (!isset($sections['description'])) { return $fallback; }
        $missing = [];
        foreach (self::HEADINGS as $heading => $key) {
            if ($key !== 'ignored' && !isset($sections[$key])) { $missing[] = $heading; }
        }
        $fields = array_map(static fn ($lines) => trim(implode("\n", $lines)), $sections);
        if (isset($fields['dance_style'])) { $fields['dance_style'] = trim(html_entity_decode(strip_tags($fields['dance_style']), ENT_QUOTES | ENT_HTML5, 'UTF-8')); }
        $invalid = $missing || $duplicates || $prefix || array_key_first($sections) !== 'description'
            || (isset($sections['ignored']) && array_key_last($sections) !== 'ignored')
            || ($fields['description'] ?? '') === '' || ($fields['dance_style'] ?? '') === '' || mb_strlen($fields['dance_style'] ?? '') > 250;
        if ($invalid) { return array_replace($fallback, ['mode' => 'invalid', 'missing' => $missing, 'duplicates' => array_values(array_unique($duplicates))]); }
        $ignored = isset($fields['ignored']); unset($fields['ignored']);
        return ['mode' => 'structured', 'fields' => $fields, 'missing' => [], 'duplicates' => [], 'ignored' => $ignored];
    }

    public static function courseFields(array $parsed): array
    {
        return array_intersect_key($parsed['fields'], array_flip(['description', 'dance_style', 'level_description', 'partner_info']));
    }
}
