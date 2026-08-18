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

    public function testDiscoversReadFlowNestedUnderFlows(): void
    {
        IntentSchemaPaths::resetIndexCache();
        $path = IntentSchemaPaths::resolveFileForIntentId('turnos.ver-ultimo-en-oferta-como-paciente');
        $this->assertNotNull($path);
        $normalized = str_replace('\\', '/', $path);
        $this->assertStringContainsString('/intents/read/flows/turnos.ver-ultimo-en-oferta-como-paciente.yaml', $normalized);
        $this->assertSame(IntentSchemaPaths::CATEGORY_READ, IntentSchemaPaths::categoryForIntentId('turnos.ver-ultimo-en-oferta-como-paciente'));
    }

    public function testMetricReadIntentStaysInReadRoot(): void
    {
        IntentSchemaPaths::resetIndexCache();
        $path = IntentSchemaPaths::resolveFileForIntentId('profesionales.conteo-efector');
        $this->assertNotNull($path);
        $normalized = str_replace('\\', '/', $path);
        $this->assertStringContainsString('/intents/read/profesionales.conteo-efector.yaml', $normalized);
        $this->assertStringNotContainsString('/read/flows/', $normalized);
        $this->assertSame(IntentSchemaPaths::CATEGORY_READ, IntentSchemaPaths::categoryForIntentId('profesionales.conteo-efector'));
    }
}
