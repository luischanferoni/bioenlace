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

    public function testPacienteOnlyFlowByRepresentationIntentId(): void
    {
        $this->assertTrue(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'personas.vincular-menor-flow',
            'rbac_route' => '/api/person-representation/solicitar-menor-como-tutor',
        ]));
        $this->assertFalse(ClientContextMetadata::isPacienteOnlyFlow([
            'action_id' => 'personas.verificar-tutela-staff-flow',
            'rbac_route' => '/api/person-representation/verificar-vinculo-para-staff',
        ]));
    }

    public function testPacienteMobileClientDetection(): void
    {
        $this->assertTrue(ClientContextMetadata::isPacienteMobileClient('paciente-flutter'));
        $this->assertFalse(ClientContextMetadata::isPacienteMobileClient('bioenlace-personalsalud'));
        $this->assertFalse(ClientContextMetadata::isPacienteMobileClient('whatsapp-paciente'));
    }

    public function testPacienteFacingAppClientIncludesWhatsapp(): void
    {
        $this->assertTrue(ClientContextMetadata::isPacienteFacingAppClient('paciente-flutter'));
        $this->assertTrue(ClientContextMetadata::isPacienteFacingAppClient('whatsapp-paciente'));
        $this->assertFalse(ClientContextMetadata::isPacienteFacingAppClient('web-frontend'));
        $this->assertFalse(ClientContextMetadata::isPacienteFacingAppClient('bioenlace-personalsalud'));
    }

    public function testPacienteMobileShortcutDisplayFlags(): void
    {
        $this->assertFalse(ClientContextMetadata::pacienteMobileShortcutUseYamlActionName());
        $this->assertTrue(ClientContextMetadata::pacienteMobileShortcutOmitSubgroups());
        $this->assertSame(
            'assistant-shortcuts-paciente.yaml',
            ClientContextMetadata::pacienteMobileShortcutsCatalogBasename()
        );
    }

    public function testWhatsappPacienteShortcutsFromMetadata(): void
    {
        $display = ClientContextMetadata::shortcutsDisplayForAppClient('whatsapp-paciente');
        $this->assertNotNull($display);
        $this->assertSame('assistant-shortcuts-whatsapp-paciente.yaml', $display['catalog_basename']);
        $this->assertTrue($display['omit_subgroups']);
        $this->assertNull(ClientContextMetadata::shortcutsDisplayForAppClient('web-frontend'));
    }
}
