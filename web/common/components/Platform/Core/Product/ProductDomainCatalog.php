<?php

namespace common\components\Platform\Core\Product;

/**
 * Conjunto canónico de dominios de producto: carpetas de primer nivel bajo
 * `common/components/Domain/`.
 *
 * Es la única fuente del id de dominio para invariantes de forma del árbol espejo
 * (`capa/dominio|platform/entidad`). `platform` no es un dominio: es el eje paralelo
 * de motores/transversal en las demás capas.
 *
 * @see web/docs/arquitectura/arbol-espejo-dominios.md
 */
final class ProductDomainCatalog
{
    /** Segmento transversal (misma posición que un dominio en capas espejo). */
    public const PLATFORM = 'platform';

    /**
     * Grafías prohibidas en el slot de dominio (lowercase → canónica).
     *
     * @var array<string, string>
     */
    private const FORBIDDEN_SPELLINGS = [
        'persona' => 'person',
        'integration' => 'integrations',
        'core' => self::PLATFORM,
        'common' => self::PLATFORM,
    ];

    /** Controllers API transversales permitidos en la raíz de `controllers/` (Fase 1). */
    public const ROOT_API_CONTROLLERS = [
        'AccionesController.php',
        'AuthController.php',
        'BaseController.php',
        'ChatController.php',
        'ClientDiagnosticController.php',
        'DeviceController.php',
        'EditarController.php',
        'HomeController.php',
        'InfoController.php',
        'ListarController.php',
        'LoginController.php',
        'NotificacionesController.php',
        'QuejaPacienteController.php',
    ];

    /** @var list<string>|null ids lowercase ordenados */
    private static ?array $ids = null;

    public static function domainRoot(): string
    {
        $default = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'Domain';
        $resolved = realpath($default);

        return $resolved !== false ? $resolved : $default;
    }

    /**
     * @return list<string> ids en lowercase, ordenados
     */
    public static function ids(): array
    {
        if (self::$ids !== null) {
            return self::$ids;
        }

        $root = self::domainRoot();
        if (!is_dir($root)) {
            self::$ids = [];

            return self::$ids;
        }

        $ids = [];
        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === 'README.md') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($path)) {
                continue;
            }
            $id = strtolower($name);
            // Plataforma no puede vivir bajo Domain/.
            if ($id === self::PLATFORM) {
                continue;
            }
            $ids[] = $id;
        }
        sort($ids);
        self::$ids = $ids;

        return self::$ids;
    }

    public static function isDomain(string $segment): bool
    {
        $segment = strtolower(trim($segment));

        return $segment !== '' && in_array($segment, self::ids(), true);
    }

    public static function isDomainOrPlatform(string $segment): bool
    {
        $segment = strtolower(trim($segment));

        return $segment === self::PLATFORM || self::isDomain($segment);
    }

    /**
     * @return array<string, string> grafía prohibida → canónica
     */
    public static function forbiddenSpellings(): array
    {
        return self::FORBIDDEN_SPELLINGS;
    }

    public static function resetCacheForTests(): void
    {
        self::$ids = null;
    }
}
