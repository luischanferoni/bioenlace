<?php

namespace common\tests\unit\platform\ai;

use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;

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

    public function testRechazaResultadoConErrorDelMotor(): void
    {
        $r = DeviceSttQualityAssessor::assess(
            'Dolor de cabeza desde ayer.',
            ['confidence' => 0.95, 'error' => 'error_no_match'],
            'captura_clinica'
        );

        verify($r['ok'])->false();
        verify($r['reasons'])->contains('error_motor');
    }

    public function testAceptaTextoLocalDeBuenaCalidadConSttServidorHabilitado(): void
    {
        $quality = ['ok' => true, 'needs_server' => false, 'reasons' => []];

        verify(DeviceSttQualityAssessor::isDeviceTranscriptAccepted(
            'Paciente con dolor de garganta desde ayer.',
            $quality,
            true,
            true
        ))->true();
    }

    public function testSolicitaSttServidorParaTextoLocalDeBajaCalidad(): void
    {
        $quality = ['ok' => false, 'needs_server' => true, 'reasons' => ['confianza_baja']];

        verify(DeviceSttQualityAssessor::isDeviceTranscriptAccepted(
            'Paciente con dolor de garganta desde ayer.',
            $quality,
            true,
            true
        ))->false();
    }

    public function testConservaTextoLocalSiSttServidorEstaDeshabilitado(): void
    {
        $quality = ['ok' => false, 'needs_server' => true, 'reasons' => ['confianza_baja']];

        verify(DeviceSttQualityAssessor::isDeviceTranscriptAccepted(
            'Paciente con dolor de garganta desde ayer.',
            $quality,
            true,
            false
        ))->true();
    }

    public function testNoAceptaTextoSiDictadoEnDispositivoEstaDeshabilitado(): void
    {
        $quality = ['ok' => true, 'needs_server' => false, 'reasons' => []];

        verify(DeviceSttQualityAssessor::isDeviceTranscriptAccepted(
            'Paciente con dolor de garganta desde ayer.',
            $quality,
            false,
            false
        ))->false();
    }
}
