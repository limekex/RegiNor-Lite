<?php

declare(strict_types=1);
namespace RegiNor\Lite;

/** Domain classes also run without WordPress in CLI unit tests. */
function translate(string $text, string $domain = 'reginor-lite'): string
{
    return function_exists('__') ? \__($text, $domain) : $text;
}

function plural(string $single, string $plural, int $number, string $domain = 'reginor-lite'): string
{
    return function_exists('_n') ? \_n($single, $plural, $number, $domain) : ($number === 1 ? $single : $plural);
}
