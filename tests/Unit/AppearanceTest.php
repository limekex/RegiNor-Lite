<?php

declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Infrastructure\Appearance;

final class AppearanceTest extends TestCase
{
    public function testColorsAreNormalizedAndTextContrastsWithLightAndDarkBackgrounds(): void
    {
        self::assertSame('#aabbcc', Appearance::color('#AABBCC'));
        self::assertSame('#ffffff', Appearance::ink('#000000'));
        self::assertSame('#000000', Appearance::ink('#ffffff'));
        self::assertSame('#ffffff', Appearance::ink('#61364d'));
        self::assertSame('#000000', Appearance::ink('#fff2cf'));
    }
    public function testCssCannotBeInjectedThroughColorInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Appearance::validate(['background' => '#fff; background:url(https://example.com)']);
    }
    public function testUnsupportedLayoutIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class); Appearance::validate(['layout' => 'arbitrary']);
    }
    public function testAlphaAndPaletteStayLinkedWithoutChangingTextOpacity(): void
    {
        $s = Appearance::validate(['background' => '#112233', 'background_source' => 'avada:color4', 'background_alpha' => '50', 'background_tone' => 'light']);
        self::assertSame(50, $s['background_alpha']);
        self::assertStringContainsString('--rnl-background-bg:color-mix(in srgb,var(--awb-color4,#112233) 50%,transparent);--rnl-background-ink:#ffffff;', Appearance::variables($s));
        self::assertStringNotContainsString('opacity:', Appearance::variables($s));
    }
    public function testInvalidAlphaIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class); Appearance::validate(['course_alpha' => 101]);
    }
    public function testPaletteReferenceCannotInjectCss(): void
    {
        $this->expectException(InvalidArgumentException::class); Appearance::validate(['room_source' => 'wp:primary);color:red;']);
    }
    public function testFullyTransparentAndOpaqueAreAllowed(): void
    {
        self::assertSame('color-mix(in srgb,#123456 0%,transparent)', \RegiNor\Lite\Infrastructure\ColorPalette::expression('#123456', '', 0));
        self::assertSame('#123456', \RegiNor\Lite\Infrastructure\ColorPalette::expression('#123456', '', 100));
    }
    public function testDefaultPresentationKeepsPeriodChoice(): void
    {
        self::assertSame('period', Appearance::validate([])['view']);
        self::assertSame('columns', Appearance::validate([])['layout']);
        self::assertStringContainsString('--rnl-room-bg:#f1e9e4;', Appearance::variables(Appearance::validate([])));
    }
}
