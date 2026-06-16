<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\IntentFamilyCatalog;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\IntentSubmitFieldFilter;

class IntentFamilyResolutionTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
        IntentFamilyCatalog::resetCache();
    }

    public function testCondicionLaboralFamilyLoadsPilotMembers(): void
    {
        $family = IntentFamilyCatalog::get('condicion-laboral.edit');
        $this->assertNotNull($family);
        $this->assertContains('condicion-laboral.editar-propio', $family['members']);
        $this->assertContains('condicion-laboral.editar-staff', $family['members']);
    }

    public function testSubmitFieldFilterStripsUnknownKeys(): void
    {
        $filtered = (new IntentSubmitFieldFilter())->filter('condicion-laboral.editar-propio', [
            'id_profesional_efector_servicio' => 10,
            'id_condicion_laboral' => 2,
            'fecha_inicio' => '2026-01-01',
            'nombre' => 'hack',
        ]);

        $this->assertArrayHasKey('id_condicion_laboral', $filtered);
        $this->assertArrayHasKey('fecha_inicio', $filtered);
        $this->assertArrayNotHasKey('nombre', $filtered);
    }

    public function testAllowedFieldNamesFromPilotManifest(): void
    {
        $names = (new IntentSubmitFieldFilter())->allowedFieldNames('condicion-laboral.editar-staff');
        $this->assertContains('id_condicion_laboral', $names);
        $this->assertContains('fecha_inicio', $names);
        $this->assertContains('fecha_fin', $names);
    }
}
