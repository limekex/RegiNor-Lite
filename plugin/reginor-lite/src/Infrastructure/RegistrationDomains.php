<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

/** The editor, publication check and public links share the same exact host list. */
final class RegistrationDomains
{
    public static function allowed(): array
    {
        return array_values((array) apply_filters('rnl_registration_hosts', [
            'letsreg.com', 'www.letsreg.com', 'letsreg.no', 'www.letsreg.no',
        ]));
    }
}
