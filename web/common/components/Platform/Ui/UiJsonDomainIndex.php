<?php

namespace common\components\Platform\Ui;

use Yii;

/**
 * Índice del árbol de descriptores UI JSON: `views/json/<dominio>/<entidad>/<accion>.json`.
 *
 * La **carpeta es la fuente de verdad** del dominio, igual que en `controllers/<dominio>/`
 * (ver {@see \frontend\modules\api\v1\DomainControllerMap}). No hay mapa `entidad → dominio`
 * que mantener a mano: mover un descriptor de carpeta cambia su dominio, y agregar uno nuevo
 * no exige tocar PHP.
 *
 */
final class UiJsonDomainIndex
{
    /** Raíz del árbol de descriptores. Única definición en el proyecto. */
    public const BASE_ALIAS = '@frontend/modules/api/v1/views/json';

    /** @var array<string, string>|null entidad → dominio */
    private static ?array $entityDomains = null;

    /** @var list<string>|null */
    private static ?array $domains = null;

    public static function domainForEntity(string $entity): ?string
    {
        $entity = strtolower(trim($entity));
        if ($entity === '') {
            return null;
        }
        $index = self::index();

        return $index[$entity] ?? null;
    }

    /**
     * ¿Este segmento es una carpeta de primer nivel del árbol?
     *
     * Reemplaza al prefijo `clinical` hardcodeado: cualquier dominio puede aparecer como
     * prefijo de un action id (`clinical.internacion.mapa-camas`) o de una ruta API.
     */
    public static function isDomain(string $segment): bool
    {
        $segment = strtolower(trim($segment));
        self::index();

        return $segment !== '' && in_array($segment, self::$domains ?? [], true);
    }

    /** @return list<string> */
    public static function domains(): array
    {
        self::index();

        return self::$domains ?? [];
    }

    /** @return array<string, string> */
    public static function entityDomains(): array
    {
        return self::index();
    }

    public static function resetCacheForTests(): void
    {
        self::$entityDomains = null;
        self::$domains = null;
    }

    /**
     * @return array<string, string>
     */
    private static function index(): array
    {
        if (self::$entityDomains !== null) {
            return self::$entityDomains;
        }

        $base = Yii::getAlias(self::BASE_ALIAS, false);
        $domains = [];
        $entities = [];

        if (is_string($base) && $base !== '') {
            foreach (glob(rtrim($base, '/\\') . '/*', GLOB_ONLYDIR) ?: [] as $domainDir) {
                $domain = strtolower(basename($domainDir));
                $domains[$domain] = true;
                foreach (glob($domainDir . '/*', GLOB_ONLYDIR) ?: [] as $entityDir) {
                    $entity = strtolower(basename($entityDir));
                    if (isset($entities[$entity]) && $entities[$entity] !== $domain) {
                        Yii::warning(
                            "Entidad UI JSON '{$entity}' en dos dominios: '{$entities[$entity]}' y '{$domain}'",
                            'ui-json'
                        );
                        continue;
                    }
                    $entities[$entity] = $domain;
                }
            }
        }

        ksort($entities);
        ksort($domains);
        self::$entityDomains = $entities;
        self::$domains = array_keys($domains);

        return self::$entityDomains;
    }
}
