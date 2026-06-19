<?php

namespace common\components\Platform\Ui;

use common\components\Platform\Core\Product\UiJsonDomainMetadata;

/**
 * Resolución de carpetas bajo `views/json/` según metadata de producto.
 */
final class UiJsonDomain
{
    public static function forEntity(string $entity): ?string
    {
        return UiJsonDomainMetadata::domainForEntity($entity);
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
        $clinicalPrefix = UiJsonDomainMetadata::clinicalActionIdPrefix();
        if ($parts[0] === $clinicalPrefix && count($parts) >= 3) {
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

        $aliasAction = UiJsonDomainMetadata::templateAliasAction($parsed['entity'], $parsed['action']);
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

        $folderEntity = UiJsonDomainMetadata::templateFolderForEntity($entity);
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

        $clinicalPrefix = preg_quote(UiJsonDomainMetadata::clinicalActionIdPrefix(), '#');

        if (preg_match('#^/api/v\d+/' . $clinicalPrefix . '/([\\w-]+)/(?:\d+|\{[\w-]+\})/([\\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        if (preg_match('#^/api/v\d+/' . $clinicalPrefix . '/([\\w-]+)/([\\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        if (preg_match('#^/api/v\d+/([\\w-]+)/([\\w-]+)$#', $path, $m) === 1) {
            return ['entity' => strtolower((string) $m[1]), 'action' => (string) $m[2]];
        }

        if (preg_match('#^/api/v\d+/(info|listar)$#', $path, $m) === 1) {
            return ['entity' => 'data-access', 'action' => (string) $m[1]];
        }

        return null;
    }
}
