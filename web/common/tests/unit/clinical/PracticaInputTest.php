<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCaptureCompletenessValidator;
use common\models\Clinical\Input\PracticaInput;
use common\models\ConsultaPracticas;

class PracticaInputTest extends Unit
{
    public function testPracticaAloneIsValid(): void
    {
        $input = PracticaInput::fromExtractedRow(['Practica' => 'ECG']);
        $this->assertTrue($input->validate());
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testEmptyPracticaIsIncomplete(): void
    {
        $input = PracticaInput::fromExtractedRow(['Resultado' => '120/80']);
        $this->assertFalse($input->validate());
        $this->assertContains(PracticaInput::FIELD_PRACTICA, $input->missingFieldsForCompleteness());
    }

    public function testCompletenessAllowsPracticaWithoutCodigo(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            [
                'Prácticas realizadas' => [
                    ['Practica' => 'Auscultación', 'Resultado' => null, 'Codigo' => null],
                ],
            ],
            [
                [
                    'titulo' => 'Prácticas realizadas',
                    'modelo' => 'ConsultaPracticas',
                    'requerido' => false,
                    'campos_requeridos' => (new ConsultaPracticas())->requeridosPrompt(),
                ],
            ]
        );

        $this->assertTrue($result['complete'], $result['message']);
    }
}
