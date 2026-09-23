<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\LetsRegDescriptionTemplate as Template;

final class LetsRegDescriptionTemplateTest extends TestCase
{
    private const TEXT = "Kursbeskrivelse\nDans i ring.\n\nDansestil\nRueda de Casino\n\nNivå og forkunnskaper\nGjennomført nybegynnerkurs.\n\nPartnerinformasjon\nVelg parpåmelding for rabatt.\n\nPrisvilkår og tillegg\nStudentrabatt 100 kr.\n\nPraktisk informasjon\nVelkommen! Bare hos LetsReg.";

    public function testPlainHeadingsSplitAllFiveFieldsAndExcludePracticalSection(): void
    {
        $parsed = Template::parse(self::TEXT);
        self::assertSame('structured', $parsed['mode']);
        self::assertSame(['description'=>'Dans i ring.', 'dance_style'=>'Rueda de Casino', 'level_description'=>'Gjennomført nybegynnerkurs.', 'partner_info'=>'Velg parpåmelding for rabatt.', 'price_terms'=>'Studentrabatt 100 kr.'], $parsed['fields']);
        self::assertTrue($parsed['ignored']);
        self::assertArrayNotHasKey('price_terms', Template::courseFields($parsed));
    }

    public function testCaseBoldMarkdownColonNbspAndCrLf(): void
    {
        $text = self::TEXT;
        foreach (Template::HEADINGS as $heading=>$key) { $text = str_replace($heading . "\n", '**' . mb_strtoupper(str_replace(' ', "\u{00a0}", $heading)) . ":**\n", $text); }
        self::assertSame(Template::parse(self::TEXT)['fields'], Template::parse(str_replace("\n", "\r\n", $text))['fields']);
        self::assertSame('structured', Template::parse(str_replace('Kursbeskrivelse', '## Kursbeskrivelse', self::TEXT))['mode']);
    }

    public function testLegacyProseIsNotGuessedOrTruncated(): void
    {
        $text = "Vi snakker om dansestil på kurset.\nRabatter\nStudent 100 kr.\nPraktisk informasjon\nTa med sko.";
        self::assertSame(['description'=>$text], Template::parse($text)['fields']);
        self::assertSame('plain', Template::parse($text)['mode']);
    }

    public function testIncompleteDuplicateAndPreamblePreserveWholeText(): void
    {
        foreach ([str_replace('Partnerinformasjon', 'Parnerinformasjon', self::TEXT), self::TEXT . "\nDansestil\nSalsa", "Innledning\n" . self::TEXT, str_replace("Dansestil\nRueda de Casino", 'Dansestil', self::TEXT)] as $text) {
            $parsed = Template::parse($text);
            self::assertSame('invalid', $parsed['mode']);
            self::assertSame(['description'=>$text], $parsed['fields']);
            self::assertFalse($parsed['ignored']);
        }
        self::assertSame(['Partnerinformasjon'], Template::parse(str_replace('Partnerinformasjon', 'Parnerinformasjon', self::TEXT))['missing']);
        $outOfOrder = str_replace("Praktisk informasjon\nVelkommen! Bare hos LetsReg.", '', self::TEXT);
        $outOfOrder = str_replace('Dansestil', "Praktisk informasjon\nVelkommen!\nDansestil", $outOfOrder);
        self::assertSame('invalid', Template::parse($outOfOrder)['mode']);
        self::assertSame(['description'=>$outOfOrder], Template::parse($outOfOrder)['fields']);
    }

    public function testRichMarkupIsKeptWhileHeadingsAreRecognized(): void
    {
        $text = '<p><strong>Kursbeskrivelse</strong></p><p>Dans <em>sammen</em>.</p><p>Dansestil</p><p>Salsa</p><p>Nivå og forkunnskaper</p><p>Grunnkurs.</p><p>Partnerinformasjon</p><p><a href="https://example.org/">Mer</a></p><p>Prisvilkår og tillegg</p><ul><li>Student</li></ul><p>Praktisk informasjon</p><p>Ikke importer.</p>';
        $parsed = Template::parse($text);
        self::assertSame('structured', $parsed['mode']);
        self::assertSame('<p>Dans <em>sammen</em>.</p>', $parsed['fields']['description']);
        self::assertSame('Salsa', $parsed['fields']['dance_style']);
        self::assertStringContainsString('<a href=', $parsed['fields']['partner_info']);
        self::assertStringContainsString('<li>Student</li>', $parsed['fields']['price_terms']);
        self::assertStringNotContainsString('Ikke importer', implode('', $parsed['fields']));
        self::assertSame('structured', Template::parse('<p>&nbsp;</p>' . str_replace('Nivå og forkunnskaper', 'Nivå&nbsp;og&nbsp;forkunnskaper', $text))['mode']);
    }

    public function testEmptyOptionalFieldsAndNoPracticalSection(): void
    {
        $text = "Kursbeskrivelse\nDans i ring.\nDansestil\nRueda\nNivå og forkunnskaper\nPartnerinformasjon\nPrisvilkår og tillegg\n";
        $parsed = Template::parse($text);
        self::assertSame('structured', $parsed['mode']);
        self::assertSame('', $parsed['fields']['partner_info']);
        self::assertSame('', $parsed['fields']['price_terms']);
        self::assertFalse($parsed['ignored']);
    }
}
