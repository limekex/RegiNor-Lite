<?php

declare(strict_types=1);

namespace RegiNor\Lite\Infrastructure;

use function RegiNor\Lite\translate as __;
use function RegiNor\Lite\plural as _n;

final class VersionConflict extends \RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('Oppsettet er endret siden det ble lest. Last inn siste versjon og forhåndsvis på nytt.', 'reginor-lite'), 409);
    }
}
