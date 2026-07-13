<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaConfigImpactProfile;

class AgendaConfigImpactProfileTest extends Unit
{
    public function testModalityOnlySubmitSkipsConfirmation(): void
    {
        $post = [
            'acepta_consultas_online' => '1',
            'id_profesional_efector_servicio' => '42',
        ];

        $this->assertTrue(AgendaConfigImpactProfile::isModalityOnlySubmit($post));
        $this->assertFalse(AgendaConfigImpactProfile::previewRequiresUserConfirmation([
            'requiere_confirmacion' => true,
            'turnos_en_conflicto' => 5,
        ], $post));
    }

    public function testGridFieldChangeRequiresConfirmationWhenPreviewSaysSo(): void
    {
        $post = [
            'intervalo_minutos' => '30',
            'vigente_desde' => '2026-06-15',
        ];

        $this->assertFalse(AgendaConfigImpactProfile::isModalityOnlySubmit($post));
        $this->assertTrue(AgendaConfigImpactProfile::postTouchesGridFields($post));
        $this->assertTrue(AgendaConfigImpactProfile::previewRequiresUserConfirmation([
            'requiere_confirmacion' => true,
            'turnos_en_conflicto' => 0,
        ], $post));
    }

    public function testGridChangeWithoutConflictsMaySkipConfirmation(): void
    {
        $post = ['lunes_2' => '08:00-12:00'];

        $this->assertTrue(AgendaConfigImpactProfile::postTouchesGridFields($post));
        $this->assertFalse(AgendaConfigImpactProfile::previewRequiresUserConfirmation([
            'requiere_confirmacion' => false,
            'turnos_en_conflicto' => 0,
        ], $post));
    }

    public function testFilterPostForFieldsKeepsContextKeys(): void
    {
        $filtered = AgendaConfigImpactProfile::filterPostForFields([
            'acepta_consultas_online' => '1',
            'intervalo_minutos' => '30',
            'id_servicio' => '7',
            'extra' => 'x',
        ], ['acepta_consultas_online']);

        $this->assertSame([
            'acepta_consultas_online' => '1',
            'id_servicio' => '7',
        ], $filtered);
    }

    public function testEmptyDayKeyCountsAsGridTouch(): void
    {
        $this->assertTrue(AgendaConfigImpactProfile::postTouchesGridFields([
            'lunes_2' => '',
        ]));
    }

    public function testMergeClearsOmittedDaysWhenAnyDayPresent(): void
    {
        $defaults = [
            'intervalo_minutos' => '15',
            'lunes_2' => '8,9',
            'martes_2' => '10',
            'viernes_2' => '8',
            'sabado_2' => '10,11',
        ];
        $merged = AgendaConfigImpactProfile::mergePostWithAgendaDefaults([
            'viernes_2' => '10',
            'intervalo_minutos' => '15',
        ], $defaults);

        $this->assertSame('10', $merged['viernes_2']);
        $this->assertSame('', $merged['lunes_2']);
        $this->assertSame('', $merged['martes_2']);
        $this->assertSame('', $merged['sabado_2']);
        $this->assertSame('15', $merged['intervalo_minutos']);
    }

    public function testMergePreservesDaysWhenNoDayFieldInPost(): void
    {
        $defaults = [
            'lunes_2' => '8,9',
            'sabado_2' => '10,11',
            'formas_atencion' => 'TURNO',
        ];
        $merged = AgendaConfigImpactProfile::mergePostWithAgendaDefaults([
            'acepta_consultas_online' => '1',
        ], $defaults);

        $this->assertSame('8,9', $merged['lunes_2']);
        $this->assertSame('10,11', $merged['sabado_2']);
        $this->assertSame('1', $merged['acepta_consultas_online']);
    }
}
