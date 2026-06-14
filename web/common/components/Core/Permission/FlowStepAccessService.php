<?php

namespace common\components\Core\Permission;

use common\components\Assistant\UiActions\ActionMappingService;

/**
 * Autorización de pasos open_ui intermedios: heredan el permiso del intent padre.
 */
final class FlowStepAccessService
{
    public const HEADER_FLOW_INTENT_ID = 'X-Flow-Intent-Id';

    /** @var array<string, list<string>>|null api route → action_ids */
    private static ?array $routeToActionIds = null;

    /**
     * ¿Puede acceder a la ruta API como paso de un flow cuyo intent padre está autorizado?
     */
    public function canAccessViaParentIntent(int $userId, string $apiRoute, ?string $flowIntentId = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $apiRoute = $this->normalizeApiRoute($apiRoute);
        if ($apiRoute === '') {
            return false;
        }

        if ($flowIntentId !== null && trim($flowIntentId) !== '') {
            return $this->canAccessForIntentAndRoute($userId, trim($flowIntentId), $apiRoute);
        }

        $actionIds = $this->actionIdsForRoute($apiRoute);
        if ($actionIds === []) {
            return false;
        }

        foreach ($actionIds as $actionId) {
            foreach (IntentManifestIndex::parentIntentsForOpenUiAction($actionId) as $parent) {
                if ($this->userCanAccessIntent($userId, $parent)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array{intent_id: string, step_id: string, permission: string, rbac_route: string} $parent
     */
    private function userCanAccessIntent(int $userId, array $parent): bool
    {
        $intentId = trim((string) ($parent['intent_id'] ?? ''));
        if ($intentId !== '') {
            return \common\components\Assistant\Catalog\YamlIntentCatalogService::userIdCanPermissionKey($userId, $intentId);
        }

        $rbacRoute = trim((string) ($parent['rbac_route'] ?? ''));
        if ($rbacRoute !== '') {
            return ActionMappingService::userIdCanAccessRoute($userId, $rbacRoute);
        }

        return false;
    }

    private function canAccessForIntentAndRoute(int $userId, string $flowIntentId, string $apiRoute): bool
    {
        $meta = IntentManifestIndex::get($flowIntentId);
        if ($meta === null) {
            return false;
        }

        if (!$this->userCanAccessIntent($userId, [
            'intent_id' => $flowIntentId,
            'step_id' => '',
            'rbac_route' => (string) ($meta['rbac_route'] ?? ''),
        ])) {
            return false;
        }

        $actionIds = $this->actionIdsForRoute($apiRoute);
        if ($actionIds === []) {
            return false;
        }

        $allowed = [];
        foreach ($meta['open_ui_steps'] ?? [] as $step) {
            if (!is_array($step)) {
                continue;
            }
            $aid = trim((string) ($step['action_id'] ?? ''));
            if ($aid !== '') {
                $allowed[$aid] = true;
            }
        }

        foreach ($actionIds as $actionId) {
            if (isset($allowed[$actionId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function actionIdsForRoute(string $apiRoute): array
    {
        $this->ensureRouteIndex();
        $apiRoute = $this->normalizeApiRoute($apiRoute);

        return self::$routeToActionIds[$apiRoute] ?? [];
    }

    private function ensureRouteIndex(): void
    {
        if (self::$routeToActionIds !== null) {
            return;
        }

        self::$routeToActionIds = [];
        foreach (IntentManifestIndex::all() as $meta) {
            foreach ($meta['open_ui_steps'] ?? [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $actionId = trim((string) ($step['action_id'] ?? ''));
                if ($actionId === '' || strpos($actionId, '.') === false) {
                    continue;
                }
                [$entity, $action] = explode('.', $actionId, 2);
                $route = '/api/' . $entity . '/' . $action;
                self::$routeToActionIds[$route][] = $actionId;
            }
        }

        foreach (self::$routeToActionIds as $route => $ids) {
            self::$routeToActionIds[$route] = array_values(array_unique($ids));
        }
    }

    private function normalizeApiRoute(string $route): string
    {
        $route = '/' . ltrim(trim($route), '/');
        if (preg_match('#^/api/v\d+/#', $route) === 1) {
            $route = preg_replace('#^/api/v\d+/#', '/api/', $route, 1);
        }

        return $route;
    }
}
