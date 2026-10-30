<?php

namespace common\tests\unit\person;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\IdentityVerificationException;
use common\components\Domain\Person\Identity\Application\UseCase\RegisterPerson;

final class RegisterPersonProfessionalSignupRetiredTest extends Unit
{
    public function testProfessionalSignupIsRejectedBeforeIdentityResolution(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Este flujo solo registra pacientes.');

        (new RegisterPerson())->registrar([
            'tipo' => 'medico',
            'verification_id' => 'must-not-be-resolved',
        ]);
    }

    public function testPatientRegistrationStillRequiresDiditVerification(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El campo "verification_id" de Didit es requerido para el registro.');

        (new RegisterPerson())->registrar(['tipo' => 'paciente']);
    }

    public function testIdentityVerificationMustBeExplicitlyApproved(): void
    {
        DiditIdentityService::assertApprovedVerification([
            'success' => true,
            'status' => 'approved',
        ]);

        $this->expectException(\RuntimeException::class);
        DiditIdentityService::assertApprovedVerification([
            'success' => true,
            'status' => 'pending',
        ]);
    }

    public function testUnknownVerificationStatusIsRejected(): void
    {
        try {
            DiditIdentityService::assertApprovedVerification([
                'success' => true,
                'status' => 'unknown',
            ]);
            $this->fail('Unknown verification status must be rejected.');
        } catch (IdentityVerificationException $exception) {
            $this->assertSame('unknown', $exception->verificationStatus);
            $this->assertTrue($exception->isRetryable());
        }
    }
}
