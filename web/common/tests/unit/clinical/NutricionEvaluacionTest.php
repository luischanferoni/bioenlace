<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\NutricionEvaluacionRowContract;
use common\components\Domain\Clinical\Specialty\Application\Service\NutritionEncounterService;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use Yii;

/**
 * Nutrición: IMC derivado, contrato y persistencia del panel.
 */
class NutricionEvaluacionTest extends Unit
{
    public function testImcIsDerivedFromWeightAndHeight(): void
    {
        $this->assertSame('27.4', NutricionEvaluacionRowContract::imc('82', '173'));
        $this->assertSame('22.9', NutricionEvaluacionRowContract::imc('65', '168.5'));
    }

    public function testImcRequiresBothMeasures(): void
    {
        $this->assertNull(NutricionEvaluacionRowContract::imc(null, '170'));
        $this->assertNull(NutricionEvaluacionRowContract::imc('70', null));
        $this->assertNull(NutricionEvaluacionRowContract::imc('70', '0'));
        $this->assertNull(NutricionEvaluacionRowContract::imc('mucho', '170'));
    }

    public function testImcIsNeverTakenFromThePayload(): void
    {
        $values = NutricionEvaluacionRowContract::normalize([
            'Peso' => '82',
            'Talla' => '173',
            'IMC' => '15.0',
        ]);

        $this->assertSame('27.4', $values[NutricionEvaluacionRowContract::FIELD_IMC]);
    }

    public function testImcIsIgnoredWithoutMeasures(): void
    {
        $values = NutricionEvaluacionRowContract::normalize(['IMC' => '27.4']);

        $this->assertSame([], $values);
    }

    public function testImcIsNotAskedToTheExtractor(): void
    {
        $this->assertNotContains(NutricionEvaluacionRowContract::FIELD_IMC, NutricionEvaluacionRowContract::promptFields());
    }

    public function testEveryPromptFieldHasStorage(): void
    {
        foreach (NutricionEvaluacionRowContract::promptFields() as $field) {
            $storage = NutricionEvaluacionRowContract::storageFor($field);
            $this->assertNotNull($storage, "«{$field}» no declara cómo se guarda.");
            $this->assertSame(NutricionEvaluacionRowContract::SYSTEM_LOCAL, $storage['system']);
        }
        $this->assertNotNull(NutricionEvaluacionRowContract::storageFor(NutricionEvaluacionRowContract::FIELD_IMC));
    }

    public function testNormalizeMapsAliasesAndRejectsNonNumericMeasures(): void
    {
        $values = NutricionEvaluacionRowContract::normalize([
            'Peso' => '82,5',
            'Altura' => '1.73 m',
            'Cintura' => '96',
            'Plan' => 'Hipocalórico',
            'Adherencia' => 'buena',
        ]);

        $this->assertSame('82.5', $values[NutricionEvaluacionRowContract::FIELD_PESO]);
        $this->assertArrayHasKey(NutricionEvaluacionRowContract::FIELD_CINTURA, $values);
        $this->assertArrayNotHasKey(NutricionEvaluacionRowContract::FIELD_TALLA, $values);
        $this->assertArrayNotHasKey(NutricionEvaluacionRowContract::FIELD_IMC, $values);
        $this->assertSame('Hipocalórico', $values[NutricionEvaluacionRowContract::FIELD_PLAN]);
        $this->assertSame('buena', $values[NutricionEvaluacionRowContract::FIELD_ADHERENCIA]);
    }

    public function testEmptyAssessmentIsIncomplete(): void
    {
        $assessment = NutricionEvaluacionRowContract::assess(['Otra cosa' => 'x']);

        $this->assertFalse($assessment->isComplete());
        $this->assertSame(
            NutricionEvaluacionRowContract::promptFields(),
            $assessment->missingFields()
        );
    }

    public function testAssessmentWithOneMeasureIsComplete(): void
    {
        $assessment = NutricionEvaluacionRowContract::assess(['Peso' => '82']);

        $this->assertTrue($assessment->isComplete());
    }

    public function testCatalogDeclaresNutritionWithTheAssessment(): void
    {
        $nutrition = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::NUTRITION);

        $this->assertNotNull($nutrition);
        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_SPECIFIC, $nutrition['status']);
        $this->assertSame(
            [NutricionEvaluacionRowContract::MODELO],
            array_column($nutrition['sections'], 'key')
        );
        $this->assertSame(
            ClinicalSpecialtyCatalog::NUTRITION,
            ClinicalSpecialtyCatalog::specialtyForCaptureModel(NutricionEvaluacionRowContract::MODELO)
        );
    }

    public function testPersistAndReadAssessment(): void
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

            $service = new NutritionEncounterService();
            $created = $service->persistAssessment($encounter, [
                'Peso' => '82',
                'Talla' => '173',
                'Cintura' => '96',
                'Objetivo' => 'Descenso de peso',
            ]);

            // Peso, talla, cintura, objetivo e IMC derivado.
            $this->assertSame(5, $created);
            $this->assertSame(5, (int) Observation::find()
                ->where([
                    'encounter_id' => $encounter->id,
                    'category' => Observation::CATEGORY_NUTRITION,
                ])
                ->count());

            $panels = $service->assessmentsForPerson($personaId);
            $this->assertCount(1, $panels);
            $this->assertSame('27.4', $panels[0]['values'][NutricionEvaluacionRowContract::FIELD_IMC]['value']);
            $this->assertSame('kg/m2', $panels[0]['values'][NutricionEvaluacionRowContract::FIELD_IMC]['unit']);
            $this->assertSame('Descenso de peso', $panels[0]['values'][NutricionEvaluacionRowContract::FIELD_OBJETIVO]['value']);

            $series = $service->imcSeriesForPerson($personaId);
            $this->assertCount(1, $series);
            $this->assertSame(27.4, $series[0]['value']);
        } finally {
            $tx->rollBack();
            $encounter->subject_persona_id = $originalPersona;
        }
    }
}
