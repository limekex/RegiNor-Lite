<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RegiNor\Lite\Domain\RegistrationUrl;

final class RegistrationUrlTest extends TestCase
{
    public static function validUrls(): array
    {
        return [
            ['https://www.letsreg.com/no/register/SalsaØvet1_4_26', 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26'],
            ['https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26', 'https://www.letsreg.com/no/register/Salsa%C3%98vet1_4_26'],
            ['https://letsreg.no/no/event/æøå?kurs=Øvet&next=%2Fno%2Fevent&tag=a+b#påmelding', 'https://letsreg.no/no/event/%C3%A6%C3%B8%C3%A5?kurs=%C3%98vet&next=%2Fno%2Fevent&tag=a+b#p%C3%A5melding'],
            ['https://www.letsreg.com/no/event/kurs?tag=%C3%98&lang=no#register', 'https://www.letsreg.com/no/event/kurs?tag=%C3%98&lang=no#register'],
            ['HTTPS://WWW.LETSREG.COM/no/register/SalsaØvet1_4_26', 'https://WWW.LETSREG.COM/no/register/Salsa%C3%98vet1_4_26'],
            [' https://www.letsreg.no ', 'https://www.letsreg.no'],
            ['', ''],
        ];
    }

    #[DataProvider('validUrls')]
    public function testUnicodeAndAlreadyEncodedUrlsKeepTheSameDestination(string $input, string $expected): void
    {
        self::assertSame($expected, RegistrationUrl::normalize($input));
        self::assertSame($expected, RegistrationUrl::normalize($expected));
    }

    public static function invalidUrls(): array
    {
        return array_map(static fn (string $url): array => [$url], [
            'http://www.letsreg.com/no/register/SalsaØvet1_4_26',
            'https://user:pass@www.letsreg.com/no/register/SalsaØvet1_4_26',
            'https://@www.letsreg.com/no/register/SalsaØvet1_4_26',
            'https://www.letsreg.com/no/register/Salsa Øvet',
            'https://www.letsreg.com\\@evil.example/no/register/Øvet',
            "https://www.letsreg.com/no/register/Salsa\nØvet",
            "https://www.letsreg.com/no/register/\xC3\x28",
            'https://létsreg.com/no/register/Øvet',
            'https:///no/register/Øvet',
            'javascript:alert(1)',
        ]);
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidAddressesStillFail(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        RegistrationUrl::normalize($input);
    }
}
