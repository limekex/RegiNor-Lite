<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\CourseLevelSuggestion;

final class CourseLevelSuggestionTest extends TestCase
{
    public static function names(): iterable
    {
        yield ['Salsa Nybegynner', 1];
        yield ['SALSA NYBEGYNNER (mandager)', 1];
        yield ["  Salsa\u{00a0}ØVET\u{00a0}1 ", 2];
        yield ['Salsa Øvet nivå 1', 2];
        yield ['Salsa Øvet1', 2];
        yield ['Rueda – Videregående', 3];
        yield ['Salsa Øvet 10', 4];
        yield ['Salsa Øvet 2', null];
        yield ['Salsa Nybegynner / Videregående', null];
        yield ['Salsa Øvet 1 / Øvet 10', null];
        yield ['Salsa supernybegynner', null];
        yield ['Salsa Nybegynnerkurs', null];
        yield ['Salsa Avansert', null];
        yield ['Salsa', null];
        yield ['', null];
    }

    #[DataProvider('names')]
    public function testConfiguredLevelsOnly(string $name, ?int $expected): void
    {
        self::assertSame($expected, CourseLevelSuggestion::fromName($name, [
            1 => ['title' => 'Nybegynner', 'active' => true],
            2 => ['title' => 'Øvet 1', 'active' => true],
            3 => ['title' => 'Videregående', 'active' => true],
            4 => ['title' => 'Øvet nivå 10', 'active' => true],
            5 => ['title' => 'Avansert', 'active' => false],
        ]));
    }

    public function testSpecificAndAmbiguousNames(): void
    {
        $levels = [1 => ['title' => 'Nybegynner', 'active' => true], 2 => ['title' => 'Salsa Nybegynner', 'active' => true]];
        self::assertSame(2, CourseLevelSuggestion::fromName('Salsa Nybegynner (mandag)', $levels));
        $levels[3] = ['title' => 'salsa-nybegynner', 'active' => true];
        self::assertNull(CourseLevelSuggestion::fromName('Salsa Nybegynner', $levels));
        self::assertNull(CourseLevelSuggestion::fromName('Salsa Nybegynner', []));
        self::assertNull(CourseLevelSuggestion::fromName('Salsa Øvet 2', [1 => ['title' => 'Øvet', 'active' => true]]));
    }
}
