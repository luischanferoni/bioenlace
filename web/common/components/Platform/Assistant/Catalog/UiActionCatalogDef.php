<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Ui\ApiV1HttpRoute;

/**
 * Factory de filas de catálogo UI (providers de dominio).
 */
final class UiActionCatalogDef
{
    /**
     * @param list<string> $keywords
     * @param list<string> $tags
     * @param array<string, mixed>|null $clientOpen
     * @param array<string, mixed>|null $providedParams
     * @return array<string, mixed>
     */
    public static function make(
        string $actionId,
        string $actionName,
        string $description,
        string $rbacRoute,
        array $keywords,
        string $entity,
        array $tags = ['clinical'],
        bool $uiJsonDescriptor = false,
        ?array $clientOpen = null,
        ?string $httpRouteTemplate = null,
        ?array $providedParams = null
    ): array {
        $httpRoute = ApiV1HttpRoute::normalize($httpRouteTemplate ?? $rbacRoute);

        $row = [
            'action_id' => $actionId,
            'action_name' => $actionName,
            'display_name' => $actionName,
            'description' => $description,
            'entity' => $entity,
            'route' => $httpRoute,
            'rbac_route' => $rbacRoute,
            'keywords' => $keywords,
            'synonyms' => [],
            'tags' => $tags,
            'parameters' => [
                'expected' => [],
                'provided' => $providedParams ?? [],
            ],
            'intent_semantics' => null,
            'flow_capable' => false,
        ];

        if ($uiJsonDescriptor) {
            $row['client_open'] = [
                'kind' => 'ui_json',
                'api' => [
                    'route' => $httpRoute,
                    'method' => 'GET|POST',
                ],
            ];
            $row['client_interaction'] = 'ui_asistente_json';
        } elseif ($clientOpen !== null) {
            $row['client_open'] = $clientOpen;
            $row['client_interaction'] = ($clientOpen['kind'] ?? '') === 'native'
                ? 'native_screen'
                : 'open';
        }

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @return array<string, mixed>|null
     */
    public static function findByActionId(array $definitions, string $actionId): ?array
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return null;
        }
        foreach ($definitions as $def) {
            if (trim((string) ($def['action_id'] ?? '')) === $actionId) {
                return $def;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $definitions
     */
    public static function httpRouteFromDefinitions(array $definitions, string $actionId): string
    {
        $def = self::findByActionId($definitions, $actionId);

        return $def !== null ? trim((string) ($def['route'] ?? '')) : '';
    }

    /**
     * @param list<array<string, mixed>> $definitions
     * @return array<string, mixed>|null
     */
    public static function clientOpenFromDefinitions(array $definitions, string $actionId): ?array
    {
        $def = self::findByActionId($definitions, $actionId);
        if ($def === null) {
            return null;
        }
        $clientOpen = $def['client_open'] ?? null;
        if (!is_array($clientOpen) || trim((string) ($clientOpen['kind'] ?? '')) === '') {
            return null;
        }

        return $clientOpen;
    }
}
