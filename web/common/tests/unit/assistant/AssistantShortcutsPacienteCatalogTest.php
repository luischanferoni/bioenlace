<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\AssistantShortcutsCatalog;
use Symfony\Component\Yaml\Yaml;
use Yii;

class AssistantShortcutsPacienteCatalogTest extends Unit
{
    protected function _after(): void
    {
        AssistantShortcutsCatalog::resetCacheForTests();
    }

    public function testPacienteCatalogHasNoSubgroups(): void
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/assistant/assistant-shortcuts-paciente.yaml');
        $parsed = Yaml::parseFile($path);
        $this->assertIsArray($parsed);
        foreach ($parsed['categories'] ?? [] as $cat) {
            $this->assertIsArray($cat);
            $subgroups = $cat['subgroups'] ?? [];
            $this->assertSame([], $subgroups, 'Categoría paciente no debe tener subgrupos: ' . ($cat['id'] ?? ''));
        }
    }

    public function testPacienteCatalogExcludesStaffIntents(): void
    {
        $cats = AssistantShortcutsCatalog::categories('assistant-shortcuts-paciente.yaml');
        $intentIds = [];
        foreach ($cats as $cat) {
            foreach ($cat['intent_ids'] as $id) {
                $intentIds[] = $id;
            }
        }
        $this->assertContains('atencion.consultas-seguimiento-flow', $intentIds);
        $this->assertContains('atencion.necesito-atencion', $intentIds);
        $this->assertNotContains('profesional-agenda.configurar-staff', $intentIds);
        $this->assertNotContains('urgencias.ver-tablero-guardia', $intentIds);
    }
}
