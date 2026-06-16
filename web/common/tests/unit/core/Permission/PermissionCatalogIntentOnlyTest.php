<?php

namespace common\tests\unit\core\Permission;

use Codeception\Test\Unit;
use common\components\Platform\Core\Permission\CatalogPermissionSyncService;
use common\components\Platform\Core\Permission\IntentManifestIndex;
use common\components\Platform\Core\Permission\PermissionCatalogService;

class PermissionCatalogIntentOnlyTest extends Unit
{
    protected function _before(): void
    {
        IntentManifestIndex::resetCache();
    }

    public function testFindPermissionRowRejectsLegacyAttributeKey(): void
    {
        $catalog = new PermissionCatalogService();
        $this->assertNull($catalog->findPermissionRow('Persona.nombre.edit'));
        $this->assertFalse($catalog->isIntentPermissionKey('Persona.nombre.edit'));
        $this->assertTrue(PermissionCatalogService::isLegacyAttributePermissionKey('Persona.nombre.edit'));
    }

    public function testBuildIntentFieldManifestForPilot(): void
    {
        $manifest = (new PermissionCatalogService())->buildIntentFieldManifest('condicion-laboral.editar-propio');
        $this->assertNotNull($manifest);
        $this->assertSame('edit', $manifest['operation']);
        $this->assertSame('condicion-laboral.edit', $manifest['intent_family']);
        $this->assertNotEmpty($manifest['fields']);
        $this->assertIsArray($manifest['field_groups']);
    }

    public function testSyncDefinitionsIncludeOnlyIntents(): void
    {
        $defs = (new CatalogPermissionSyncService())->collectDefinitions();
        $this->assertNotEmpty($defs);
        foreach ($defs as $def) {
            $this->assertSame('intent', $def['kind']);
            $this->assertFalse(
                PermissionCatalogService::isLegacyAttributePermissionKey((string) $def['key']),
                'Sync no debe incluir claves atributo: ' . $def['key']
            );
        }
    }
}
