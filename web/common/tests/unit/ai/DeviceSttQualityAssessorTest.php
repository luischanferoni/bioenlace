<?php

namespace common\tests\unit\ai;

use common\components\Ai\SpeechToText\DeviceSttQualityAssessor;

class DeviceSttQualityAssessorTest extends \Codeception\Test\Unit
{
    public function testAceptaTextoClinicoRazonable(): void
    {
        $r = DeviceSttQualityAssessor::assess(
            'Paciente con hipertensión controlada, ajusto enalapril.',
            ['confidence' => 0.9, 'duration_ms' => 8000, 'locale' => 'es-AR'],
            'captura_clinica'
        );
        verify($r['ok'])->true();
        verify($r['needs_server'])->false();
    }

    public function testRechazaTextoMuyCorto(): void
    {
        $r = DeviceSttQualityAssessor::assess('ok', ['confidence' => 0.95], 'captura_clinica');
        verify($r['ok'])->false();
        verify($r['reasons'])->contains('texto_muy_corto');
    }

    public function testRechazaConfianzaBajaEnCaptura(): void
    {
        $r = DeviceSttQualityAssessor::assess(
            'Dolor torácico opresivo desde ayer sin irradiación.',
            ['confidence' => 0.5, 'duration_ms' => 10000, 'locale' => 'es-AR'],
            'captura_clinica'
        );
        verify($r['ok'])->false();
        verify($r['reasons'])->contains('confianza_baja');
    }
}
