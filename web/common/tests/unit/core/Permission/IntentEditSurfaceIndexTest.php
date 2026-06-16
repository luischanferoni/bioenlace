<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\IntentEditSurfaceIndex;
use common\components\Platform\Core\Permission\IntentManifestIndex;

class IntentEditSurfaceIndexTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        IntentEditSurfaceIndex::resetCache();
    }

    public function testBindsAgendaAndIdentidadSurfaces(): void
    {
        $agendaIntents = IntentEditSurfaceIndex::intentsForSurface('ProfesionalEfectorServicioAgenda');
        $this->assertContains('profesional-agenda.configurar-propio', $agendaIntents);
        $this->assertContains('profesional-agenda.configurar-staff', $agendaIntents);

        $this->assertSame(
            'profesional-identidad.editar-staff',
            IntentEditSurfaceIndex::intentsForSurface('ProfesionalEfectorServicio')[0] ?? null
        );
        $this->assertTrue(IntentEditSurfaceIndex::isSurfaceMigrated('ProfesionalEfectorServicioAgenda'));
    }
}
