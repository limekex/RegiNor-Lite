<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain;

use InvalidArgumentException;
use function RegiNor\Lite\translate as __;

final class RegistrationUrl
{
    /** Encode UTF-8 in the path/query/fragment without changing delimiters or existing escapes. */
    public static function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') { return ''; }
        $invalid = static fn (): InvalidArgumentException => new InvalidArgumentException(__('Påmeldingslenken må være en gyldig HTTPS-adresse uten innloggingsopplysninger. Eksempel: https://www.letsreg.com/event/ditt-kurs.', 'reginor-lite'));
        if (preg_match('//u', $url) !== 1 || preg_match('/[\x00-\x20\x7F]/', $url) || str_contains($url, '\\')
            || !preg_match('~\Ahttps://([^/?#]+)(.*)\z~i', $url, $parts)
            || preg_match('/[^\x21-\x7E]/', $parts[1])) {
            throw $invalid();
        }
        // Do not encode the authority: this must still pass strict host/credential validation.
        $tail = preg_replace_callback('/[^\x00-\x7F]+/u', static fn (array $match): string => rawurlencode($match[0]), $parts[2]);
        $normalized = 'https://' . $parts[1] . $tail;
        if (filter_var($normalized, FILTER_VALIDATE_URL) === false
            || parse_url($normalized, PHP_URL_USER) !== null || parse_url($normalized, PHP_URL_PASS) !== null) {
            throw $invalid();
        }
        return $normalized;
    }
}
