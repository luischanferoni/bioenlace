<?php

namespace common\tests\unit\core\Permission;

use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use Codeception\Test\Unit;

class IntentSchemaPathsTest extends Unit
{
    public function testDiscoversNestedCreateIntent(): void
    {
        IntentSchemaPaths::resetIndexCache();
        $path = IntentSchemaPaths::resolveFileForIntentId('turnos.crear-como-paciente');
        $this->assertNotNull($path);
        $this->assertStringContainsString('create' . DIRECTORY_SEPARATOR . 'turnos.crear-como-paciente.yaml', str_replace('/', DIRECTORY_SEPARATOR, $path));
        $this->assertSame(IntentSchemaPaths::CATEGORY_CREATE, IntentSchemaPaths::categoryForIntentId('turnos.crear-como-paciente'));
    }

    public function testDiscoverYamlFilesIncludesSubfolders(): void
    {
        $files = IntentSchemaPaths::discoverYamlFiles();
        $this->assertNotEmpty($files);
        $hasNested = false;
        foreach ($files as $file) {
            if (strpos(str_replace('\\', '/', $file), '/intents/create/') !== false) {
                $hasNested = true;
                break;
            }
        }
        $this->assertTrue($hasNested, 'Debe incluir YAML bajo intents/create/');
    }

    public function testStripOrderPrefixFromFilename(): void
    {
        $this->assertSame('condicion-laboral.editar-staff', IntentSchemaPaths::stripOrderPrefix('02-condicion-laboral.editar-staff'));
        $this->assertSame('data-access.editar', IntentSchemaPaths::stripOrderPrefix('data-access.editar'));
    }

    public function testResolvesNumberedUpdateIntentByYamlIntentId(): void
    {
        IntentSchemaPaths::resetIndexCache();
        $path = IntentSchemaPaths::resolveFileForIntentId('condicion-laboral.editar-staff');
        $this->assertNotNull($path);
        $this->assertStringContainsString('02-condicion-laboral.editar-staff.yaml', str_replace('\\', '/', $path));
    }
}
