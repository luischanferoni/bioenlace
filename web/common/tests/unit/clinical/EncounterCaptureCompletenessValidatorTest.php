<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;
use common\components\Domain\Clinical\Workflow\EncounterCaptureCompletenessValidator;

class EncounterCaptureCompletenessValidatorTest extends Unit
{
    public function testCategoriaRequeridaVacia(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            ['Motivos de Consulta' => ['Dolor']],
            [
                ['titulo' => 'Diagnósticos', 'requerido' => true, 'campos_requeridos' => []],
                ['titulo' => 'Motivos de Consulta', 'requerido' => false, 'campos_requeridos' => []],
            ]
        );

        $this->assertFalse($result['complete']);
        $this->assertTrue($result['tiene_datos_faltantes']);
        $this->assertSame(['Diagnósticos'], $result['missing_categories']);
        $this->assertStringContainsString('Diagnósticos', $result['message']);
    }

    public function testMedicacionSinDosisNiFrecuencia(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $campos = [
            'Nombre del medicamento',
            'Cantidad',
            'Via de administracion',
            'Frecuencia de administracion',
            'Tipo de frecuencia',
            'Duracion del tratamiento',
            'Tipo de duracion',
        ];
        $result = $svc->validate(
            [
                'Medicación' => [
                    ['Nombre del medicamento' => 'Enalapril'],
                ],
            ],
            [
                [
                    'titulo' => 'Medicación',
                    'requerido' => false,
                    'campos_requeridos' => $campos,
                ],
            ]
        );

        $this->assertFalse($result['complete']);
        $this->assertCount(1, $result['incomplete_items']);
        $this->assertContains('Cantidad', $result['incomplete_items'][0]['missing_fields']);
        $this->assertContains('Frecuencia de administracion', $result['incomplete_items'][0]['missing_fields']);
    }

    public function testMedicacionCompleta(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $campos = [
            'Nombre del medicamento',
            'Cantidad',
            'Via de administracion',
            'Frecuencia de administracion',
            'Tipo de frecuencia',
            'Duracion del tratamiento',
            'Tipo de duracion',
        ];
        $row = [
            'Nombre del medicamento' => 'Enalapril',
            'Cantidad' => '10',
            'Via de administracion' => 'oral',
            'Frecuencia de administracion' => '1',
            'Tipo de frecuencia' => 'DIA',
            'Duracion del tratamiento' => '30',
            'Tipo de duracion' => 'DIA',
        ];
        $result = $svc->validate(
            ['Medicación' => [$row]],
            [
                [
                    'titulo' => 'Medicación',
                    'requerido' => true,
                    'campos_requeridos' => $campos,
                ],
            ]
        );

        $this->assertTrue($result['complete']);
        $this->assertFalse($result['tiene_datos_faltantes']);
        $this->assertSame('', $result['message']);
    }

    public function testStringSoloNoCumpleCamposMultiples(): void
    {
        $svc = new EncounterCaptureCompletenessValidator();
        $result = $svc->validate(
            ['Medicación' => ['Enalapril 10 mg']],
            [
                [
                    'titulo' => 'Medicación',
                    'requerido' => false,
                    'campos_requeridos' => [
                        'Nombre del medicamento',
                        'Cantidad',
                        'Frecuencia de administracion',
                    ],
                ],
            ]
        );

        $this->assertFalse($result['complete']);
        $this->assertContains('Cantidad', $result['incomplete_items'][0]['missing_fields']);
    }

    public function testPresenterNoPermiteConfirmarConFaltantes(): void
    {
        $presenter = new EncounterCaptureReviewPresenter();
        $review = $presenter->build(
            [
                'datosExtraidos' => [
                    'Medicación' => [
                        ['Nombre del medicamento' => 'Enalapril'],
                    ],
                ],
            ],
            [
                [
                    'titulo' => 'Medicación',
                    'requerido' => true,
                    'campos_requeridos' => [
                        'Nombre del medicamento',
                        'Cantidad',
                        'Frecuencia de administracion',
                    ],
                ],
            ],
            'Indico enalapril',
            'Indico enalapril',
            false
        );

        $this->assertTrue($review['tiene_datos_faltantes']);
        $this->assertFalse($review['puede_confirmar']);
        $this->assertNotEmpty($review['datos_faltantes_detalle']['incomplete_items'] ?? []);
    }
}
