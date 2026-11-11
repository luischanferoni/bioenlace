<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Billing\Application\Service\SignupEmailGate;

/**
 * Regla de e-mail confirmado antes del checkout del alta paga.
 */
class SignupEmailGateTest extends Unit
{
    public function testConLaReglaApagadaNoBloqueaNada(): void
    {
        SignupEmailGate::assertReady('', '', false, false);
        SignupEmailGate::assertReady('cuenta@accounts.bioenlace.invalid', 'pago@ejemplo.com', false, false);

        $this->addToAssertionCount(1);
    }

    public function testCuentaConEmailRealConfirmadoYIgualAlDelPagoPasa(): void
    {
        SignupEmailGate::assertReady('Persona@Ejemplo.com', ' persona@ejemplo.com ', true, true);

        $this->addToAssertionCount(1);
    }

    public function testCuentaSinEmailRealNoPuedePagar(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/agregar y confirmar tu e-mail/');

        SignupEmailGate::assertReady(
            'staff_123@accounts.bioenlace.invalid',
            'persona@ejemplo.com',
            false,
            true
        );
    }

    public function testCuentaSinEmailYConPagoInvalidoPideUnEmailValido(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/e-mail válido/');

        SignupEmailGate::assertReady('', 'no-es-un-email', false, true);
    }

    public function testEmailDePagoDistintoAlDeLaCuentaNoPasa(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/el de tu cuenta/');

        SignupEmailGate::assertReady('persona@ejemplo.com', 'otra@ejemplo.com', true, true);
    }

    public function testEmailSinConfirmarNoPasa(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Confirmá tu e-mail/');

        SignupEmailGate::assertReady('persona@ejemplo.com', 'persona@ejemplo.com', false, true);
    }
}
