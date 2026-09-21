<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Capture\Application\ClinicalCaptureRowContracts;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\EncounterCaptureCompletenessValidator;
use common\models\Clinical\Input\PracticaInput;

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
        $svc = ClinicalCaptureRowContracts::completenessValidator();
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
                    'campos_requeridos' => PracticaInput::promptFieldNames(),
                ],
            ]
        );

        $this->assertTrue($result['complete'], $result['message']);
    }
}
