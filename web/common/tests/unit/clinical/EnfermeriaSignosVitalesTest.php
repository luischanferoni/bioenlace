<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Capture\Domain\Catalog\EncounterDefinitionWorkflowCatalog;
use common\components\Domain\Clinical\Capture\Domain\RowContract\EnfermeriaSignosVitalesRowContract;

/**
 * Contrato de signos vitales de enfermería: alias, claves persistidas y completitud.
 */
class EnfermeriaSignosVitalesTest extends Unit
{
    public function testEveryPromptFieldIsPersisted(): void
    {
        foreach (EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS as $field) {
            $datos = EnfermeriaSignosVitalesRowContract::normalize([$field => '1']);

            $this->assertNotSame(
                [],
                $datos,
                "El prompt pide «{$field}» y la normalización lo descarta."
            );
        }
    }

    public function testNormalizeMapsArterialPressure(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'TA sistolica' => '120',
            'TA diastolica' => '80',
        ]);

        $this->assertSame('120', $datos[EnfermeriaSignosVitalesRowContract::CANON_SISTOLICA]);
        $this->assertSame('80', $datos[EnfermeriaSignosVitalesRowContract::CANON_DIASTOLICA]);
        $this->assertSame(
            [
                EnfermeriaSignosVitalesRowContract::CANON_SISTOLICA => '120',
                EnfermeriaSignosVitalesRowContract::CANON_DIASTOLICA => '80',
            ],
            $datos[EnfermeriaSignosVitalesRowContract::CANON_TENSION]
        );
    }

    public function testNormalizeIgnoresAccentsAndCase(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'SATURACIÓN O2' => '98',
            'Frecuencia Respiratoria' => '18',
        ]);

        $this->assertSame('98', $datos[EnfermeriaSignosVitalesRowContract::CANON_SAT_O2]);
        $this->assertSame('18', $datos[EnfermeriaSignosVitalesRowContract::CANON_FR]);
    }

    public function testNormalizeConvertsCommaDecimals(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'Temperatura' => '36,5',
            'Peso' => 70,
        ]);

        $this->assertSame('36.5', $datos[EnfermeriaSignosVitalesRowContract::CANON_TEMPERATURA]);
        $this->assertSame(70, $datos[EnfermeriaSignosVitalesRowContract::CANON_PESO]);
    }

    public function testNormalizeDropsUnknownKeysAndEmptyValues(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'Observaciones' => 'Paciente colaborador',
            'Glasgow' => '',
            'FC' => '88',
        ]);

        $this->assertSame(['fc' => '88'], $datos);
    }

    public function testNormalizeMergesListOfRows(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            ['Temperatura' => '37'],
            ['Glucemia' => '110'],
        ]);

        $this->assertSame('37', $datos[EnfermeriaSignosVitalesRowContract::CANON_TEMPERATURA]);
        $this->assertSame('110', $datos[EnfermeriaSignosVitalesRowContract::CANON_GLUCEMIA]);
    }

    public function testOneVitalSignIsEnough(): void
    {
        $assessment = EnfermeriaSignosVitalesRowContract::assess(['Temperatura' => '36.5']);

        $this->assertTrue($assessment->isComplete());
        $this->assertSame('Temperatura 36.5', $assessment->label());
    }

    public function testEmptyRowIsIncomplete(): void
    {
        $assessment = EnfermeriaSignosVitalesRowContract::assess(['Observaciones' => 'sin signos']);

        $this->assertFalse($assessment->isComplete());
        $this->assertSame(EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS, $assessment->missingFields());
    }

    public function testLabelUsesArterialPressureWhenPresent(): void
    {
        $assessment = EnfermeriaSignosVitalesRowContract::assess([
            'TA sistolica' => '120',
            'TA diastolica' => '80',
        ]);

        $this->assertTrue($assessment->isComplete());
        $this->assertSame('TA 120/80', $assessment->label());
    }

    public function testCompletenessNoLongerRequiresEveryVitalSign(): void
    {
        $svc = RowContractService::completenessValidator();

        $result = $svc->validate(
            ['Signos vitales' => [['Temperatura' => '36.5']]],
            [[
                'titulo' => 'Signos vitales',
                'modelo' => 'EncounterNursingCare',
                'requerido' => false,
                'campos_requeridos' => EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            ]]
        );

        $this->assertTrue($result['complete'], $result['message']);
    }
    public function testAnthropometryAliasesReachTheGrowthCurveKeys(): void
    {
        $datos = EnfermeriaSignosVitalesRowContract::normalize([
            'Perimetro cefalico' => '47',
            'PC' => '47',
            'Circunferencia abdominal' => '50,5',
        ]);

        $this->assertSame('47', $datos[EnfermeriaSignosVitalesRowContract::CANON_PERIMETRO_CEFALICO]);
        $this->assertSame('50.5', $datos[EnfermeriaSignosVitalesRowContract::CANON_CIRCUNFERENCIA_ABDOMINAL]);
    }

    public function testAnthropometryIsASubsetOfThePromptFields(): void
    {
        foreach (EnfermeriaSignosVitalesRowContract::ANTHROPOMETRY_FIELDS as $field) {
            $this->assertContains($field, EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS);
        }
    }

    public function testModelExposesTheSameFieldsAsTheContract(): void
    {
        $this->assertSame(
            EnfermeriaSignosVitalesRowContract::PROMPT_FIELDS,
            \common\models\Clinical\EncounterNursingCare::PROMPT_FIELDS
        );
    }

    public function testPediatricWorkflowIncludesTheGrowthStep(): void
    {
        $template = EncounterDefinitionWorkflowCatalog::TEMPLATE_AMB_PEDIATRICS;
        $steps = EncounterDefinitionWorkflowCatalog::templates()[$template];
        $models = array_column($steps, 'relacion');

        $this->assertContains('EncounterNursingCare', $models);
        $this->assertContains('EncounterReason', $models);
        $this->assertSame(
            $template,
            EncounterDefinitionWorkflowCatalog::templateForServiceName('PEDIATRIA', 'AMB')
        );
    }

    public function testStandardAmbulatoryDoesNotAddVitals(): void
    {
        $steps = EncounterDefinitionWorkflowCatalog::templates()[
            EncounterDefinitionWorkflowCatalog::TEMPLATE_AMB_STANDARD
        ];

        $this->assertNotContains('EncounterNursingCare', array_column($steps, 'relacion'));
    }
}
