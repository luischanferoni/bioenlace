<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Clinical\AiContext\PatientAiContextBuilder;

class PatientAiContextBuilderTest extends \Codeception\Test\Unit
{
    public function testFormatBlockIncluyeDemografiaYListas()
    {
        $data = [
            'demographics' => ['edad' => 42, 'sexo' => 'Femenino'],
            'allergies' => ['Penicilina, allergy, criticidad high'],
            'conditions' => ['Hipertensión arterial (crónica)', 'Asma'],
            'medications' => ['Losartán 50 mg — 1 comp/día'],
        ];

        $block = PatientAiContextBuilder::formatBlock(
            $data,
            PatientAiContextBuilder::PROFILE_ENCOUNTER,
            2400
        );

        verify($block)->stringContainsString('Edad: 42 años');
        verify($block)->stringContainsString('Penicilina');
        verify($block)->stringContainsString('Hipertensión arterial');
        verify($block)->stringContainsString('Losartán');
    }

    public function testFormatBlockRecortaPorMaxChars()
    {
        $long = str_repeat('Medicamento X, ', 200);
        $data = [
            'demographics' => ['edad' => 30, 'sexo' => 'Masculino'],
            'allergies' => [],
            'conditions' => [],
            'medications' => [rtrim($long, ', ')],
        ];

        $block = PatientAiContextBuilder::formatBlock(
            $data,
            PatientAiContextBuilder::PROFILE_ENCOUNTER,
            300
        );

        verify(strlen($block))->lessOrEquals(300);
        verify(substr($block, -3))->equals('…');
    }

    public function testFormatBlockSinDatosMuestraPlaceholders()
    {
        $block = PatientAiContextBuilder::formatBlock(
            ['demographics' => ['edad' => 10, 'sexo' => 'Femenino']],
            PatientAiContextBuilder::PROFILE_MOTIVOS,
            2400
        );

        verify($block)->stringContainsString('Sin alergias registradas.');
        verify($block)->stringContainsString('Sin condiciones activas registradas.');
    }

    public function testFormatBlockIncluyeEvolucionesPreviasDelEpisodio()
    {
        $block = PatientAiContextBuilder::formatBlock(
            [
                'demographics' => ['edad' => 50, 'sexo' => 'Masculino'],
                'allergies' => [],
                'conditions' => [],
                'medications' => [],
                'prior_evolutions' => [
                    '2026-08-09 — Primer día: neumonía, Sat 94 %.',
                ],
            ],
            PatientAiContextBuilder::PROFILE_ENCOUNTER,
            2400
        );

        verify($block)->stringContainsString('Evoluciones previas del episodio');
        verify($block)->stringContainsString('priorizar cambios clínicos');
        verify($block)->stringContainsString('Primer día: neumonía');
    }

    public function testFormatBlockIncluyePlanDeCuidadoInpatient()
    {
        $block = PatientAiContextBuilder::formatBlock(
            [
                'demographics' => ['edad' => 50, 'sexo' => 'Masculino'],
                'allergies' => [],
                'conditions' => [],
                'medications' => [],
                'care_plan' => [
                    'Paracetamol 1 g — cada 8 h',
                ],
            ],
            PatientAiContextBuilder::PROFILE_ENCOUNTER,
            2400
        );

        verify($block)->stringContainsString('Plan de cuidado indicado');
        verify($block)->stringContainsString('Paracetamol 1 g');
    }
}
