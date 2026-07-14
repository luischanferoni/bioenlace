<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Text\EncounterCaptureExtractionPostProcessor;

class EncounterCaptureBackfillMotivosTest extends Unit
{
    public function testBackfillsMotivosWhenEmptyAndLexiconMatches(): void
    {
        $processor = new EncounterCaptureExtractionPostProcessor();
        $text = 'Cefalea tensional de una semana, sin signos de alarma. Diagnóstico: hipertensión arterial esencial.';
        $resultado = [
            'datosExtraidos' => [
                'Motivos de consulta' => [],
                'Diagnóstico' => ['hipertensión arterial esencial'],
            ],
        ];
        $categorias = [
            ['titulo' => 'Motivos de consulta', 'modelo' => 'ConsultaMotivos'],
            ['titulo' => 'Diagnóstico', 'modelo' => 'DiagnosticoConsulta'],
        ];

        $out = $processor->apply($resultado, $categorias, $text);
        $motivos = $out['datosExtraidos']['Motivos de consulta'] ?? [];
        $this->assertNotEmpty($motivos);
        $this->assertStringContainsStringIgnoringCase('cefalea', (string) $motivos[0]);
    }

    public function testDoesNotOverwriteExistingMotivos(): void
    {
        $processor = new EncounterCaptureExtractionPostProcessor();
        $text = 'Cefalea tensional. Diagnóstico: migraña.';
        $resultado = [
            'datosExtraidos' => [
                'Motivos de consulta' => ['cefalea tensional'],
            ],
        ];
        $categorias = [
            ['titulo' => 'Motivos de consulta', 'modelo' => 'ConsultaMotivos'],
        ];

        $out = $processor->apply($resultado, $categorias, $text);
        $this->assertSame(['cefalea tensional'], $out['datosExtraidos']['Motivos de consulta']);
    }
}
