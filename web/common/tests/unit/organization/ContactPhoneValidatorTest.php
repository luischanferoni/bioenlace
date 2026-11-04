<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Efector\Application\Service\ContactPhoneValidator;

class ContactPhoneValidatorTest extends Unit
{
    public function testTelefonoVacioEsValidoPorqueEsOpcional(): void
    {
        $this->assertTrue(ContactPhoneValidator::isValidMobile(null));
        $this->assertTrue(ContactPhoneValidator::isValidMobile(''));
        $this->assertTrue(ContactPhoneValidator::isValidMobile('   '));
        $this->assertNull(ContactPhoneValidator::normalizeMobile('  '));
    }

    /**
     * @dataProvider mobileArgentinoProvider
     */
    public function testNormalizaCelularArgentino(string $input, string $expected): void
    {
        $this->assertSame($expected, ContactPhoneValidator::normalizeMobile($input));
        $this->assertTrue(ContactPhoneValidator::isValidMobile($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mobileArgentinoProvider(): array
    {
        return [
            'e164' => ['+5491123456789', '+5491123456789'],
            'sin mas' => ['5491123456789', '+5491123456789'],
            'area y numero' => ['1123456789', '+5491123456789'],
            'con separadores' => ['+54 9 11 2345-6789', '+5491123456789'],
            'nacional con 15' => ['011 15 1234-5678', '+5491112345678'],
            'area de 3 digitos' => ['0351-15-123-4567', '+5493511234567'],
            'area de 4 digitos' => ['0221 15 1234567', '+5492211234567'],
        ];
    }

    public function testRechazaFijoArgentinoSinElNueve(): void
    {
        $this->assertFalse(ContactPhoneValidator::isValidMobile('+5411123456789'));
        $this->assertFalse(ContactPhoneValidator::isValidMobile('+541123456789'));
    }

    public function testRechazaNumerosInvalidos(): void
    {
        $this->assertFalse(ContactPhoneValidator::isValidMobile('123'));
        $this->assertFalse(ContactPhoneValidator::isValidMobile('abc'));
        $this->assertFalse(ContactPhoneValidator::isValidMobile('0221 15 12345678'));
    }

    public function testAceptaOtrosPaisesEnE164(): void
    {
        $this->assertTrue(ContactPhoneValidator::isValidMobile('+14155552671'));
        $this->assertTrue(ContactPhoneValidator::isValidMobile('+5511987654321'));
        $this->assertSame('+14155552671', ContactPhoneValidator::normalizeMobile('+1 415 555 2671'));
    }

    public function testAssertValidMobileLanzaConMensajeUtil(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('+5491123456789');
        ContactPhoneValidator::assertValidMobile('+5411123456789', 'Teléfono del administrador');
    }
}
