<?php

namespace common\tests\unit\platform\core\Product;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentSchemaPaths;
use common\components\Platform\Core\Product\AgentBoundedContextMap;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Fase 00 DDD: dual-root de intents + mapa agents→BC.
 */
final class DddMigrationPhase0Test extends Unit
{
    protected function _before(): void
    {
        IntentSchemaPaths::resetIndexCache();
    }

    protected function _after(): void
    {
        IntentSchemaPaths::resetIndexCache();
    }

    public function testLegacyIntentRootsSiguenExistiendo(): void
    {
        $legacy = IntentSchemaPaths::legacyIntentRoots();
        $this->assertNotEmpty($legacy, 'Debe haber intents bajo metadata/bioenlace');
    }

    public function testColocatedIntentRootsIncluyenEsqueletoSchedulingYPlatform(): void
    {
        $colocated = ProductMetadataPaths::colocatedIntentRoots();
        $this->assertNotEmpty($colocated);

        $norm = array_map(
            static fn (string $p): string => str_replace('\\', '/', $p),
            $colocated
        );
        $joined = implode("\n", $norm);
        $this->assertStringContainsString(
            'Domain/Scheduling/Application/Flows/intents',
            $joined
        );
        $this->assertStringContainsString(
            'Platform/Assistant/Application/Flows/intents',
            $joined
        );
    }

    public function testIntentRootsUneLegacyYColocalizado(): void
    {
        $all = IntentSchemaPaths::intentRoots();
        $legacy = IntentSchemaPaths::legacyIntentRoots();
        $colocated = ProductMetadataPaths::colocatedIntentRoots();

        foreach ($legacy as $root) {
            $this->assertContains($root, $all);
        }
        foreach ($colocated as $root) {
            $this->assertContains($root, $all);
        }
    }

    public function testDiscoverYamlFilesParidadConLegacy(): void
    {
        $index = IntentSchemaPaths::buildIndex();
        $this->assertNotEmpty($index, 'Debe descubrir intent_id desde YAML');

        $legacyFiles = [];
        foreach (IntentSchemaPaths::legacyIntentRoots() as $root) {
            foreach (['create', 'read', 'update', 'delete'] as $category) {
                $subdir = $root . DIRECTORY_SEPARATOR . $category;
                if (!is_dir($subdir)) {
                    continue;
                }
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($subdir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $file) {
                    if ($file->isFile() && str_ends_with(strtolower($file->getFilename()), '.yaml')) {
                        $legacyFiles[] = IntentSchemaPaths::intentIdFromPath($file->getPathname());
                    }
                }
            }
            foreach (glob($root . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $flat) {
                $legacyFiles[] = IntentSchemaPaths::intentIdFromPath($flat);
            }
        }
        $legacyIds = array_values(array_unique(array_filter($legacyFiles)));
        sort($legacyIds);

        foreach ($legacyIds as $intentId) {
            $this->assertArrayHasKey(
                $intentId,
                $index,
                "intent_id legacy '$intentId' debe estar en el índice dual-root"
            );
        }
    }

    public function testDomainFromPathLegacy(): void
    {
        $path = IntentSchemaPaths::resolveFileForIntentId('turnos.cancelar-como-paciente-flow');
        $this->assertNotNull($path);
        $this->assertSame('scheduling', IntentSchemaPaths::domainFromPath($path));
        // Fase 01: intents Scheduling ya viven en Application/Flows.
        $this->assertTrue(IntentSchemaPaths::isColocatedPath($path));
    }

    public function testDomainFromPathColocatedScheduling(): void
    {
        $dir = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Flows'
            . DIRECTORY_SEPARATOR . 'intents'
            . DIRECTORY_SEPARATOR . 'create';
        $this->assertDirectoryExists($dir);

        $tmp = $dir . DIRECTORY_SEPARATOR . '_phase0-colocated-probe.yaml';
        $created = false;
        try {
            if (!is_file($tmp)) {
                file_put_contents($tmp, "intent_id: _phase0-colocated-probe\nversion: 1\n");
                $created = true;
            }
            IntentSchemaPaths::resetIndexCache();
            $this->assertSame('scheduling', IntentSchemaPaths::domainFromPath($tmp));
            $this->assertTrue(IntentSchemaPaths::isColocatedPath($tmp));
        } finally {
            if ($created && is_file($tmp)) {
                unlink($tmp);
            }
            IntentSchemaPaths::resetIndexCache();
        }
    }

    public function testAgentMapCubreYamlDeAgents(): void
    {
        $agentsDir = ProductMetadataPaths::agentsDir();
        $this->assertDirectoryExists($agentsDir);

        $missing = [];
        foreach (glob($agentsDir . DIRECTORY_SEPARATOR . '*.yaml') ?: [] as $file) {
            $agentId = basename($file, '.yaml');
            if (!AgentBoundedContextMap::isKnown($agentId)) {
                $missing[] = $agentId;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Agregar agent_id a AgentBoundedContextMap: ' . implode(', ', $missing)
        );
    }

    public function testAgentMapOwnersSonDominiosOPlatform(): void
    {
        foreach (AgentBoundedContextMap::all() as $agentId => $owner) {
            $this->assertNotSame('', $owner, $agentId);
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_-]*$/',
                $owner,
                "owner inválido para $agentId"
            );
        }
    }
}
