<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Application\Service\RefepsProfessionalVerificationService;
use common\components\Domain\Person\Identity\Application\Service\RefepsVerificationResult;

final class RefepsProfessionalVerificationServiceTest extends Unit
{
    public function testConfirmsOnlyAnExactDocumentMatch(): void
    {
        $result = RefepsProfessionalVerificationService::parseResponse([
            'ok' => true,
            'total' => 1,
            'profesionales' => [
                ['nro_documento' => '20.123.456'],
            ],
        ], '20123456');

        $this->assertSame(RefepsVerificationResult::CONFIRMED, $result->status);
    }

    public function testDistinguishesSuccessfulLookupWithNoResults(): void
    {
        $result = RefepsProfessionalVerificationService::parseResponse([
            'ok' => true,
            'total' => 0,
            'profesionales' => [],
        ], '20123456');

        $this->assertSame(RefepsVerificationResult::NOT_FOUND, $result->status);
    }

    public function testDoesNotTreatProviderFailureAsNotFound(): void
    {
        $result = RefepsProfessionalVerificationService::parseResponse([
            'ok' => false,
            'message' => 'timeout',
        ], '20123456');

        $this->assertSame(RefepsVerificationResult::UNAVAILABLE, $result->status);
    }

    public function testRequiresReviewWhenTheResponseCannotProveIdentity(): void
    {
        $result = RefepsProfessionalVerificationService::parseResponse([
            'ok' => true,
            'total' => 1,
            'profesionales' => [
                ['nombre' => 'Persona'],
            ],
        ], '20123456');

        $this->assertSame(RefepsVerificationResult::REVIEW_REQUIRED, $result->status);
    }

    public function testRequiresReviewWhenReturnedDocumentDoesNotMatch(): void
    {
        $result = RefepsProfessionalVerificationService::parseResponse([
            'ok' => true,
            'total' => 1,
            'profesionales' => [
                ['documento' => '20.987.654'],
            ],
        ], '20123456');

        $this->assertSame(RefepsVerificationResult::REVIEW_REQUIRED, $result->status);
    }
}
