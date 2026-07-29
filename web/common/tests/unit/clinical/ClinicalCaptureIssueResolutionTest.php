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
            true
        );

        $this->assertSame('Indicaciones::0:Plazo dias', $issue['id']);
        $this->assertSame('Plazo dias', $issue['field']);
        $this->assertCount(1, $issue['options']);
        $this->assertTrue($issue['allow_custom']);

        $parsed = ClinicalCaptureIssueFactory::parseIssueId($issue['id']);
        $this->assertNotNull($parsed);
        $this->assertSame('Indicaciones', $parsed['category']);
        $this->assertSame(0, $parsed['index']);
        $this->assertSame('Plazo dias', $parsed['field']);
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
