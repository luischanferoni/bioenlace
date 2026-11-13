<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Specialty\Domain\EncounterDefinitionSpecialtyRegistry;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Capture\Domain\Policy\CaptureCompletenessPolicy;
use common\components\Domain\Clinical\Capture\Domain\RowContract\OdontologiaItemRowContract;
use common\models\Clinical\Input\OdontologiaItemInput;
use common\models\Clinical\Input\OftalmologiaEstudioInput;
use common\models\Clinical\Input\OftalmologiaPracticaInput;
use common\models\Clinical\Input\OftalmologiaRecetaInput;

class SpecialtyCaptureInputTest extends Unit
{
    public function testOdontologiaAcceptsCodigoAlone(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow(['Codigo' => 'Obturación']);
        $this->assertTrue($input->validate());
    }

    public function testOdontologiaMapsDentalFieldsFromPromptKeys(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow([
            'Codigo' => 'Obturación',
            'Pieza' => '16',
            'Caras' => 'M',
            'Tiempo' => 'futura',
        ]);

        $this->assertTrue($input->validate(), implode(' ', $input->getFirstErrors()));
        $this->assertSame('16', $input->pieza);
        $this->assertSame('M', $input->caras);
        $this->assertSame('FUTURA', $input->tiempo);
    }

    public function testOdontologiaDiscardsUnknownTimeQualifier(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow(['Codigo' => 'Limpieza', 'Tiempo' => 'ayer']);

        $this->assertNull($input->tiempo);
        $this->assertTrue($input->validate());
    }

    public function testOdontologiaStillRequiresTipoOrCodigoWhenOnlyDentalFieldsCome(): void
    {
        $input = OdontologiaItemInput::fromExtractedRow(['Pieza' => '16']);

        $this->assertFalse($input->validate());
        $this->assertSame(
            [OdontologiaItemRowContract::FIELD_TIPO, OdontologiaItemRowContract::FIELD_CODIGO],
            $input->missingFieldsForCompleteness()
        );
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

    public function testOftalmologiaPracticaMapsPromptFields(): void
    {
        $input = OftalmologiaPracticaInput::fromExtractedRow([
            'Codigo' => '252100006',
            'Ojo' => 'od',
            'Prueba' => 'Agudeza visual',
            'Estado' => 'realizada',
            'Informe' => 'AV 10/10',
            'Tipo' => 'control',
        ]);

        $this->assertTrue($input->validate(), implode(' ', $input->getFirstErrors()));
        $this->assertSame('252100006', $input->codigo);
        $this->assertSame('OD', $input->ojo);
        $this->assertSame('Agudeza visual', $input->prueba);
        $this->assertSame('realizada', $input->estado);
        $this->assertSame('AV 10/10', $input->informe);
        $this->assertSame('control', $input->tipo);
    }

    public function testOftalmologiaPracticaNeedsCodigoOrPrueba(): void
    {
        $this->assertTrue(OftalmologiaPracticaInput::fromExtractedRow(['Prueba' => 'Biometría'])->validate());

        $empty = OftalmologiaPracticaInput::fromExtractedRow(['Ojo' => 'OI']);
        $this->assertFalse($empty->validate());
        $this->assertSame(
            [
                \common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract::FIELD_CODIGO,
                \common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract::FIELD_PRUEBA,
            ],
            $empty->missingFieldsForCompleteness()
        );
    }

    public function testOftalmologiaPracticaDiscardsInvalidEye(): void
    {
        $input = OftalmologiaPracticaInput::fromExtractedRow([
            'Codigo' => '252100006',
            'Ojo' => 'derecho',
        ]);

        $this->assertNull($input->ojo);
        $this->assertTrue($input->validate());
    }

    public function testOftalmologiaRecetaMapsBothEyesFromPromptKeys(): void
    {
        $input = OftalmologiaRecetaInput::fromExtractedRow([
            'OD esfera' => '-1.25',
            'OD cilindro' => '-0.50',
            'OD eje' => '90',
            'OD adicion' => '+1.00',
            'OI esfera' => '-0.75',
            'OI eje' => '80',
        ]);

        $this->assertTrue($input->validate());
        $this->assertSame('-1.25', $input->odEsfera);
        $this->assertSame('-0.50', $input->odCilindro);
        $this->assertSame('90', $input->odEje);
        $this->assertSame('+1.00', $input->odAdicion);
        $this->assertSame('-0.75', $input->oiEsfera);
        $this->assertNull($input->oiCilindro);
        $this->assertSame('80', $input->oiEje);
        $this->assertSame([], $input->missingFieldsForCompleteness());
    }

    public function testOftalmologiaRecetaReadsLegacySnakeCaseKeys(): void
    {
        $input = OftalmologiaRecetaInput::fromExtractedRow([
            'od_esfera' => '-2.00',
            'oi_add' => '+2.50',
        ]);

        $this->assertSame('-2.00', $input->odEsfera);
        $this->assertSame('+2.50', $input->oiAdicion);
    }

    public function testOftalmologiaRecetaWithoutValuesIsIncomplete(): void
    {
        $input = OftalmologiaRecetaInput::fromExtractedRow([]);

        $this->assertSame(
            OftalmologiaRecetaInput::promptFieldNames(),
            $input->missingFieldsForCompleteness()
        );
    }

    public function testCompletenessUsesOphthalmologyContracts(): void
    {
        $svc = RowContractService::completenessValidator();

        $practice = $svc->validate(
            ['Prácticas oftalmológicas' => [['Ojo' => 'OD']]],
            [[
                'titulo' => 'Prácticas oftalmológicas',
                'modelo' => 'OphthalmologyPractice',
                'requerido' => false,
                'campos_requeridos' => OftalmologiaPracticaInput::promptFieldNames(),
            ]]
        );
        $this->assertFalse($practice['complete']);
        $this->assertSame(
            ['Codigo', 'Prueba'],
            $practice['incomplete_items'][0]['missing_fields']
        );

        $prescription = $svc->validate(
            ['Receta de lentes' => [['OD esfera' => '-1.00']]],
            [[
                'titulo' => 'Receta de lentes',
                'modelo' => 'OphthalmologyLensPrescription',
                'requerido' => false,
                'campos_requeridos' => OftalmologiaRecetaInput::promptFieldNames(),
            ]]
        );
        $this->assertTrue($prescription['complete'], $prescription['message']);
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

    public function testSpecialtyMetadataDescribesSectionsFieldsAndUnits(): void
    {
        $registry = new EncounterDefinitionSpecialtyRegistry();
        $metadata = $registry->metadataForSpecialties([
            EncounterDefinitionSpecialtyRegistry::SPECIALTY_ODONTOLOGY,
            EncounterDefinitionSpecialtyRegistry::SPECIALTY_OPHTHALMOLOGY,
            'unknown',
        ]);
        $byCode = [];
        foreach ($metadata as $specialty) {
            $byCode[$specialty['code']] = $specialty;
        }

        $this->assertSame(
            ['EncounterDentalPractice', 'EncounterDentalDiagnosis', 'EncounterDentalStatus'],
            array_column($byCode['odontology']['sections'], 'key')
        );
        $this->assertSame([1, 2, 3], array_column($byCode['odontology']['sections'], 'order'));
        $promptFields = OdontologiaItemInput::promptFieldNames();
        $this->assertSame(
            [OdontologiaItemRowContract::FIELD_PIEZA, OdontologiaItemRowContract::FIELD_CARAS, OdontologiaItemRowContract::FIELD_TIEMPO],
            array_slice($promptFields, 2)
        );
        foreach ($byCode['odontology']['sections'] as $section) {
            $this->assertSame($promptFields, array_column($section['fields'], 'key'));
            $this->assertSame(range(1, count($promptFields)), array_column($section['fields'], 'order'));
            $this->assertSame(
                [
                    'type' => 'at_least_one',
                    'fields' => ['Tipo', 'Codigo'],
                ],
                $section['validation']
            );
        }
        $this->assertSame(
            OftalmologiaPracticaInput::promptFieldNames(),
            array_column($byCode['ophthalmology']['sections'][0]['fields'], 'key')
        );
        $this->assertSame([1, 2, 3], array_column($byCode['ophthalmology']['sections'], 'order'));
        $this->assertSame(
            range(1, count(OftalmologiaPracticaInput::promptFieldNames())),
            array_column($byCode['ophthalmology']['sections'][0]['fields'], 'order')
        );
        $this->assertSame(
            [
                'type' => 'at_least_one',
                'fields' => ['Codigo', 'Informe'],
            ],
            $byCode['ophthalmology']['sections'][1]['validation']
        );

        $prescription = $byCode['ophthalmology']['sections'][2];
        $fieldsByKey = [];
        foreach ($prescription['fields'] as $field) {
            $fieldsByKey[$field['key']] = $field;
        }
        $this->assertSame(
            OftalmologiaRecetaInput::promptFieldNames(),
            array_column($prescription['fields'], 'key')
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_column($prescription['fields'], 'order'));
        $this->assertSame('decimal', $fieldsByKey['OD esfera']['format']);
        $this->assertSame('D', $fieldsByKey['OD esfera']['unit']);
        $this->assertSame('integer', $fieldsByKey['OD eje']['format']);
        $this->assertSame('°', $fieldsByKey['OD eje']['unit']);
        $this->assertNotContains('unknown', array_column($metadata, 'code'));
    }
}
