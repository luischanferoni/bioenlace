<?php

namespace common\tests\unit\platform\core\Product;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\ProductDomainCatalog;
use Yii;

/**
 * Invariantes de forma del árbol espejo: `capa/<dominio|platform>/…`.
 *
 * Fuente de dominios: {@see ProductDomainCatalog} ← carpetas en `components/Domain/`.
 *
 * @see web/docs/arquitectura/arbol-espejo-dominios.md
 */
final class ProductDomainTreeShapeTest extends Unit
{
    protected function _before(): void
    {
        ProductDomainCatalog::resetCacheForTests();
    }

    protected function _after(): void
    {
        ProductDomainCatalog::resetCacheForTests();
    }

    public function testDominiosSalenDeComponentsDomain(): void
    {
        $ids = ProductDomainCatalog::ids();
        $this->assertNotEmpty($ids, 'components/Domain/ no tiene carpetas de dominio');
        $this->assertContains('clinical', $ids);
        $this->assertContains('scheduling', $ids);
        $this->assertContains('person', $ids);
        $this->assertNotContains(
            ProductDomainCatalog::PLATFORM,
            $ids,
            'platform no es un dominio; no debe listarse desde Domain/'
        );
    }

    public function testDomainNoContieneCarpetaPlatform(): void
    {
        $path = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Platform';
        $this->assertFalse(
            is_dir($path),
            'components/Domain/Platform/ no puede existir: la plataforma va en components/Platform/'
        );
    }

    public function testModelsPrimerNivel(): void
    {
        $this->assertLayerTopLevel(
            Yii::getAlias('@common/models'),
            allowRootFiles: false,
            layerLabel: 'common/models'
        );
    }

    public function testMetadataPrimerNivel(): void
    {
        $this->assertLayerTopLevel(
            Yii::getAlias('@common') . '/metadata/bioenlace',
            allowRootFiles: false,
            layerLabel: 'metadata/bioenlace'
        );
    }

    public function testViewsJsonPrimerNivel(): void
    {
        $this->assertLayerTopLevel(
            Yii::getAlias('@frontend') . '/modules/api/v1/views/json',
            allowRootFiles: false,
            layerLabel: 'views/json'
        );
    }

    public function testTestsUnitPrimerNivel(): void
    {
        $this->assertLayerTopLevel(
            Yii::getAlias('@common') . '/tests/unit',
            allowRootFiles: false,
            layerLabel: 'tests/unit'
        );
    }

    public function testControllersPrimerNivel(): void
    {
        $dir = Yii::getAlias('@frontend') . '/modules/api/v1/controllers';
        $this->assertDirectoryExists($dir);

        $allowedFiles = array_fill_keys(ProductDomainCatalog::ROOT_API_CONTROLLERS, true);
        $errors = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === 'README.md') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $id = strtolower($name);
                if ($msg = $this->slotError($id, 'controllers')) {
                    $errors[] = $msg;
                }
                continue;
            }
            if (!isset($allowedFiles[$name])) {
                $errors[] = "controllers/: archivo plano no whitelisteado '$name' "
                    . '(mover a controllers/<dominio>/ o agregar a ProductDomainCatalog::ROOT_API_CONTROLLERS)';
            }
        }

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    public function testGrafiasProhibidasEnCapasEspejo(): void
    {
        $layers = [
            'common/models' => Yii::getAlias('@common/models'),
            'controllers' => Yii::getAlias('@frontend') . '/modules/api/v1/controllers',
            'views/json' => Yii::getAlias('@frontend') . '/modules/api/v1/views/json',
            'metadata/bioenlace' => Yii::getAlias('@common') . '/metadata/bioenlace',
            'tests/unit' => Yii::getAlias('@common') . '/tests/unit',
            'components/Domain' => ProductDomainCatalog::domainRoot(),
        ];

        $forbidden = ProductDomainCatalog::forbiddenSpellings();
        $errors = [];
        foreach ($layers as $label => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || !is_dir($dir . DIRECTORY_SEPARATOR . $name)) {
                    continue;
                }
                $id = strtolower($name);
                if (isset($forbidden[$id])) {
                    $errors[] = "$label/: carpeta '$name' — usar '{$forbidden[$id]}' (una sola grafía)";
                }
            }
        }

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    /**
     * Platform no debe depender de Domain concreto salvo registries/interfaces
     * ya inventariados. Aquí solo fallamos si aparece un use a Domain\Platform
     * (eje inválido) o si Domain/ importa Platform como si fuera dominio hermano
     * vía carpeta — el resto de acoplamientos cruzados se refactoriza aparte.
     */
    public function testSinNamespaceDomainPlatform(): void
    {
        $roots = [
            Yii::getAlias('@common/components'),
            Yii::getAlias('@common/models'),
            Yii::getAlias('@frontend') . '/modules/api/v1',
        ];
        $needle = 'common\\components\\Domain\\Platform';
        $hits = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                if ($contents !== false && str_contains($contents, $needle)) {
                    $hits[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
        $this->assertSame(
            [],
            $hits,
            "Namespace Domain\\Platform prohibido:\n" . implode("\n", $hits)
        );
    }

    /**
     * @param list<string> $allowRootFiles
     */
    private function assertLayerTopLevel(string $dir, bool $allowRootFiles, string $layerLabel): void
    {
        $this->assertDirectoryExists($dir, "Falta la capa $layerLabel");
        $errors = [];

        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === 'README.md') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $id = strtolower($name);
                if ($msg = $this->slotError($id, $layerLabel)) {
                    $errors[] = $msg;
                }
                continue;
            }
            if (!$allowRootFiles) {
                $errors[] = "$layerLabel/: archivo plano '$name' — mover bajo <dominio|platform>/";
            }
        }

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    private function slotError(string $id, string $layerLabel): ?string
    {
        if (ProductDomainCatalog::isDomainOrPlatform($id)) {
            return null;
        }
        $forbidden = ProductDomainCatalog::forbiddenSpellings();
        if (isset($forbidden[$id])) {
            return "$layerLabel/: '$id' — usar '{$forbidden[$id]}'";
        }

        return "$layerLabel/: carpeta '$id' no es un dominio de components/Domain/ ni 'platform'";
    }
}
