<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Catalog\EncounterDefinitionWorkflowCatalog;
use common\components\Domain\Clinical\Capture\Domain\RowContract\ObstetriciaControlRowContract;
use common\components\Domain\Clinical\Specialty\Application\Service\ObstetricsEncounterService;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;
use common\models\Clinical\Encounter;
use common\models\Clinical\Observation;
use Yii;

/**
 * Control prenatal: contrato, catálogo y persistencia como observaciones.
 */
class ObstetriciaControlTest extends Unit
{
    public function testNormalizeMapsAliases(): void
    {
        $values = ObstetriciaControlRowContract::normalize([
            'FUM' => '2026-03-10',
            'EG' => '28',
            'Gestas' => '2',
            'Paridad' => '1',
            'AU' => '29,5',
            'FCF' => '140',
            'Presentación' => 'cefálica',
        ]);

        $this->assertSame('2026-03-10', $values[ObstetriciaControlRowContract::FIELD_FUM]);
        $this->assertSame('28', $values[ObstetriciaControlRowContract::FIELD_EDAD_GESTACIONAL]);
        $this->assertSame('2', $values[ObstetriciaControlRowContract::FIELD_GRAVIDEZ]);
        $this->assertSame('1', $values[ObstetriciaControlRowContract::FIELD_PARIDAD]);
        $this->assertSame('29.5', $values[ObstetriciaControlRowContract::FIELD_ALTURA_UTERINA]);
        $this->assertSame('140', $values[ObstetriciaControlRowContract::FIELD_LATIDO_FETAL]);
        $this->assertSame('cefálica', $values[ObstetriciaControlRowContract::FIELD_PRESENTACION]);
    }

    public function testAmbiguousSingleLettersAreNotAliases(): void
    {
        $this->assertNull(ObstetriciaControlRowContract::fieldFor('g'));
        $this->assertNull(ObstetriciaControlRowContract::fieldFor('p'));
        $this->assertNull(ObstetriciaControlRowContract::fieldFor('a'));
        $this->assertNull(ObstetriciaControlRowContract::fieldFor('Peso'));
        $this->assertSame(
            ObstetriciaControlRowContract::FIELD_ALTURA_UTERINA,
            ObstetriciaControlRowContract::fieldFor('AU')
        );
    }

    public function testEveryPromptFieldHasStorage(): void
    {
        foreach (ObstetriciaControlRowContract::PROMPT_FIELDS as $field) {
            $storage = ObstetriciaControlRowContract::storageFor($field);
            $this->assertNotNull($storage, "«{$field}» no declara cómo se guarda.");
            $this->assertNotSame('', $storage['code'], $field);
            $this->assertContains($storage['type'], [
                ObstetriciaControlRowContract::KIND_NUMERIC,
                ObstetriciaControlRowContract::KIND_TEXT,
                ObstetriciaControlRowContract::KIND_DATE,
            ], $field);
        }
    }

    public function testNumericFieldsDeclareUnits(): void
    {
        foreach (['Edad gestacional', 'Altura uterina', 'Latido fetal'] as $field) {
            $this->assertNotNull(ObstetriciaControlRowContract::storageFor($field)['unit'], $field);
        }
    }

    public function testEmptyControlIsIncomplete(): void
    {
        $assessment = ObstetriciaControlRowContract::assess(['Observaciones' => 'sin datos']);

        $this->assertFalse($assessment->isComplete());
        $this->assertSame(ObstetriciaControlRowContract::PROMPT_FIELDS, $assessment->missingFields());
    }

    public function testOneValueIsEnough(): void
    {
        $assessment = ObstetriciaControlRowContract::assess(['AU' => '29']);

        $this->assertTrue($assessment->isComplete());
        $this->assertSame('Altura uterina 29', $assessment->label());
    }

    public function testCatalogDeclaresObstetricsAsSpecific(): void
    {
        $definition = ClinicalSpecialtyCatalog::get(ClinicalSpecialtyCatalog::OBSTETRICS);

        $this->assertNotNull($definition);
        $this->assertSame(ClinicalSpecialtyCatalog::STATUS_SPECIFIC, $definition['status']);
        $this->assertSame(
            [ObstetriciaControlRowContract::MODELO],
            array_column($definition['sections'], 'key')
        );
        $this->assertSame(
            ObstetriciaControlRowContract::PROMPT_FIELDS,
            array_column($definition['sections'][0]['fields'], 'key')
        );
    }

    public function testObstetricWorkflowIncludesTheControlStep(): void
    {
        $steps = EncounterDefinitionWorkflowCatalog::templates()[
            EncounterDefinitionWorkflowCatalog::TEMPLATE_AMB_OBSTETRICS
        ];

        $this->assertContains('EncounterObstetricControl', array_column($steps, 'relacion'));
        $this->assertSame(
            EncounterDefinitionWorkflowCatalog::TEMPLATE_AMB_OBSTETRICS,
            EncounterDefinitionWorkflowCatalog::templateForServiceName('OBSTETRICIA', 'AMB')
        );
    }

    public function testPersistAndReadControlRoundTrip(): void
    {
        // Los AR clínicos registran `created_by` vía Yii::$app->user, que en tests
        // necesita la cookie key de la request.
        if (Yii::$app->has('request')) {
            Yii::$app->request->cookieValidationKey = 'test';
        }

        $personaId = (int) (new \yii\db\Query())
            ->select('id_persona')->from('{{%personas}}')->scalar(Yii::$app->db);
        $encounterId = (int) (new \yii\db\Query())
            ->select('id')->from('{{%encounter}}')->scalar(Yii::$app->db);

        $encounter = Encounter::findOne($encounterId);
        $this->assertNotNull($encounter);
        $originalPersona = (int) $encounter->subject_persona_id;

        $tx = Yii::$app->db->beginTransaction();
        try {
            $encounter->subject_persona_id = $personaId;

            $service = new ObstetricsEncounterService();
            $created = $service->persistControl($encounter, [
                'FUM' => '2026-03-10',
                'EG' => '28',
                'AU' => '29,5',
                'FCF' => '140',
                'Presentacion' => 'cefalica',
            ]);

            $this->assertSame(5, $created);
            $this->assertSame(5, (int) Observation::find()
                ->where([
                    'encounter_id' => $encounter->id,
                    'category' => Observation::CATEGORY_OBSTETRICS,
                ])
                ->count());

            $controls = $service->controlsForPerson($personaId);
            $this->assertCount(1, $controls);
            $control = $controls[0];

            $this->assertSame(
                [
                    ObstetriciaControlRowContract::FIELD_FUM,
                    ObstetriciaControlRowContract::FIELD_EDAD_GESTACIONAL,
                    ObstetriciaControlRowContract::FIELD_ALTURA_UTERINA,
                    ObstetriciaControlRowContract::FIELD_LATIDO_FETAL,
                    ObstetriciaControlRowContract::FIELD_PRESENTACION,
                ],
                array_keys($control['values'])
            );
            $this->assertSame('2026-03-10', $control['values']['FUM']['value']);
            $this->assertSame('sem', $control['values']['Edad gestacional']['unit']);
            $this->assertSame('29.5', $control['values']['Altura uterina']['value']);
            $this->assertSame('cm', $control['values']['Altura uterina']['unit']);
            $this->assertSame('cefalica', $control['values']['Presentacion']['value']);

            $series = $service->seriesForPerson($personaId, ObstetriciaControlRowContract::FIELD_ALTURA_UTERINA);
            $this->assertCount(1, $series);
            $this->assertSame(29.5, $series[0]['value']);
        } finally {
            $tx->rollBack();
            $encounter->subject_persona_id = $originalPersona;
        }
    }
}
