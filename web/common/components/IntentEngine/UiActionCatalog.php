<?php

namespace common\components\IntentEngine;

use Yii;
use webvimark\modules\UserManagement\models\User;
use common\components\IntentCatalog\IntentCatalogService;
use common\components\Actions\ActionDiscoveryService;

/**
 * Catálogo de UIs disponibles para un usuario (templates JSON existentes + RBAC).
 */
final class UiActionCatalog
{
    /** @var UiActionCatalogItem[] */
    public array $items;

    /** @var array<string, UiActionCatalogItem> */
    public array $byActionId;

    /**
     * @param UiActionCatalogItem[] $items
     * @param array<string, UiActionCatalogItem> $byActionId
     */
    private function __construct(array $items, array $byActionId)
    {
        $this->items = $items;
        $this->byActionId = $byActionId;
    }

    public static function forUser(int $userId): self
    {
        $raw = IntentCatalogService::getAvailableUiForUser($userId, true);
        $items = [];
        $byId = [];

        foreach ($raw as $a) {
            $actionId = isset($a['action_id']) ? (string) $a['action_id'] : '';
            if ($actionId === '') {
                continue;
            }

            $display = (string) ($a['action_name'] ?? $a['display_name'] ?? '');
            if ($display === '' || strncmp($display, 'RBAC:', 5) === 0) {
                $display = $actionId;
            }
            $desc = (string) ($a['description'] ?? '');
            $entity = isset($a['entity']) ? (string) $a['entity'] : null;
            $route = (string) ($a['route'] ?? '');

            $kw = [];
            foreach (['keywords', 'synonyms', 'tags'] as $k) {
                if (isset($a[$k]) && is_array($a[$k])) {
                    foreach ($a[$k] as $v) {
                        if (is_string($v) && trim($v) !== '') {
                            $kw[] = trim($v);
                        }
                    }
                }
            }
            $kw = array_values(array_unique($kw));

            $params = [
                'expected' => $a['parameters'] ?? [],
                'provided' => [],
            ];

            $item = new UiActionCatalogItem(
                $actionId,
                $display,
                $desc,
                $entity !== '' ? $entity : null,
                $route,
                $kw,
                $params
            );

            $items[] = $item;
            $byId[$actionId] = $item;
        }

        // UIs nativas: frontend/controllers (por defecto incluidas; excluir con @no_intent_catalog)
        foreach (self::discoverNativeFrontendItems($userId) as $item) {
            if (!isset($byId[$item->action_id])) {
                $items[] = $item;
                $byId[$item->action_id] = $item;
            }
        }

        return new self($items, $byId);
    }

    /**
     * UIs nativas: en web se abren por URL (route), en móvil por screen_id (client_open).
     *
     * @return UiActionCatalogItem[]
     */
    private static function discoverNativeFrontendItems(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $defs = ActionDiscoveryService::discoverFrontendUiDefinitions(true);
        if ($defs === []) {
            return [];
        }

        $out = [];
        foreach ($defs as $d) {
            $controller = (string) ($d['controller'] ?? '');
            $action = (string) ($d['action'] ?? '');
            if ($controller === '' || $action === '') {
                continue;
            }

            // Permiso RBAC sobre ruta web nativa (el usuario puede asignar permisos a estas URLs).
            $rbacRoute = '/' . $controller . '/' . $action;
            if ($action === 'index') {
                $rbacRoute = '/' . $controller . '/index';
            }

            if (!self::userCanRoute($userId, $rbacRoute)) {
                continue;
            }

            // Ruta web canónica: /<controller> o /<controller>/<action> si no es index.
            $webPath = '/' . rawurlencode($controller);
            if ($action !== 'index') {
                $webPath .= '/' . rawurlencode($action);
            }

            $actionId = 'native.' . strtolower($controller . '.' . $action);
            $display = (string) ($d['action_name'] ?? $d['display_name'] ?? '');
            if ($display === '' || strncmp($display, 'RBAC:', 5) === 0) {
                $display = $controller . '/' . $action;
            }

            $kw = [];
            foreach (['keywords', 'synonyms', 'tags'] as $k) {
                if (isset($d[$k]) && is_array($d[$k])) {
                    foreach ($d[$k] as $v) {
                        if (is_string($v) && trim($v) !== '') {
                            $kw[] = trim($v);
                        }
                    }
                }
            }
            $kw[] = $controller;
            $kw[] = $action;
            $kw = array_values(array_unique(array_filter($kw)));

            $clientOpen = [
                'kind' => 'ui_native',
                // En móvil se usa screen_id; en web se usa path.
                'screen_id' => strtolower($controller . '.' . $action),
                'web' => ['path' => $webPath],
                'mobile' => ['screen_id' => strtolower($controller . '.' . $action)],
            ];

            $out[] = new UiActionCatalogItem(
                $actionId,
                $display,
                (string) ($d['description'] ?? ''),
                null,
                $webPath,
                $kw,
                ['expected' => $d['parameters'] ?? [], 'provided' => []],
                $clientOpen,
                'ui_asistente_native'
            );
        }

        return $out;
    }

    private static function userCanRoute(int $userId, string $route): bool
    {
        // En API v1 con JWT, AuthHelper::updatePermissions ya se ejecuta en authenticate() del bearer auth.
        // webvimark resuelve canRoute en base a rutas cargadas para el user.
        $route = '/' . ltrim($route, '/');

        // Superadmin.
        $u = User::findOne($userId);
        if ($u && (int) $u->superadmin === 1) {
            return true;
        }

        return User::canRoute($route);
    }
}

