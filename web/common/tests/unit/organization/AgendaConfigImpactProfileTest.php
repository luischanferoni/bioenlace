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
}
