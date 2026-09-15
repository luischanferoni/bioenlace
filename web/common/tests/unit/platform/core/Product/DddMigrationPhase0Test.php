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

    public function testLegacyIntentRootsVaciosTrasFase02(): void
    {
        $this->assertSame(
            [],
            IntentSchemaPaths::legacyIntentRoots(),
            'Tras fase 02 no debe quedar metadata/bioenlace/*/intents'
        );
    }

    public function testColocatedIntentRootsIncluyenTodosLosBcConFlows(): void
    {
        $colocated = ProductMetadataPaths::colocatedIntentRoots();
        $this->assertNotEmpty($colocated);

        $norm = array_map(
            static fn (string $p): string => str_replace('\\', '/', $p),
            $colocated
        );
        $joined = implode("\n", $norm);
        foreach (['Clinical', 'Scheduling', 'Person', 'Organization'] as $bc) {
            $this->assertStringContainsString(
                "Domain/{$bc}/Application/Flows/intents",
                $joined
            );
        }
        $this->assertStringContainsString(
            'Platform/Assistant/Application/Flows/intents',
            $joined
        );
    }

    public function testIntentRootsSonSoloColocalizados(): void
    {
        $all = IntentSchemaPaths::intentRoots();
        $colocated = ProductMetadataPaths::colocatedIntentRoots();
        sort($all);
        sort($colocated);
        $this->assertSame($colocated, $all);
    }

    public function testDiscoverYamlFilesTodosColocalizados(): void
    {
        $index = IntentSchemaPaths::buildIndex();
        $this->assertGreaterThanOrEqual(50, count($index), 'Debe descubrir intent_id desde YAML');

        foreach ($index as $intentId => $path) {
            $this->assertTrue(
                IntentSchemaPaths::isColocatedPath($path),
                "intent '$intentId' aún en path legacy: $path"
            );
        }
    }

    public function testDomainFromPathColocatedSamples(): void
    {
        $samples = [
            'turnos.cancelar-como-paciente-flow' => 'scheduling',
            'internacion.ingreso-flow' => 'clinical',
            'personas.vincular-menor-flow' => 'person',
            'profesional-efector-servicio.crear-flow' => 'organization',
            'data-access.listar' => 'platform',
        ];
        foreach ($samples as $intentId => $domain) {
            $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
            $this->assertNotNull($path, $intentId);
            $this->assertSame($domain, IntentSchemaPaths::domainFromPath($path), $intentId);
            $this->assertTrue(IntentSchemaPaths::isColocatedPath($path), $intentId);
        }
    }

    public function testDomainFromPathLegacy(): void
    {
        $this->assertSame([], IntentSchemaPaths::legacyIntentRoots());
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
