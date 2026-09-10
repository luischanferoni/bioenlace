<?php

namespace common\components\Platform\Ui;

/**
 * Resolución de carpetas bajo `views/json/` a partir del árbol de descriptores.
 *
 * Tanto los action id (`clinical.internacion.mapa-camas`) como las rutas API
 * (`/api/v1/clinical/encounter/ver-resumen`) pueden llevar el dominio adelante. Qué segmento es
 * un dominio lo dice {@see UiJsonDomainIndex::isDomain()}, no una constante por dominio.
 */
final class UiJsonDomain
{
    public static function forEntity(string $entity): ?string
    {
        return UiJsonDomainIndex::domainForEntity($entity);
    }

    /**
     * @return array{entity: string, action: string}|null
     */
    public static function parseActionId(string $actionId): ?array
    {
        $actionId = strtolower(trim($actionId));
        if ($actionId === '' || strpos($actionId, '.') === false) {
            return null;
        }

        $parts = explode('.', $actionId);
        if (count($parts) >= 3 && UiJsonDomainIndex::isDomain($parts[0])) {
            return [
                'entity' => $parts[1],
                'action' => implode('.', array_slice($parts, 2)),
            ];
        }

        return [
            'entity' => $parts[0],
            'action' => implode('.', array_slice($parts, 1)),
        ];
    }

    public static function resolveActionIdTemplatePath(string $actionId): ?string
    {
        $parsed = self::parseActionId($actionId);
        if ($parsed === null) {
            return null;
        }

        $path = UiDefinitionTemplateManager::resolveTemplateAbsolutePath(
            $parsed['entity'],
            $parsed['action']
        );
        if ($path !== null) {
            return $path;
        }

        $aliasAction = UiJsonDomainIndex::templateAliasAction($parsed['entity'], $parsed['action']);
        if ($aliasAction === null || $aliasAction === '') {
            return null;
        }

        return UiDefinitionTemplateManager::resolveTemplateAbsolutePath(
            $parsed['entity'],
            $aliasAction
        );
    }

    /**
     * @return list<string>
     */
    public static function candidateRelativePaths(string $entity, string $action): array
    {
        $entity = strtolower(trim($entity));
        $action = trim($action);
        if ($entity === '' || $action === '') {
            return [];
        }

        $folderEntity = UiJsonDomainIndex::templateFolderForEntity($entity);
        $file = $action . '.json';
        $out = [];
        $domain = self::forEntity($folderEntity) ?? self::forEntity($entity);
        if ($domain !== null) {
            $out[] = $domain . '/' . $folderEntity . '/' . $file;
        }
        $out[] = $folderEntity . '/' . $file;

        return array_values(array_unique($out));
    }

    /**
     * @return array{entity: string, action: string}|null
     */
    public static function parseApiV1UiRoute(string $route): ?array
    {
        $path = parse_url(trim($route), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = trim($route);
        }

        // Con dominio adelante y recurso identificado: /api/v{n}/{dominio}/{entidad}/{id}/{accion}
        if (preg_match('#^/api/v\d+/([\w-]+)/([\w-]+)/(?:\d+|\{[\w-]+\})/([\w-]+)$#', $path, $m) === 1
            && UiJsonDomainIndex::isDomain((string) $m[1])
        ) {
            return ['entity' => strtolower((string) $m[2]), 'action' => (string) $m[3]];
        }

        // Con dominio adelante: /api/v{n}/{dominio}/{entidad}/{accion}
        if (preg_match('#^/api/v\d+/([\w-]+)/([\w-]+)/([\w-]+)$#', $path, $m) === 1
            && UiJsonDomainIndex::isDomain((string) $m[1])
        ) {
            return ['entity' => strtolower((string) $m[2]), 'action' => (string) $m[3]];
        }

        // Sin dominio en la URL: /api/v{n}/{entidad}/{accion}
        if (preg_match('#^/api/v\d+/([\w-]+)/([\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        // DataAccess se expone sin entidad en la ruta.
        if (preg_match('#^/api/v\d+/(info|listar)$#', $path, $m) === 1) {
            return ['entity' => 'data-access', 'action' => (string) $m[1]];
        }

        return null;
    }
}
