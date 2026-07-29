<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Presentation\EncounterCaptureReviewPresenter;

class EncounterCaptureReviewPresenterTest extends Unit
{
    public function testBuildCaptureReviewFromCategoriasAndExtraidos(): void
    {
        $presenter = new EncounterCaptureReviewPresenter();
        $review = $presenter->build(
            [
                'datosExtraidos' => [
                    'Diagnósticos' => [
                        [
                            'termino' => 'Hipertensión arterial',
                            'codigo_cie10' => 'I10',
                        ],
                    ],
                    'Motivos de Consulta' => ['Dolor torácico'],
                ],
            ],
            [
                ['titulo' => 'Diagnósticos', 'requerido' => true],
                ['titulo' => 'Motivos de Consulta', 'requerido' => false],
            ],
            'Paciente con dolor torácico',
            'Paciente con dolor toracico',
            false
        );

        $this->assertSame(1, $review['version']);
        $this->assertSame('Paciente con dolor torácico', $review['texto_original']);
        $this->assertFalse($review['tiene_datos_faltantes']);
        $this->assertNull($review['system_error']);
        $this->assertCount(2, $review['categories']);

        $dx = $review['categories'][0];
        $this->assertSame('Diagnósticos', $dx['title']);
        $this->assertTrue($dx['required']);
        $this->assertSame('Diagnósticos::0', $dx['items'][0]['id']);
        $this->assertSame('Hipertensión arterial', $dx['items'][0]['label']);
        $this->assertSame('I10', $dx['items'][0]['subtitle']);

        $this->assertSame(
            ['Diagnósticos::0', 'Motivos de Consulta::0'],
            $review['default_staged_item_ids']
        );
        $this->assertTrue($review['puede_confirmar']);
    }

    public function testBuildCaptureReviewWithSystemError(): void
    {
        $presenter = new EncounterCaptureReviewPresenter();
        $review = $presenter->build(
            [
                'datosExtraidos' => [
                    'Error' => [
                        'texto' => 'Fallo IA',
                        'detalle' => 'Reintentar',
                        'tipo' => 'error_ia',
                    ],
                ],
            ],
            [],
            'texto',
            null,
            true
        );

        $this->assertFalse($review['puede_confirmar']);
        $this->assertNotNull($review['system_error']);
        $this->assertSame('error_ia', $review['system_error']['tipo']);
        $this->assertSame([], $review['categories']);
    }

    public function testDefaultStagedExcludesAiSourcedItems(): void
    {
        $presenter = new EncounterCaptureReviewPresenter();
        $review = $presenter->build(
            [
                'datosExtraidos' => [
                    'Medicación' => [
                        [
                            'Nombre del medicamento' => 'enalapril',
                            'Cantidad' => '10 mg',
                        ],
                    ],
                    'Diagnóstico' => ['hipertensión arterial esencial'],
                ],
            ],
            [
                [
                    'titulo' => 'Medicación',
                    'modelo' => 'ConsultaMedicamentos',
                    'requerido' => false,
                    'campos_requeridos' => ['Nombre del medicamento', 'Cantidad'],
                ],
                [
                    'titulo' => 'Diagnóstico',
                    'modelo' => 'DiagnosticoConsulta',
                    'requerido' => false,
                    'campos_requeridos' => [],
                ],
            ],
            // Haystack sin el fármaco → Medicación queda source=ai: sugerencia sin tildar.
            'Consulta por cefalea. Diagnóstico: hipertensión arterial esencial.',
            null,
            false
        );

        $this->assertNotContains('Medicación::0', $review['default_staged_item_ids']);
        $this->assertContains('Diagnóstico::0', $review['default_staged_item_ids']);
    }

    public function testSubtitleOmiteTipoInternoFollowUpYOrdered(): void
    {
        $presenter = new EncounterCaptureReviewPresenter();
        $review = $presenter->build(
            [
                'datosExtraidos' => [
                    'Indicaciones' => [
                        [
                            'Indicacion' => 'Control en consultorio',
                            'Tipo' => 'follow_up',
                            'Plazo dias' => 7,
                        ],
                    ],
                    'Medicación' => [
                        [
                            'Nombre del medicamento' => 'Enalapril',
                            'Tipo' => 'ordered',
                            'Cantidad' => '10 mg',
                            'Frecuencia de administracion' => '1 vez al día',
                        ],
                    ],
                ],
            ],
            [
                [
                    'titulo' => 'Indicaciones',
                    'modelo' => 'ConsultaIndicaciones',
                    'requerido' => false,
                    'campos_requeridos' => ['Indicacion', 'Tipo', 'Plazo dias'],
                ],
                [
                    'titulo' => 'Medicación',
                    'modelo' => 'ConsultaMedicamentos',
                    'requerido' => false,
                    'campos_requeridos' => [
                        'Nombre del medicamento',
                        'Tipo',
                        'Cantidad',
                        'Frecuencia de administracion',
                    ],
                ],
            ],
            'Control en consultorio en 7 días. Indico enalapril 10 mg 1 vez al día.',
            null,
            false
        );

        $ind = $review['categories'][0]['items'][0];
        $this->assertSame('Control en consultorio', $ind['label']);
        $this->assertSame('7 días', $ind['subtitle']);
        $this->assertStringNotContainsString('follow_up', (string) ($ind['subtitle'] ?? ''));

        $med = $review['categories'][1]['items'][0];
        $this->assertSame('Enalapril', $med['label']);
        $this->assertSame('10 mg · 1 vez al día', $med['subtitle']);
        $this->assertStringNotContainsString('ordered', (string) ($med['subtitle'] ?? ''));
    }
}
