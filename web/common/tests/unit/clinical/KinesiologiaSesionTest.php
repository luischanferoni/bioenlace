<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\RowContract\KinesiologiaSesionRowContract;
use common\components\Domain\Clinical\Specialty\Application\Service\KinesiologyEncounterService;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use Yii;

/**
 * Kinesiología: escalas validadas (EVA, fuerza), contrato y persistencia del panel.
 */
class KinesiologiaSesionTest extends Unit
{
    public function testScalesAreDeclared(): void
    {
        $this->assertSame(0, KinesiologiaSesionRowContract::PAIN_MIN);
        $this->assertSame(10, KinesiologiaSesionRowContract::PAIN_MAX);
        $this->assertSame(0, KinesiologiaSesionRowContract::STRENGTH_MIN);
        $this->assertSame(5, KinesiologiaSesionRowContract::STRENGTH_MAX);
    }

    public function testNormalizeKeepsInRangePainAndStrength(): void
    {
        $values = KinesiologiaSesionRowContract::normalize([
            'Region' => 'lumbar',
            'Lado' => 'Derecho',
            'EVA' => '6',
            'Fuerza muscular' => '3',
            'ROM' => '45',
        ]);

        $this->assertSame('lumbar', $values[KinesiologiaSesionRowContract::FIELD_REGION]);
        $this->assertSame('derecho', $values[KinesiologiaSesionRowContract::FIELD_LADO]);
        $this->assertSame('6', $values[KinesiologiaSesionRowContract::FIELD_DOLOR]);
        $this->assertSame('3', $values[KinesiologiaSesionRowContract::FIELD_FUERZA]);
        $this->assertSame('45', $values[KinesiologiaSesionRowContract::FIELD_MOVILIDAD]);
    }

    public function testOutOfScaleValuesAreDroppedNotClamped(): void
    {
        $values = KinesiologiaSesionRowContract::normalize([
            'EVA' => '11',
            'Fuerza' => '6',
            'Region' => 'cervical',
        ]);

        $this->assertArrayNotHasKey(KinesiologiaSesionRowContract::FIELD_DOLOR, $values);
        $this->assertArrayNotHasKey(KinesiologiaSesionRowContract::FIELD_FUERZA, $values);
        $this->assertSame('cervical', $values[KinesiologiaSesionRowContract::FIELD_REGION]);
    }

    public function testNegativeScalesAreDropped(): void
    {
        $values = KinesiologiaSesionRowContract::normalize([
            'EVA' => '-1',
            'Fuerza' => '-2',
        ]);

        $this->assertSame([], $values);
    }

    public function testUnknownSideIsDropped(): void
    {
        $values = KinesiologiaSesionRowContract::normalize([
            'Lado' => 'arriba',
            'Region' => 'hombro',
        ]);

        $this->assertArrayNotHasKey(KinesiologiaSesionRowContract::FIELD_LADO, $values);
        $this->assertSame('hombro', $values[KinesiologiaSesionRowContract::FIELD_REGION]);
    }

    public function testEveryPromptFieldHasStorage(): void
    {
        foreach (KinesiologiaSesionRowContract::promptFields() as $field) {
            $storage = KinesiologiaSesionRowContract::storageFor($field);
            $this->assertNotNull($storage, "«{$field}» no declara cómo se guarda.");
            $this->assertSame(KinesiologiaSesionRowContract::SYSTEM_LOCAL, $storage['system']);
        }
    }

    public function testEmptySessionIsIncomplete(): void
    {
        $assessment = KinesiologiaSesionRowContract::assess(['EVA' => '99']);

        $this->assertFalse($assessment->isComplete());
        $this->assertSame(
            KinesiologiaSesionRowContract::promptFields(),
            $assessment->missingFields()
        );
    }

    public function testCatalogDeclaresKinesiologyWithTheSession(): void
    {
        $kinesiology = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::KINESIOLOGY);

        $this->assertNotNull($kinesiology);
        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_SPECIFIC, $kinesiology['status']);
        $this->assertSame(
            [KinesiologiaSesionRowContract::MODELO],
            array_column($kinesiology['sections'], 'key')
        );
        $this->assertSame(
            ClinicalSpecialtyCatalog::KINESIOLOGY,
            ClinicalSpecialtyCatalog::specialtyForCaptureModel(KinesiologiaSesionRowContract::MODELO)
        );
    }

    public function testPersistAndReadSession(): void
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

            $service = new KinesiologyEncounterService();
            $created = $service->persistSession($encounter, [
                'Region' => 'lumbar',
                'Lado' => 'izquierdo',
                'EVA' => '6',
                'Fuerza' => '3',
                'Sesiones' => '10',
            ]);

            $this->assertSame(5, $created);
            $this->assertSame(5, (int) Observation::find()
                ->where([
                    'encounter_id' => $encounter->id,
                    'category' => Observation::CATEGORY_KINESIOLOGY,
                ])
                ->count());

            $sessions = $service->sessionsForPerson($personaId);
            $this->assertCount(1, $sessions);
            $this->assertSame('6', $sessions[0]['values'][KinesiologiaSesionRowContract::FIELD_DOLOR]['value']);
            $this->assertSame('/10', $sessions[0]['values'][KinesiologiaSesionRowContract::FIELD_DOLOR]['unit']);

            $pain = $service->painSeriesForPerson($personaId);
            $this->assertCount(1, $pain);
            $this->assertSame(6.0, $pain[0]['value']);
        } finally {
            $tx->rollBack();
            $encounter->subject_persona_id = $originalPersona;
        }
    }
}
