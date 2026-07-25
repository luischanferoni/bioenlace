<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCaptureCompletenessValidator;
use common\models\Clinical\Input\IndicacionInput;
use common\models\ConsultaIndicaciones;

class IndicacionInputTest extends Unit
{
    public function testCounselingWithoutPlazoIsValid(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'hidratación',
        ]);
        $this->assertSame(IndicacionInput::TYPE_COUNSELING, $input->tipo);
        $this->assertTrue($input->validate());
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testConditionalWithoutPlazoIsValid(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'control si empeora o aparece fiebre',
        ]);
        $this->assertSame(IndicacionInput::TYPE_CONDITIONAL, $input->tipo);
        $this->assertTrue($input->validate());
    }

    public function testFollowUpRequiresPlazo(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'control en consultorio',
            'Tipo' => 'follow_up',
        ]);
        $this->assertFalse($input->validate());
        $this->assertContains(
            IndicacionInput::FIELD_PLAZO_DIAS,
            $input->missingFieldsForCompleteness()
        );
    }

    public function testPlazoInfersFollowUpAndIsValid(): void
    {
        $input = IndicacionInput::fromExtractedRow([
            'Indicacion' => 'Control',
            'Plazo dias' => 15,
        ]);
        $this->assertSame(IndicacionInput::TYPE_FOLLOW_UP, $input->tipo);
        $this->assertSame(15, $input->plazoDias);
        $this->assertTrue($input->validate());
        $this->assertSame('follow-up', $input->categoryForServiceRequest());
    }

    public function testCompletenessAllowsViralIndicationsWithoutPlazo(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            [
                'Indicaciones' => [
                    ['Indicacion' => 'hidratación', 'Plazo dias' => null],
                    ['Indicacion' => 'analgesia si molesta', 'Plazo dias' => null],
                    ['Indicacion' => 'control si empeora o aparece fiebre', 'Plazo dias' => null],
                ],
            ],
            [
                [
                    'titulo' => 'Indicaciones',
                    'modelo' => 'ConsultaIndicaciones',
                    'requerido' => false,
                    'campos_requeridos' => (new ConsultaIndicaciones())->requeridosPrompt(),
                ],
            ]
        );

        $this->assertTrue($result['complete'], $result['message']);
        $this->assertSame([], $result['incomplete_items']);
    }

    public function testCompletenessBlocksFollowUpWithoutPlazo(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            [
                'Indicaciones' => [
                    [
                        'Indicacion' => 'control en consultorio',
                        'Tipo' => 'follow_up',
                    ],
                ],
            ],
            [
                [
                    'titulo' => 'Indicaciones',
                    'modelo' => 'ConsultaIndicaciones',
                    'requerido' => false,
                    'campos_requeridos' => (new ConsultaIndicaciones())->requeridosPrompt(),
                ],
            ]
        );

        $this->assertFalse($result['complete']);
        $this->assertContains('Plazo dias', $result['incomplete_items'][0]['missing_fields']);
    }
}
