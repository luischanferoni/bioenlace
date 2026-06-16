<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\DataAccess\DataAccessGenericChannelRetirement;
use common\components\Platform\Core\DataAccess\EditSurfaceAuthorizationService;
use common\components\Platform\Core\DataAccess\PermissionContext;
use common\components\Platform\Ui\ApiV1HttpRoute;
use Yii;

/**
 * Acciones API staff DataAccess ({@see /api/info}, {@see /api/listar}).
 *
 * Rutas HTTP: `/api/v1/info`, `/api/v1/listar`; RBAC: `/api/info`, `/api/listar`.
 */
final class DataAccessUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Definiciones runtime (open_ui, rutas HTTP). Incluye genéricos aunque estén retirados del catálogo NL.
     *
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
            self::def(
                'data-access.editar',
                'Edición dispersa',
                'Modificar datos por superficie y aspectos autorizados (permiso write por grupo).',
                '/api/editar',
                [
                    'editar',
                    'modificar',
                    'actualizar',
                    'cambiar',
                    'modificar agenda',
                    'editar agenda',
                    'agenda profesional',
                    'horarios profesional',
                    'configurar agenda',
                ]
            ),
        ];

        return self::$definitions;
    }

    /**
     * Entradas sugeribles al asistente (vacío cuando fase 3 migró todas las métricas/superficies).
     *
     * @return list<array<string, mixed>>
     */
    public static function discoverCatalogEntries(): array
    {
        if (DataAccessGenericChannelRetirement::areGenericChannelsRetired()) {
            return [];
        }

        return self::discoverAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId): array
    {
        $items = YamlIntentCatalogService::filterByRbac(self::discoverCatalogEntries(), $userId);
        if (!self::userHasEditableSurfaces($userId)) {
            $items = array_values(array_filter(
                $items,
                static fn (array $def): bool => trim((string) ($def['action_id'] ?? '')) !== 'data-access.editar'
            ));
        }

        return $items;
    }

    private static function userHasEditableSurfaces(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $roles = [];
        if (Yii::$app->has('authManager')) {
            $assigned = Yii::$app->authManager->getRolesByUser($userId);
            if (is_array($assigned)) {
                $roles = array_keys($assigned);
            }
        }

        $ctx = new PermissionContext($userId, $roles);

        return (new EditSurfaceAuthorizationService())->userHasAnyWriteGrantForEdit($ctx);
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
                    'metric_id' => ['description' => 'ID de métrica en data-access-config'],
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
