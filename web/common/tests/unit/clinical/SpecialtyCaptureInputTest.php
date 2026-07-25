<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Workflow\EncounterCaptureCompletenessValidator;
use common\models\Clinical\Input\OdontologiaItemInput;
use common\models\Clinical\Input\OftalmologiaEstudioInput;
use common\models\ConsultaOdontologiaPracticas;
use common\models\ConsultaPracticasOftalmologiaEstudios;

class SpecialtyCaptureInputTest extends Unit
{
    public function testOdontologiaAcceptsCodigoAlone(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow(['Codigo' => 'Obturación']);
        $this->assertTrue($input->validate());
    }

    public function testOdontologiaRejectsEmpty(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow([]);
        $this->assertFalse($input->validate());
    }

    public function testOftalmologiaAcceptsInformeWithoutOjo(): void
    {
        $input = OftalmologiaEstudioInput::fromExtractedRow([
            'Informe' => 'Fondo de ojo sin alteraciones',
        ]);
        $this->assertTrue($input->validate());
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testCompletenessDiscoversOdontologiaContractByModelName(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            [
                'Prácticas odontológicas' => [
                    ['Codigo' => 'Limpieza'],
                ],
            ],
            [
                [
                    'titulo' => 'Prácticas odontológicas',
                    'modelo' => 'ConsultaOdontologiaPracticas',
                    'requerido' => false,
                    'campos_requeridos' => (new ConsultaOdontologiaPracticas())->requeridosPrompt(),
                ],
            ]
        );
        $this->assertTrue($result['complete'], $result['message']);
    }

    public function testCompletenessDiscoversOftalmologiaContractByModelName(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            [
                'Estudios oftalmológicos' => [
                    ['Codigo' => 'OCT', 'Informe' => null],
                ],
            ],
            [
                [
                    'titulo' => 'Estudios oftalmológicos',
                    'modelo' => 'ConsultaPracticasOftalmologiaEstudios',
                    'requerido' => false,
                    'campos_requeridos' => (new ConsultaPracticasOftalmologiaEstudios())->requeridosPrompt(),
                ],
            ]
        );
        $this->assertTrue($result['complete'], $result['message']);
    }
}
