<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Specialty\Domain\EncounterDefinitionSpecialtyRegistry;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\CaptureCompletenessPolicy;
use common\models\Clinical\Input\OdontologiaItemInput;
use common\models\Clinical\Input\OftalmologiaEstudioInput;

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
        $svc = RowContractService::completenessValidator();
        $result = $svc->validate(
            [
                'Prácticas odontológicas' => [
                    ['Codigo' => 'Limpieza'],
                ],
            ],
            [
                [
                    'titulo' => 'Prácticas odontológicas',
                    'modelo' => 'EncounterDentalPractice',
                    'requerido' => false,
                    'campos_requeridos' => OdontologiaItemInput::promptFieldNames(),
                ],
            ]
        );
        $this->assertTrue($result['complete'], $result['message']);
    }

    public function testOdontologiaRowContractRecognizesAllDentalEncounterCategories(): void
    {
        $contract = new \common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract();

        $this->assertTrue($contract->matchesModelo('EncounterDentalPractice'));
        $this->assertTrue($contract->matchesModelo('EncounterDentalDiagnosis'));
        $this->assertTrue($contract->matchesModelo('EncounterDentalStatus'));
    }

    public function testCompletenessDiscoversOftalmologiaContractByModelName(): void
    {
        $svc = RowContractService::completenessValidator();
        $result = $svc->validate(
            [
                'Estudios oftalmológicos' => [
                    ['Codigo' => 'OCT', 'Informe' => null],
                ],
            ],
            [
                [
                    'titulo' => 'Estudios oftalmológicos',
                    'modelo' => 'OphthalmologyStudy',
                    'requerido' => false,
                    'campos_requeridos' => OftalmologiaEstudioInput::promptFieldNames(),
                ],
            ]
        );
        $this->assertTrue($result['complete'], $result['message']);
    }

    public function testOphthalmologyEncounterCategoriesResolveToSpecialty(): void
    {
        $registry = new EncounterDefinitionSpecialtyRegistry();

        $this->assertSame(
            EncounterDefinitionSpecialtyRegistry::SPECIALTY_OPHTHALMOLOGY,
            $registry->specialtyForModel('OphthalmologyPractice')
        );
        $this->assertSame(
            EncounterDefinitionSpecialtyRegistry::SPECIALTY_OPHTHALMOLOGY,
            $registry->specialtyForModel('OphthalmologyStudy')
        );
        $this->assertSame(
            EncounterDefinitionSpecialtyRegistry::SPECIALTY_OPHTHALMOLOGY,
            $registry->specialtyForModel('OphthalmologyLensPrescription')
        );
    }
}
