<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory;
use common\components\Domain\Clinical\Capture\ClinicalCaptureResolutionApplier;
use common\models\Clinical\Input\IndicacionInput;
use common\models\ConsultaIndicaciones;

class ClinicalCaptureIssueResolutionTest extends Unit
{
    public function testFactoryMakeAndParse(): void
    {
        $issue = ClinicalCaptureIssueFactory::make(
            'Indicaciones',
            0,
            'Plazo dias',
            'Indique el plazo del control.',
            [
                ['value' => 7, 'label' => '7 días'],
            ],
            false
        );

        $this->assertSame('Indicaciones::0:Plazo dias', $issue['id']);
        $this->assertSame('Plazo dias', $issue['field']);
        $this->assertCount(1, $issue['options']);
        $this->assertFalse($issue['allow_custom']);

        $emptyOpts = ClinicalCaptureIssueFactory::make('X', 0, 'Campo', 'msg', [], false);
        $this->assertFalse($emptyOpts['allow_custom']);
        $this->assertSame([], $emptyOpts['options']);

        $parsed = ClinicalCaptureIssueFactory::parseIssueId($issue['id']);
        $this->assertNotNull($parsed);
        $this->assertSame('Indicaciones', $parsed['category']);
        $this->assertSame(0, $parsed['index']);
        $this->assertSame('Plazo dias', $parsed['field']);
    }

    public function testMedicacionCantidadIssueUsesCatalogChips(): void
    {
        $input = \common\models\Clinical\Input\MedicacionInput::fromExtractedRow([
            'Nombre del medicamento' => 'ibuprofeno',
            'Tipo' => 'ordered',
        ]);
        $issues = $input->buildIssues('Medicación', 0);
        $cantidad = null;
        foreach ($issues as $issue) {
            if (($issue['field'] ?? '') === \common\models\Clinical\Input\MedicacionInput::FIELD_CANTIDAD) {
                $cantidad = $issue;
                break;
            }
        }
        $this->assertNotNull($cantidad);
        $this->assertSame('Medicación::0:Cantidad', $cantidad['id']);
        $this->assertNotEmpty($cantidad['options']);
        $this->assertFalse($cantidad['allow_custom']);
        $this->assertSame('Elegí la cantidad / dosis.', $cantidad['message']);
    }

    public function testIndicacionFollowUpBuildsPlazoIssueWithoutSelection(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'control en consultorio',
            'Tipo' => 'follow_up',
        ]);
        $issues = $input->buildIssues('Indicaciones', 0);
        $this->assertNotEmpty($issues);
        $plazo = null;
        foreach ($issues as $issue) {
            if (($issue['field'] ?? '') === IndicacionInput::FIELD_PLAZO_DIAS) {
                $plazo = $issue;
                break;
            }
        }
        $this->assertNotNull($plazo);
        $this->assertSame('Indicaciones::0:Plazo dias', $plazo['id']);
        $this->assertNotEmpty($plazo['options']);
        $this->assertFalse($plazo['allow_custom']);
        foreach ($plazo['options'] as $opt) {
            $this->assertArrayHasKey('value', $opt);
            $this->assertArrayHasKey('label', $opt);
        }
    }

    public function testApplierWritesPlazoAndClearsIssue(): void
    {
        $categorias = [
            [
                'titulo' => 'Indicaciones',
                'modelo' => 'ConsultaIndicaciones',
                'requerido' => false,
                'campos_requeridos' => (new ConsultaIndicaciones())->requeridosPrompt(),
            ],
        ];
        $extraidos = [
            'Indicaciones' => [
                [
                    'Indicacion' => 'control en consultorio',
                    'Tipo' => 'follow_up',
                ],
            ],
        ];
        $updated = (new ClinicalCaptureResolutionApplier())->apply(
            $extraidos,
            ['Indicaciones::0:Plazo dias' => 7],
            $categorias
        );

        $this->assertSame(7, $updated['Indicaciones'][0]['Plazo dias']);

        $check = ConsultaIndicaciones::completenessForExtractedRow($updated['Indicaciones'][0]);
        $this->assertSame([], $check['missing_fields']);
    }

    public function testCounselingDoesNotEmitPlazoIssue(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'hidratación',
        ]);
        $this->assertSame([], $input->buildIssues('Indicaciones', 0));
    }
}
