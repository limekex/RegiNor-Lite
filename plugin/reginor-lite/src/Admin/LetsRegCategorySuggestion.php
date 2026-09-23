<?php

declare(strict_types=1);

namespace RegiNor\Lite\Admin;

/** Editable form defaults only. Names are not an authoritative provider contract. */
final class LetsRegCategorySuggestion
{
    /** @return array{role: string, registration: string}|null */
    public static function fromName(string $name): ?array
    {
        $word = static fn (string $pattern): bool => preg_match('/(?<![\p{L}\p{N}])(?:' . $pattern . ')(?![\p{L}\p{N}])/iu', $name) === 1;
        $leader = $word('førere?');
        $follower = $word('følgere?');
        // Both roles, no role, or explicit mixed registration forms need a manual decision.
        if ($leader === $follower) { return null; }
        $pair = $word('par(?:[\s\p{Pd}]*påmelding)?');
        $single = $word('enkelt[\s\p{Pd}]*påmelding');
        if ($pair && $single) { return null; }
        return ['role' => $leader ? 'leader' : 'follower', 'registration' => $pair ? 'pair' : 'single'];
    }
}
