<?php

namespace common\tests\unit\core;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\ClientContextMetadata;

class ClientContextMetadataTest extends Unit
{
    protected function _after(): void
    {
        ClientContextMetadata::resetCacheForTests();
    }

    public function testPacienteOnlyFlowByIntentId(): void
    {
        $this->assertTrue(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'atencion.necesito-atencion',
            'rbac_route' => '/api/test',
        ]));
    }

    public function testPacienteOnlyFlowBySubstring(): void
    {
        $this->assertTrue(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'turnos.crear-como-paciente',
            'rbac_route' => '/api/turnos/crear',
        ]));
        $this->assertFalse(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'turnos.crear-sobreturno-flow',
            'rbac_route' => '/api/turnos/crear-sobreturno',
        ]));
        $this->assertTrue(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'turnos.crear-para-paciente-flow',
            'rbac_route' => '/api/turnos/crear-para-paciente',
        ]));
    }

    public function testPacienteNotificationTiposFromMetadata(): void
    {
        $tipos = ClientContextMetadata::pacienteNotificacionTipos();
        $this->assertContains('TURNO_RECORDATORIO', $tipos);
        $this->assertNotContains('STAFF_ALERT', $tipos);
    }
}
