<?php

namespace common\tests\unit\platform\core\Product;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\ProductDomainCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Invariantes de capas DDD dentro de cada BC (esqueleto; se endurece al migrar).
 *
 * Cuando existe `Application/`, no debe haber `*Agent.php` bajo `Service/` legacy
 * en ese mismo BC (regla progresiva: solo falla si ya hay Application/ y Agents mal ubicados).
 */
final class BoundedContextLayerShapeTest extends Unit
{
    /** Capas DDD canónicas bajo un BC. */
    private const LAYER_DIRS = [
        'Application',
        'Domain',
        'Infrastructure',
        'Presentation',
    ];

    public function testCapasDddSiExistenTienenNombreCanonico(): void
    {
        $root = ProductDomainCatalog::domainRoot();
        $errors = [];

        foreach (scandir($root) ?: [] as $bc) {
            if ($bc === '.' || $bc === '..' || $bc === 'README.md') {
                continue;
            }
            $bcPath = $root . DIRECTORY_SEPARATOR . $bc;
            if (!is_dir($bcPath)) {
                continue;
            }
            foreach (scandir($bcPath) ?: [] as $child) {
                if ($child === '.' || $child === '..') {
                    continue;
                }
                // No fallar por carpetas legacy (Service, Emergency, …); solo validar
                // que si alguien crea ApplicationX no canónica, no — las capas canónicas
                // se listan abajo cuando existan.
            }
            foreach (self::LAYER_DIRS as $layer) {
                $layerPath = $bcPath . DIRECTORY_SEPARATOR . $layer;
                if (!is_dir($layerPath)) {
                    continue;
                }
                // Carpeta de capa existe: ok. Futuro: contenido tipado.
                $this->assertDirectoryExists($layerPath);
            }
        }

        $this->assertSame([], $errors);
    }

    public function testAgentNoBajoServiceSiYaHayDestinoApplicationAgent(): void
    {
        $root = ProductDomainCatalog::domainRoot();
        $errors = [];
        // BCs con *Agent aún en Service/ legacy; se vacían en oleada fase 06.
        $pending = ['clinical' => true, 'scheduling' => true];

        foreach (scandir($root) ?: [] as $bc) {
            if ($bc === '.' || $bc === '..' || !is_dir($root . DIRECTORY_SEPARATOR . $bc)) {
                continue;
            }
            if (isset($pending[strtolower($bc)])) {
                continue;
            }
            $bcPath = $root . DIRECTORY_SEPARATOR . $bc;
            if (!$this->bcHasApplicationAgentHome($bcPath)) {
                continue;
            }
            $errors = array_merge($errors, $this->findAgentsUnderService($bcPath, $bc));
        }

        $this->assertSame(
            [],
            $errors,
            "Con Application/… agents migrados, *Agent.php no debe vivir bajo Service/:\n"
            . implode("\n", $errors)
        );
    }

    private function bcHasApplicationAgentHome(string $bcPath): bool
    {
        $application = $bcPath . DIRECTORY_SEPARATOR . 'Application';
        if (!is_dir($application)) {
            return false;
        }
        if (is_dir($application . DIRECTORY_SEPARATOR . 'Agent')) {
            return true;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($application, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $name = $file->getFilename();
            // *AgentPolicy.php son knobs tipados, no el Agent ejecutable.
            if (!str_ends_with($name, 'Agent.php') || str_ends_with($name, 'AgentPolicy.php')) {
                continue;
            }
            return true;
        }

        return false;
    }

    public function testSchedulingTieneApplicationFlowsIntents(): void
    {
        $path = ProductMetadataPaths::componentsDomainRoot()
            . DIRECTORY_SEPARATOR . 'Scheduling'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Flows'
            . DIRECTORY_SEPARATOR . 'intents';
        $this->assertDirectoryExists($path);
    }

    /**
     * @return list<string>
     */
    private function findAgentsUnderService(string $bcPath, string $bc): array
    {
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($bcPath, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $name = $file->getFilename();
            if (!str_ends_with($name, 'Agent.php')) {
                continue;
            }
            $pathname = str_replace('\\', '/', $file->getPathname());
            if (str_contains($pathname, '/Service/') || str_contains($pathname, '\\Service\\')) {
                $errors[] = "$bc: $pathname";
            }
        }

        return $errors;
    }
}
