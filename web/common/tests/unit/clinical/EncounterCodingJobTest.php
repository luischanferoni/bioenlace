<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Encounter\Application\Coding\EncounterCodingTargetRegistry;
use common\components\Domain\Clinical\Encounter\Application\UseCase\ApplyEncounterAutomaticCoding;
use common\models\Clinical\EncounterCodingJob;

class EncounterCodingJobTest extends \Codeception\Test\Unit
{
    public function testRegistryCoversAllCodableTargets(): void
    {
        $this->assertSame(
            ['diagnosis', 'chief_complaint', 'treatment_requests'],
            EncounterCodingTargetRegistry::keys()
        );
    }

    public function testBackoffIsExponentialAndBounded(): void
    {
        $this->assertSame(60, EncounterCodingJob::backoffSeconds(1));
        $this->assertSame(120, EncounterCodingJob::backoffSeconds(2));
        $this->assertSame(240, EncounterCodingJob::backoffSeconds(3));
        $this->assertSame(3600, EncounterCodingJob::backoffSeconds(10));
    }

    public function testDiagnosisTermsSkipCodedRowsAndDedupe(): void
    {
        $payload = [
            'EncounterDiagnosis' => [
                ['termino' => 'Cefalea'],
                ['termino' => 'Migraña', 'codigo_cie10' => 'G43.9'],
                ['termino' => 'Cefalea'],
            ],
        ];

        $this->assertSame(
            ['Cefalea'],
            ApplyEncounterAutomaticCoding::diagnosisTermsFromPayload($payload)
        );
    }

    public function testJobDiagnosisTermsReadsJsonArray(): void
    {
        $this->assertSame(
            ['Cefalea', 'Fiebre'],
            EncounterCodingJob::parseDiagnosisTerms(json_encode(['Cefalea', ' Fiebre ', '', 'Cefalea']))
        );
    }

    public function testJobWithoutTermsReturnsEmptyList(): void
    {
        $this->assertSame([], EncounterCodingJob::parseDiagnosisTerms(null));
        $this->assertSame([], EncounterCodingJob::parseDiagnosisTerms('no-json'));
    }
}
