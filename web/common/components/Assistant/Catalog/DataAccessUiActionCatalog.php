<?php

namespace common\components\Assistant\Catalog;

use common\components\Ui\ApiV1HttpRoute;

/**
 * Acciones API staff DataAccess ({@see /api/info}, {@see /api/listar}).
 *
 * Rutas HTTP: `/api/v1/info`, `/api/v1/listar`; RBAC: `/api/info`, `/api/listar`.
 */
final class DataAccessUiActionCatalog
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            self::def(
                'data-access.info',
                'Consulta informativa staff',
                'Ejecuta una métrica registrada (aggregate/grouped) y devuelve ui_json informativo.',
                '/api/info',
                ['info staff', 'conteo', 'métrica', 'resumen datos']
            ),
            self::def(
                'data-access.listar',
                'Listado',
                'Ejecuta una métrica en modo filas y devuelve una tabla.',
                '/api/listar',
                ['listar', 'listado', 'mostrar listado', 'ver listado']
            ),
        ];

        return self::$definitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        return YamlIntentCatalogService::filterByRbac(self::discoverAll(), $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definitionByActionId(string $actionId): ?array
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return null;
        }
        foreach (self::discoverAll() as $def) {
            if (trim((string) ($def['action_id'] ?? '')) === $actionId) {
                return $def;
            }
        }

        return null;
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        $def = self::definitionByActionId($actionId);

        return $def !== null ? trim((string) ($def['route'] ?? '')) : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function clientOpenForActionId(string $actionId): ?array
    {
        $def = self::definitionByActionId($actionId);
        if ($def === null) {
            return null;
        }
        $co = $def['client_open'] ?? null;

        return is_array($co) ? $co : null;
    }

    /**
     * @param list<string> $keywords
     * @return array<string, mixed>
     */
    private static function def(
        string $actionId,
        string $actionName,
        string $description,
        string $rbacRoute,
        array $keywords
    ): array {
        return [
            'action_id' => $actionId,
            'action_name' => $actionName,
            'display_name' => $actionName,
            'description' => $description,
            'entity' => 'DataAccess',
            'route' => ApiV1HttpRoute::normalize($rbacRoute),
            'rbac_route' => $rbacRoute,
            'keywords' => $keywords,
            'synonyms' => [],
            'tags' => ['staff', 'metrics', 'data-access'],
            'parameters' => [
                'expected' => [
                    'metric_id' => ['description' => 'ID de métrica en attribute_groups_v1.yaml'],
                ],
                'provided' => [],
            ],
            'intent_semantics' => null,
            'flow_capable' => false,
            'client_open' => [
                'kind' => 'ui_json',
                'api' => [
                    'route' => ApiV1HttpRoute::normalize($rbacRoute),
                    'method' => 'GET|POST',
                ],
            ],
        ];
    }
}
