<?php

declare(strict_types=1);

namespace RegiNor\Lite\Domain\Publication;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
