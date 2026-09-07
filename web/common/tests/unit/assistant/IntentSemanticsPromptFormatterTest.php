<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSemanticsPromptFormatter;
use common\components\Platform\Assistant\Catalog\SmartCatalogRegistry;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;

class IntentSemanticsPromptFormatterTest extends Unit
{
    protected function _after(): void
    {
        SmartCatalogRegistry::resetCacheForTests();
        AssistantMetadataLoader::resetCacheForTests();
    }

    public function testTurnosCrearIsHumanReadableWithoutIdsOrMarkers(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('turnos.crear-como-paciente');

        $this->assertStringContainsString('Turno con un especialista:', $block);
        $this->assertStringContainsString('Pasos:', $block);
        $this->assertStringContainsString('Elegir oferta del centro', $block);
        $this->assertStringNotContainsString('turnos.crear-como-paciente', $block);
        $this->assertStringNotContainsString('objective:', $block);
        $this->assertStringNotContainsString('context:intent_semantics', $block);
        $this->assertStringNotContainsString('capabilities:', $block);
    }

    public function testAtencionUsesRecorridoWithoutStepsDump(): void
    {
        $block = IntentSemanticsPromptFormatter::formatIntentId('atencion.necesito-atencion');

        $this->assertStringContainsString('Solicitar Atención:', $block);
        $this->assertStringContainsString('Recorrido:', $block);
        $this->assertStringNotContainsString('Pasos:', $block);
        $this->assertStringNotContainsString('atencion.necesito-atencion', $block);
    }

    public function testFormatForIntentIdsJoinsWithoutTechnicalFence(): void
    {
        $wrapped = IntentSemanticsPromptFormatter::formatForIntentIds([
            'turnos.crear-como-paciente',
            'atencion.necesito-atencion',
        ], 4);

        $this->assertStringContainsString('Turno con un especialista:', $wrapped);
        $this->assertStringContainsString('Solicitar Atención:', $wrapped);
        $this->assertStringNotContainsString('---', $wrapped);
    }
}
