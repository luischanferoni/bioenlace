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
}
