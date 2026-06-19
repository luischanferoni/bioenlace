<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;

/**
 * Convierte entradas del catálogo de intents al formato legacy de acciones descubiertas.
 */
final class IntentCatalogActionMapper
{
    /**
     * @param array<string, mixed> $flow salida de {@see YamlIntentCatalogService} o providers
     * @return array<string, mixed>
     */
    public static function toDiscoveredAction(array $flow): array
    {
        $intentId = trim((string) ($flow['action_id'] ?? ''));
        $display = trim((string) ($flow['action_name'] ?? $flow['display_name'] ?? $intentId));
        $route = trim((string) ($flow['rbac_route'] ?? $flow['route'] ?? ''));
        if ($route !== '') {
            $route = '/' . ltrim($route, '/');
        }

        [$controller, $action] = self::parseApiRouteSegments($route);
        $keywords = [];
        foreach (['keywords', 'synonyms', 'tags'] as $key) {
            if (!isset($flow[$key]) || !is_array($flow[$key])) {
                continue;
            }
            foreach ($flow[$key] as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $keywords[] = trim($value);
                }
            }
        }

        return [
            'route' => $route,
            'display_name' => $display !== '' ? $display : $intentId,
            'description' => trim((string) ($flow['description'] ?? '')),
            'controller' => $controller,
            'action' => $action,
            'action_id' => $intentId,
            'tags' => array_values(array_unique($keywords)),
            'parameters' => is_array($flow['parameters'] ?? null) ? $flow['parameters'] : [],
            'entity' => trim((string) ($flow['entity'] ?? '')),
            'intent_semantics' => is_array($flow['intent_semantics'] ?? null) ? $flow['intent_semantics'] : null,
        ];
    }

    /**
     * @return array{0: string, 1: string} controller, action
     */
    private static function parseApiRouteSegments(string $route): array
    {
        $route = AllowedRoutesResolver::apiHttpPathToPermissionRoute('/' . ltrim($route, '/'));
        if (preg_match('#^/api/([^/]+)/([^/]+)#', $route, $matches) !== 1) {
            return ['', ''];
        }

        return [(string) $matches[1], (string) $matches[2]];
    }
}
