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
}
