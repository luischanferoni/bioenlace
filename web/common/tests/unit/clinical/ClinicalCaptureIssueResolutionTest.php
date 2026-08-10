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
            [
                ['value' => 7, 'label' => '7 días'],
            ],
            false
        );

        $this->assertSame('Indicaciones::0:Plazo dias', $issue['id']);
        $this->assertSame('Plazo dias', $issue['field']);
        $this->assertArrayNotHasKey('message', $issue);
        $this->assertCount(1, $issue['options']);
        $this->assertFalse($issue['allow_custom']);

        $emptyOpts = ClinicalCaptureIssueFactory::make('X', 0, 'Campo', [], false);
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
        $this->assertArrayNotHasKey('message', $cantidad);
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

    public function testFilterByStagedKeepsResolvedRowByOriginalIndex(): void
    {
        $applier = new ClinicalCaptureResolutionApplier();
        $full = [
            'Medicación' => [
                [
                    'Nombre del medicamento' => 'antibiótico',
                    'Tipo' => 'ordered',
                    'Via de administracion' => 'EV',
                ],
                [
                    'Nombre del medicamento' => 'antibiótico',
                    'Tipo' => 'ordered',
                    'Via de administracion' => 'oral',
                ],
            ],
        ];
        $resolved = $applier->apply($full, [
            'Medicación::1:Cantidad' => '1 comprimido',
            'Medicación::1:Frecuencia de administracion' => '4',
        ], []);
        $filtered = $applier->filterByStagedItemIds($resolved, ['Medicación::1'], []);

        $this->assertCount(1, $filtered['Medicación']);
        $this->assertSame('oral', $filtered['Medicación'][0]['Via de administracion']);
        $this->assertSame('1 comprimido', $filtered['Medicación'][0]['Cantidad']);
        $this->assertSame('4', $filtered['Medicación'][0]['Frecuencia de administracion']);
    }

    public function testRemapCompletenessUsesOriginalStagedIndex(): void
    {
        $applier = new ClinicalCaptureResolutionApplier();
        $map = $applier->stagedIndexMap(['Medicación::1', 'Balance hídrico::0']);
        $this->assertSame(1, $map['Medicación'][0]);
        $this->assertSame(0, $map['Balance hídrico'][0]);

        $completeness = [
            'incomplete_items' => [
                [
                    'category' => 'Medicación',
                    'index' => 0,
                    'label' => 'antibiótico',
                    'missing_fields' => ['Cantidad'],
                ],
            ],
            'issues' => [
                [
                    'id' => 'Medicación::0:Cantidad',
                    'field' => 'Cantidad',
                    'options' => [],
                    'allow_custom' => false,
                ],
            ],
            'message' => 'x',
        ];
        $out = $applier->remapCompletenessToOriginalIndices($completeness, $map);
        $this->assertSame(1, $out['incomplete_items'][0]['index']);
        $this->assertSame('Medicación::1:Cantidad', $out['issues'][0]['id']);
    }

    public function testBalanceCantidadBuildsAllowCustomIssue(): void
    {
        $input = \common\models\Clinical\Input\BalanceHidricoInput::fromExtractedRow([
            'Tipo Registro' => 'Ingreso',
        ]);
        $issues = $input->buildIssues('Balance hídrico', 0);
        $cantidad = null;
        foreach ($issues as $issue) {
            if (($issue['field'] ?? '') === \common\models\Clinical\Input\BalanceHidricoInput::FIELD_CANTIDAD) {
                $cantidad = $issue;
                break;
            }
        }
        $this->assertNotNull($cantidad);
        $this->assertTrue($cantidad['allow_custom']);
        $this->assertNotEmpty($cantidad['options']);
    }

    public function testCounselingDoesNotEmitPlazoIssue(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'hidratación',
        ]);
        $this->assertSame([], $input->buildIssues('Indicaciones', 0));
    }
}
