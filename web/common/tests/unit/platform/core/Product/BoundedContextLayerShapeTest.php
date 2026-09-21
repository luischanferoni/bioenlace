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
        // BCs con agents aún pendientes de migrar (ninguno tras oleada Application/Agents).
        $pending = [];

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
        // Layout BC: Application/Agents (canónico; Agent singular ya no se usa)
        $application = $bcPath . DIRECTORY_SEPARATOR . 'Application';
        if (is_dir($application . DIRECTORY_SEPARATOR . 'Agents')
            || is_dir($application . DIRECTORY_SEPARATOR . 'Agent')) {
            return true;
        }
        // Layout módulo de capacidad: <Modulo>/Application/Agents
        foreach (scandir($bcPath) ?: [] as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }
            $moduleApp = $bcPath
                . DIRECTORY_SEPARATOR . $child
                . DIRECTORY_SEPARATOR . 'Application';
            if (is_dir($moduleApp . DIRECTORY_SEPARATOR . 'Agents')
                || is_dir($moduleApp . DIRECTORY_SEPARATOR . 'Agent')) {
                return true;
            }
        }
        if (!is_dir($application)) {
            return false;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($application, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $name = $file->getFilename();
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
            . DIRECTORY_SEPARATOR . 'Agenda'
            . DIRECTORY_SEPARATOR . 'Application'
            . DIRECTORY_SEPARATOR . 'Flows'
            . DIRECTORY_SEPARATOR . 'intents';
        $this->assertDirectoryExists($path);
    }

    public function testDomainNoTieneMetadataYamlLocal(): void
    {
        $root = ProductDomainCatalog::domainRoot();
        $leftover = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $pathname = str_replace('\\', '/', $file->getPathname());
            if (preg_match('#/metadata/[^/]+\\.ya?ml$#', $pathname)
                && !str_contains($pathname, '/Application/Flows/')
            ) {
                $leftover[] = $pathname;
            }
        }
        $this->assertSame(
            [],
            $leftover,
            "Knobs de negocio no deben vivir en Domain/*/metadata/*.yaml:\n"
            . implode("\n", $leftover)
        );
    }

    public function testClinicalNoTieneInfrastructureEnRaizDelBc(): void
    {
        $path = ProductDomainCatalog::domainRoot()
            . DIRECTORY_SEPARATOR . 'Clinical'
            . DIRECTORY_SEPARATOR . 'Infrastructure';
        $this->assertFalse(
            is_dir($path),
            'Clinical/Infrastructure/ en la raíz del BC está prohibido: ACL bajo <Modulo>/Infrastructure/'
        );
    }

    public function testClinicalModulosSinPhpEnRaizNiCarpetasProhibidas(): void
    {
        $clinical = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Clinical';
        $this->assertDirectoryExists($clinical);

        $pluginModules = ['Assistant' => true, 'Home' => true, 'DataAccess' => true];
        $forbiddenL1 = [
            'Support' => true,
            'Batch' => true,
            'Mapper' => true,
            'Service' => true,
            'Dto' => true,
            'Presentation' => true,
            'Legacy' => true,
            'Reminder' => true,
            'Workflow' => true,
            'SpeechToText' => true,
            'Text' => true,
        ];
        $errors = [];

        foreach (scandir($clinical) ?: [] as $module) {
            if ($module === '.' || $module === '..' || $module === 'README.md') {
                continue;
            }
            $modulePath = $clinical . DIRECTORY_SEPARATOR . $module;
            if (!is_dir($modulePath)) {
                continue;
            }
            if (!isset($pluginModules[$module])) {
                foreach (scandir($modulePath) ?: [] as $name) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    $child = $modulePath . DIRECTORY_SEPARATOR . $name;
                    if (is_file($child) && str_ends_with($name, '.php')) {
                        $errors[] = "PHP en raíz de módulo Clinical/$module/$name";
                    }
                    if (is_dir($child) && isset($forbiddenL1[$name])) {
                        $errors[] = "Carpeta prohibida Clinical/$module/$name/";
                    }
                }
                $legacyApp = $modulePath . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'Legacy';
                if (is_dir($legacyApp)) {
                    $errors[] = "Application/Legacy prohibido: Clinical/$module/Application/Legacy/";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Gramática Clinical (domain-folder-grammar):\n" . implode("\n", $errors)
        );
    }

    /**
     * Módulos Clinical ya alineados al eje Capture: Application/* solo roles CA.
     * Ampliar la lista al cerrar cada módulo de fase 01.
     */
    public function testClinicalAdoptedModulesApplicationSoloRolesCa(): void
    {
        $clinical = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Clinical';
        $adopted = [
            'Capture' => true,
            'Encounter' => true,
            'CarePlan' => true,
            'CareCohort' => true,
            'Emergency' => true,
            'HistoryExchange' => true,
            'Home' => true,
            'Inpatient' => true,
            'Laboratory' => true,
            'LegalRecord' => true,
            'PedidoAtencion' => true,
            'Prescription' => true,
            'Specialty' => true,
        ];
        $allowedAppDirs = [
            'UseCase' => true,
            'Presentation' => true,
            'Service' => true,
            'Authorization' => true,
            'Flows' => true,
            'Agents' => true,
            'Seed' => true,
        ];
        $errors = [];

        foreach (array_keys($adopted) as $module) {
            $app = $clinical . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Application';
            if (!is_dir($app)) {
                $errors[] = "Clinical/$module/Application/ ausente";
                continue;
            }
            foreach (scandir($app) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === 'README.md') {
                    continue;
                }
                $child = $app . DIRECTORY_SEPARATOR . $name;
                if (is_file($child) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP suelto en Clinical/$module/Application/$name (mover a rol CA)";
                    continue;
                }
                if (is_dir($child) && !isset($allowedAppDirs[$name])) {
                    $errors[] = "Carpeta no-CA Clinical/$module/Application/$name/";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Application solo roles CA (domain-folder-grammar):\n" . implode("\n", $errors)
        );
    }

    public function testOrganizationNoApplicationNiDomainEnRaizDelBc(): void
    {
        $org = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Organization';
        $this->assertDirectoryExists($org);
        $this->assertFalse(
            is_dir($org . DIRECTORY_SEPARATOR . 'Application'),
            'Organization/Application/ en la raíz del BC está prohibido: capas bajo <Modulo>/Application/'
        );
        $this->assertFalse(
            is_dir($org . DIRECTORY_SEPARATOR . 'Domain'),
            'Organization/Domain/ en la raíz del BC está prohibido: building blocks bajo <Modulo>/Domain/'
        );
        $this->assertFalse(
            is_dir($org . DIRECTORY_SEPARATOR . 'Infrastructure'),
            'Organization/Infrastructure/ en la raíz del BC está prohibido: ACL bajo <Modulo>/Infrastructure/'
        );
    }

    public function testOrganizationModulosApplicationSoloRolesCa(): void
    {
        $org = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Organization';
        $modules = ['Efector' => true, 'Servicio' => true, 'Pes' => true, 'SesionOperativa' => true];
        $pluginOk = ['Assistant' => true, 'DataAccess' => true];
        $allowedAppDirs = [
            'UseCase' => true,
            'Presentation' => true,
            'Service' => true,
            'Authorization' => true,
            'Flows' => true,
            'Agents' => true,
            'Seed' => true,
        ];
        $allowedModuleL1 = [
            'Application' => true,
            'Domain' => true,
            'Infrastructure' => true,
            'README.md' => true,
        ];
        $errors = [];

        foreach (scandir($org) ?: [] as $child) {
            if ($child === '.' || $child === '..' || $child === 'README.md') {
                continue;
            }
            $path = $org . DIRECTORY_SEPARATOR . $child;
            if (!is_dir($path)) {
                continue;
            }
            if (isset($pluginOk[$child])) {
                continue;
            }
            if (!isset($modules[$child])) {
                $errors[] = "Organization/$child/ no es módulo ni plugin conocidos";
                continue;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $inner = $path . DIRECTORY_SEPARATOR . $name;
                if (is_file($inner) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP en raíz Organization/$child/$name";
                }
                if (is_dir($inner) && !isset($allowedModuleL1[$name])) {
                    $errors[] = "L1 no canónica Organization/$child/$name/";
                }
            }
            $app = $path . DIRECTORY_SEPARATOR . 'Application';
            if (!is_dir($app)) {
                $errors[] = "Organization/$child/Application/ ausente";
                continue;
            }
            foreach (scandir($app) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === 'README.md') {
                    continue;
                }
                $childApp = $app . DIRECTORY_SEPARATOR . $name;
                if (is_file($childApp) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP suelto Organization/$child/Application/$name";
                }
                if (is_dir($childApp) && !isset($allowedAppDirs[$name])) {
                    $errors[] = "Carpeta no-CA Organization/$child/Application/$name/";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Organization módulo-primero (fase 02):\n" . implode("\n", $errors)
        );
    }

    public function testSchedulingNoApplicationEnRaizDelBc(): void
    {
        $sched = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Scheduling';
        $this->assertDirectoryExists($sched);
        foreach (['Application', 'Domain', 'Infrastructure'] as $layer) {
            $this->assertFalse(
                is_dir($sched . DIRECTORY_SEPARATOR . $layer),
                "Scheduling/$layer/ en la raíz del BC está prohibido: capas bajo <Modulo>/"
            );
        }
    }

    public function testSchedulingModulosApplicationSoloRolesCa(): void
    {
        $sched = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Scheduling';
        $modules = [
            'Agenda' => true,
            'BehaviorProfile' => true,
            'Quirofano' => true,
            'Home' => true,
        ];
        $pluginOk = ['Assistant' => true];
        $allowedAppDirs = [
            'UseCase' => true,
            'Presentation' => true,
            'Service' => true,
            'Authorization' => true,
            'Flows' => true,
            'Agents' => true,
            'Seed' => true,
        ];
        $allowedModuleL1 = [
            'Application' => true,
            'Domain' => true,
            'Infrastructure' => true,
            'Sections' => true, // plugin Home sections
            'README.md' => true,
        ];
        $errors = [];

        foreach (scandir($sched) ?: [] as $child) {
            if ($child === '.' || $child === '..' || $child === 'README.md') {
                continue;
            }
            $path = $sched . DIRECTORY_SEPARATOR . $child;
            if (!is_dir($path)) {
                continue;
            }
            if (isset($pluginOk[$child])) {
                continue;
            }
            if (!isset($modules[$child])) {
                $errors[] = "Scheduling/$child/ no es módulo ni plugin conocidos";
                continue;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $inner = $path . DIRECTORY_SEPARATOR . $name;
                if (is_file($inner) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP en raíz Scheduling/$child/$name";
                }
                if (is_dir($inner) && !isset($allowedModuleL1[$name])) {
                    $errors[] = "L1 no canónica Scheduling/$child/$name/";
                }
            }
            $app = $path . DIRECTORY_SEPARATOR . 'Application';
            if (!is_dir($app)) {
                continue; // Home puede vivir solo de Sections; Application opcional si vacío
            }
            foreach (scandir($app) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === 'README.md') {
                    continue;
                }
                $childApp = $app . DIRECTORY_SEPARATOR . $name;
                if (is_file($childApp) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP suelto Scheduling/$child/Application/$name";
                }
                if (is_dir($childApp) && !isset($allowedAppDirs[$name])) {
                    $errors[] = "Carpeta no-CA Scheduling/$child/Application/$name/";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Scheduling módulo-primero (fase 03):\n" . implode("\n", $errors)
        );
    }

    public function testPersonNoApplicationEnRaizDelBc(): void
    {
        $person = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Person';
        $this->assertDirectoryExists($person);
        foreach (['Application', 'Domain', 'Infrastructure'] as $layer) {
            $this->assertFalse(
                is_dir($person . DIRECTORY_SEPARATOR . $layer),
                "Person/$layer/ en la raíz del BC está prohibido: capas bajo <Modulo>/"
            );
        }
    }

    public function testPersonModulosApplicationSoloRolesCa(): void
    {
        $person = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Person';
        $modules = [
            'Identidad' => true,
            'Representation' => true,
            'Ventanilla' => true,
        ];
        $pluginOk = ['Assistant' => true, 'DataAccess' => true];
        $allowedAppDirs = [
            'UseCase' => true,
            'Presentation' => true,
            'Service' => true,
            'Authorization' => true,
            'Flows' => true,
            'Agents' => true,
            'Seed' => true,
        ];
        $allowedModuleL1 = [
            'Application' => true,
            'Domain' => true,
            'Infrastructure' => true,
            'README.md' => true,
        ];
        $errors = [];

        foreach (scandir($person) ?: [] as $child) {
            if ($child === '.' || $child === '..' || $child === 'README.md') {
                continue;
            }
            $path = $person . DIRECTORY_SEPARATOR . $child;
            if (!is_dir($path)) {
                continue;
            }
            if (isset($pluginOk[$child])) {
                continue;
            }
            if (!isset($modules[$child])) {
                $errors[] = "Person/$child/ no es módulo ni plugin conocidos";
                continue;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $inner = $path . DIRECTORY_SEPARATOR . $name;
                if (is_file($inner) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP en raíz Person/$child/$name";
                }
                if (is_dir($inner) && !isset($allowedModuleL1[$name])) {
                    $errors[] = "L1 no canónica Person/$child/$name/";
                }
            }
            $app = $path . DIRECTORY_SEPARATOR . 'Application';
            if (!is_dir($app)) {
                $errors[] = "Person/$child/Application/ ausente";
                continue;
            }
            foreach (scandir($app) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === 'README.md') {
                    continue;
                }
                $childApp = $app . DIRECTORY_SEPARATOR . $name;
                if (is_file($childApp) && str_ends_with($name, '.php')) {
                    $errors[] = "PHP suelto Person/$child/Application/$name";
                }
                if (is_dir($childApp) && !isset($allowedAppDirs[$name])) {
                    $errors[] = "Carpeta no-CA Person/$child/Application/$name/";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Person módulo-primero (fase 04):\n" . implode("\n", $errors)
        );
    }

    public function testDomainBcSinServiceNiPresentationL1(): void
    {
        $root = ProductDomainCatalog::domainRoot();
        $forbidden = ['Service' => true, 'Presentation' => true, 'Dto' => true, 'Legacy' => true];
        $pluginOk = ['Assistant' => true, 'Home' => true, 'DataAccess' => true];
        $errors = [];

        foreach (scandir($root) ?: [] as $bc) {
            if ($bc === '.' || $bc === '..' || !is_dir($root . DIRECTORY_SEPARATOR . $bc)) {
                continue;
            }
            if ($bc === 'Clinical') {
                continue; // cubierto por testClinicalModulosSinPhpEnRaizNiCarpetasProhibidas
            }
            if ($bc === 'Organization' || $bc === 'Scheduling' || $bc === 'Person') {
                continue; // módulo-primero; cubierto por tests específicos
            }
            $bcPath = $root . DIRECTORY_SEPARATOR . $bc;
            foreach (scandir($bcPath) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $bcPath . DIRECTORY_SEPARATOR . $name;
                if (is_dir($child) && isset($forbidden[$name])) {
                    $errors[] = "Domain/$bc/$name/ (usar Application/…)";
                }
            }
            // Áreas de lenguaje (Representation, Ventanilla, Quirofano, …): sin Service L1 interno
            foreach (scandir($bcPath) ?: [] as $area) {
                if ($area === '.' || $area === '..' || isset($pluginOk[$area])) {
                    continue;
                }
                if (in_array($area, ['Application', 'Domain', 'Infrastructure'], true)) {
                    continue;
                }
                $areaPath = $bcPath . DIRECTORY_SEPARATOR . $area;
                if (!is_dir($areaPath)) {
                    continue;
                }
                foreach (['Service', 'Presentation', 'Dto', 'Legacy', 'Enum'] as $bad) {
                    if (is_dir($areaPath . DIRECTORY_SEPARATOR . $bad)) {
                        $errors[] = "Domain/$bc/$area/$bad/ (tríada interna Application|Domain|Infrastructure)";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "Gramática BC (domain-folder-grammar):\n" . implode("\n", $errors)
        );
    }

    public function testClinicalMapperSoloBajoInfrastructureExternal(): void
    {
        $clinical = ProductDomainCatalog::domainRoot() . DIRECTORY_SEPARATOR . 'Clinical';
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($clinical, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (!str_ends_with($file->getFilename(), 'Mapper.php')) {
                continue;
            }
            $pathname = str_replace('\\', '/', $file->getPathname());
            if (!str_contains($pathname, '/Infrastructure/External/')) {
                $errors[] = $pathname;
            }
        }
        $this->assertSame(
            [],
            $errors,
            "*Mapper.php debe vivir bajo Infrastructure/External/:\n" . implode("\n", $errors)
        );
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
