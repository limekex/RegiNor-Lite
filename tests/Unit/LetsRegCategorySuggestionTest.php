<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Admin\LetsRegCategorySuggestion;

final class LetsRegCategorySuggestionTest extends TestCase
{
    public static function names(): iterable
    {
        yield ['Rueda Videregående som fører', ['role' => 'leader', 'registration' => 'single']];
        yield ['Rueda videregående som følger', ['role' => 'follower', 'registration' => 'single']];
        yield ['Parpåmelding Rueda Vdg som Følger', ['role' => 'follower', 'registration' => 'pair']];
        yield ['Parpåmelding Rueda Vdg som Fører', ['role' => 'leader', 'registration' => 'pair']];
        yield ['PARPÅMELDING – FØRER', ['role' => 'leader', 'registration' => 'pair']];
        yield ['Par-påmelding (følger)', ['role' => 'follower', 'registration' => 'pair']];
        yield ['Par påmelding / førere', ['role' => 'leader', 'registration' => 'pair']];
        yield ['Enkeltpåmelding: følger', ['role' => 'follower', 'registration' => 'single']];
        yield ['Fører / følger', null];
        yield ['Parpåmelding eller enkeltpåmelding for fører', null];
        yield ['Rueda Videregående', null];
        yield ['Parpåmelding', null];
        yield ['Medfølger gratis', null];
        yield ['Førertrening', null];
        yield ['Partnerøvelse – følger', ['role' => 'follower', 'registration' => 'single']];
        yield ['', null];
    }

    #[DataProvider('names')]
    public function testOnlyUnambiguousNamesSuggestDefaults(string $name, ?array $expected): void
    {
        self::assertSame($expected, LetsRegCategorySuggestion::fromName($name));
    }
}
