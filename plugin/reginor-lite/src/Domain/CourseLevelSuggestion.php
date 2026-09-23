<?php

declare(strict_types=1);
namespace RegiNor\Lite\Domain;

/** Match configured level names only; never create levels or infer from prices. */
final class CourseLevelSuggestion
{
    /** @param array<int, array{title: string, active: bool}> $levels */
    public static function fromName(string $name, array $levels): ?int
    {
        $name = self::normalize($name);
        $matches = [];
        foreach ($levels as $id => $level) {
            if (!$level['active']) { continue; }
            $title = self::normalize($level['title']);
            // A numbered level must match its number, not a prefix (Øvet 1 / Øvet 10).
            if ($title !== '' && preg_match('/(?:^| )' . preg_quote($title, '/') . '(?! \d)(?= |$)/u', $name)) {
                $matches[$id] = $title;
            }
        }
        // Prefer the specific configured phrase, e.g. Salsa Nybegynner over Nybegynner.
        // Separate matches and duplicate normalized names remain ambiguous.
        $specific = $matches;
        foreach ($matches as $id => $title) {
            foreach ($matches as $other) {
                if ($other !== $title && str_contains(' ' . $other . ' ', ' ' . $title . ' ')) {
                    unset($specific[$id]); break;
                }
            }
        }
        return count($specific) === 1 ? (int) array_key_first($specific) : null;
    }

    private static function normalize(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[\p{L}]\K(?=\d)|\d\K(?=[\p{L}])/u', ' ', $name);
        $name = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name));
        // Øvet1, Øvet 1 and Øvet nivå 1 refer to the same explicitly numbered level.
        return trim(preg_replace('/(?:^| )nivå (?=\d)/u', ' ', $name));
    }
}
