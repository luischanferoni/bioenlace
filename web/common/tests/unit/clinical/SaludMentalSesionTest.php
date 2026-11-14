<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\SaludMentalSesionRowContract;
use common\components\Domain\Clinical\Specialty\Application\Service\MentalHealthEncounterService;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\MentalHealthInstrumentCatalog;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use Yii;

/**
 * Salud mental: instrumentos, contrato, severidad derivada y persistencia.
 */
class SaludMentalSesionTest extends Unit
{
    public function testInstrumentCodesAndRanges(): void
    {
        $phq9 = MentalHealthInstrumentCatalog::get(MentalHealthInstrumentCatalog::PHQ9);
        $gad7 = MentalHealthInstrumentCatalog::get(MentalHealthInstrumentCatalog::GAD7);

        $this->assertNotNull($phq9);
        $this->assertSame(27, $phq9['max']);
        $this->assertNotNull($gad7);
        $this->assertSame(21, $gad7['max']);
        $this->assertSame(
            [MentalHealthInstrumentCatalog::PHQ9, MentalHealthInstrumentCatalog::GAD7],
            MentalHealthInstrumentCatalog::codes()
        );
    }

    public function testSeverityIsDerivedFromBands(): void
    {
        $cases = [
            [0, 'Mínima'],
            [4, 'Mínima'],
            [5, 'Leve'],
            [10, 'Moderada'],
            [15, 'Moderadamente grave'],
            [20, 'Grave'],
            [27, 'Grave'],
        ];
        foreach ($cases as [$score, $expected]) {
            $severity = MentalHealthInstrumentCatalog::severityFor(
                MentalHealthInstrumentCatalog::PHQ9,
                $score
            );
            $this->assertNotNull($severity, (string) $score);
            $this->assertSame($expected, $severity['label'], (string) $score);
        }
    }

    public function testSeverityRejectsOutOfRangeAndNonNumeric(): void
    {
        $this->assertNull(MentalHealthInstrumentCatalog::severityFor('phq9', 28));
        $this->assertNull(MentalHealthInstrumentCatalog::severityFor('phq9', -1));
        $this->assertNull(MentalHealthInstrumentCatalog::severityFor('phq9', 'mucho'));
        $this->assertNull(MentalHealthInstrumentCatalog::severityFor('unknown', 5));
    }

    public function testPromptFieldsComeFromTheInstrumentCatalog(): void
    {
        $fields = SaludMentalSesionRowContract::promptFields();

        foreach (MentalHealthInstrumentCatalog::all() as $instrument) {
            $this->assertContains($instrument['label'], $fields);
        }
        $this->assertContains(SaludMentalSesionRowContract::FIELD_EVOLUCION, $fields);
    }

    public function testEveryPromptFieldHasStorage(): void
    {
        foreach (SaludMentalSesionRowContract::promptFields() as $field) {
            $storage = SaludMentalSesionRowContract::storageFor($field);
            $this->assertNotNull($storage, "«{$field}» no declara cómo se guarda.");
            $this->assertSame(SaludMentalSesionRowContract::SYSTEM_LOCAL, $storage['system']);
        }
    }

    public function testNormalizeMapsAliasesAndRejectsNonNumericScores(): void
    {
        $values = SaludMentalSesionRowContract::normalize([
            'Motivo' => 'Insomnio persistente',
            'PHQ9' => '12',
            'GAD-7' => 'mucho',
            'Evolución' => 'Refiere mejoría',
        ]);

        $this->assertSame('Insomnio persistente', $values[SaludMentalSesionRowContract::FIELD_MOTIVO]);
        $this->assertSame('12', $values['PHQ-9']);
        $this->assertArrayNotHasKey('GAD-7', $values);
        $this->assertSame('Refiere mejoría', $values[SaludMentalSesionRowContract::FIELD_EVOLUCION]);
    }

    public function testEmptySessionIsIncomplete(): void
    {
        $assessment = SaludMentalSesionRowContract::assess(['Otra cosa' => 'x']);

        $this->assertFalse($assessment->isComplete());
        $this->assertSame(
            SaludMentalSesionRowContract::promptFields(),
            $assessment->missingFields()
        );
    }

    public function testCatalogDeclaresBothSpecialtiesWithTheSession(): void
    {
        $mental = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::MENTAL_HEALTH);
        $psychology = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::PSYCHOLOGY);

        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_SPECIFIC, $mental['status']);
        $this->assertSame(
            [SaludMentalSesionRowContract::MODELO],
            array_column($mental['sections'], 'key')
        );

        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_STANDARD, $psychology['status']);
        $this->assertSame([], $psychology['sections']);
        $this->assertSame(
            [SaludMentalSesionRowContract::MODELO],
            array_column($psychology['extra_sections'], 'key')
        );
    }

    public function testSessionCategoryBelongsToMentalHealth(): void
    {
        $this->assertSame(
            ClinicalSpecialtyCatalog::MENTAL_HEALTH,
            ClinicalSpecialtyCatalog::specialtyForCaptureModel(SaludMentalSesionRowContract::MODELO)
        );
    }

    public function testPersistAndReadSessionWithSeverity(): void
    {
        if (Yii::$app->has('request')) {
            Yii::$app->request->cookieValidationKey = 'test';
        }

        $personaId = (int) (new \yii\db\Query())
            ->select('id_persona')->from('{{%personas}}')->scalar(Yii::$app->db);
        $encounter = Encounter::findOne((int) (new \yii\db\Query())
            ->select('id')->from('{{%encounter}}')->scalar(Yii::$app->db));
        $this->assertNotNull($encounter);
        $originalPersona = (int) $encounter->subject_persona_id;

        $tx = Yii::$app->db->beginTransaction();
        try {
            $encounter->subject_persona_id = $personaId;

            $service = new MentalHealthEncounterService();
            $created = $service->persistSession($encounter, [
                'Motivo' => 'Insomnio',
                'PHQ9' => '12',
                'GAD7' => '8',
                'Riesgo suicida' => 'negado',
            ]);

            $this->assertSame(4, $created);
            $this->assertSame(4, (int) Observation::find()
                ->where([
                    'encounter_id' => $encounter->id,
                    'category' => Observation::CATEGORY_MENTAL_HEALTH,
                ])
                ->count());

            $sessions = $service->sessionsForPerson($personaId);
            $this->assertCount(1, $sessions);

            $scores = $sessions[0]['scores'];
            $byCode = array_column($scores, null, 'code');
            $this->assertSame(12.0, $byCode['phq9']['value']);
            $this->assertSame('Moderada', $byCode['phq9']['severity']['label']);
            $this->assertSame(8.0, $byCode['gad7']['value']);
            $this->assertSame('Leve', $byCode['gad7']['severity']['label']);

            // Los campos de texto quedan como valores, no como puntajes.
            $this->assertSame('Insomnio', $sessions[0]['values']['Motivo de sesion']['value']);
            $this->assertSame(['PHQ-9', 'GAD-7'], array_column($scores, 'label'));

            $series = $service->instrumentSeriesForPerson(
                $personaId,
                MentalHealthInstrumentCatalog::PHQ9
            );
            $this->assertCount(1, $series);
            $this->assertSame(12.0, $series[0]['value']);
            $this->assertSame('Moderada', $series[0]['severity']['label']);
        } finally {
            $tx->rollBack();
            $encounter->subject_persona_id = $originalPersona;
        }
    }
}
