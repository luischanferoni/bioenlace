<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Encounter\Application\Coding\EncounterCodingTargetRegistry;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCodingJobService;
use common\components\Domain\Clinical\Encounter\Application\UseCase\ApplyEncounterAutomaticCoding;
use common\components\Domain\Clinical\Encounter\Domain\EncounterStatus;
use common\models\Clinical\EncounterCodingJob;
use Yii;

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

    public function testAnnulledEncountersAreSkipped(): void
    {
        // Vigente: se codifica.
        $this->assertNull(EncounterCodingJobService::annulmentReasonFor(EncounterStatus::FINISHED, null));

        // Una edición anula el anterior: no se codifica.
        $this->assertSame(
            EncounterStatus::CANCELLED,
            EncounterCodingJobService::annulmentReasonFor(EncounterStatus::CANCELLED, null)
        );
        $this->assertSame(
            EncounterStatus::ENTERED_IN_ERROR,
            EncounterCodingJobService::annulmentReasonFor(EncounterStatus::ENTERED_IN_ERROR, null)
        );
        $this->assertSame(
            'deleted_at',
            EncounterCodingJobService::annulmentReasonFor(EncounterStatus::FINISHED, '2026-01-01 00:00:00')
        );
    }

    public function testDelayDefaultsTo48HoursAndAcceptsConfiguration(): void
    {
        $backup = Yii::$app->params;
        try {
            unset(Yii::$app->params['encounter_coding_delay_hours']);
            $this->assertSame(48 * 3600, EncounterCodingJobService::delaySecondsFromParams());

            Yii::$app->params['encounter_coding_delay_hours'] = 0;
            $this->assertSame(0, EncounterCodingJobService::delaySecondsFromParams());

            Yii::$app->params['encounter_coding_delay_hours'] = 72;
            $this->assertSame(72 * 3600, EncounterCodingJobService::delaySecondsFromParams());

            Yii::$app->params['encounter_coding_delay_hours'] = -5;
            $this->assertSame(0, EncounterCodingJobService::delaySecondsFromParams());
        } finally {
            Yii::$app->params = $backup;
        }
    }
}
