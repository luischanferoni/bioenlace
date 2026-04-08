<?php

namespace common\components\Services\Actions;

use common\components\Actions\ActionDiscoveryService;
use common\components\Actions\AllowedRoutesResolver;
use common\components\IntentCatalog\IntentCatalogService;
use webvimark\modules\UserManagement\models\User;

/**
 * Atajos de inicio: subconjunto ordenado de **UIs**.
 *
 * Importante:
 * - UI en API = descriptor JSON bajo `/api/v1/ui/<entidad>/<accion>`.
 * - Endpoints de dominio (turnos/agenda/etc.) no son UI; esta lista intenta apuntar a `/ui/`.
 */
final class CommonActionsService
{
    public const DEFAULT_LIMIT = 12;

    /**
     * @return list<array{route: string, name: string, description: string, action_id: string|null}>
     */
    public static function getFormattedForUser(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        // UIs nativas (frontend/controllers): se incluyen todas por defecto y se excluyen manualmente con @no_intent_catalog.
        // Se devuelven como "screens" nativas (no /api/v1/ui/...), con client_open.
        $native = self::nativeFrontendShortcuts();

        $available = IntentCatalogService::getAvailableUiForUser($userId, true);
        $ordered = self::prioritizeForCommonShortcuts($available);

        $out = [];
        foreach ($native as $n) {
            $out[] = $n;
        }
        foreach ($ordered as $action) {
            $name = !empty($action['action_name'])
                ? (string) $action['action_name']
                : (string) ($action['display_name'] ?? $action['route'] ?? '');

            $out[] = [
                'route' => (string) ($action['route'] ?? ''),
                'name' => $name,
                'description' => (string) ($action['description'] ?? ''),
                'action_id' => isset($action['action_id']) ? (string) $action['action_id'] : null,
            ];
        }

        return array_slice($out, 0, $limit);
    }

    /**
     * @return list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>
     */
    private static function nativeFrontendShortcuts(): array
    {
        $defs = ActionDiscoveryService::discoverFrontendUiDefinitions(true);
        if ($defs === []) {
            return [];
        }

        $out = [];
        foreach ($defs as $d) {
            $controller = (string) ($d['controller'] ?? '');
            $action = (string) ($d['action'] ?? '');
            $actionId = isset($d['action_id']) ? (string) $d['action_id'] : null;
            if ($controller === '' || $action === '') {
                continue;
            }

            // Filtrado RBAC: probar rutas como en BD (p. ej. /agenda/crear y /frontend/agenda/crear).
            $allowedNative = false;
            foreach (AllowedRoutesResolver::nativeFrontendWebRbacRouteCandidates($controller, $action) as $rbacRoute) {
                if (User::canRoute($rbacRoute)) {
                    $allowedNative = true;
                    break;
                }
            }
            if (!$allowedNative) {
                continue;
            }

            // Ruta nativa web: /<controller>[/<action>] (index => /<controller>)
            $path = '/' . rawurlencode($controller);
            if ($action !== 'index') {
                $path .= '/' . rawurlencode($action);
            }

            $name = (string) ($d['action_name'] ?? $d['display_name'] ?? '');
            if ($name === '' || strncmp($name, 'RBAC:', 5) === 0) {
                $name = $controller . '/' . $action;
            }

            $fetchPath = ActionDiscoveryService::resolveNativeWebFetchPath($d, $controller, $action);
            $presentation = isset($d['spa_presentation']) && is_string($d['spa_presentation'])
                ? strtolower(trim($d['spa_presentation']))
                : 'inline';
            if ($presentation !== 'inline' && $presentation !== 'fullscreen') {
                $presentation = 'inline';
            }
            $mobileScreenId = isset($d['mobile_screen_id']) && is_string($d['mobile_screen_id']) && $d['mobile_screen_id'] !== ''
                ? (string) $d['mobile_screen_id']
                : strtolower($controller . '.' . $action);
            $css = isset($d['native_assets_css']) && is_array($d['native_assets_css']) ? $d['native_assets_css'] : [];
            $js = isset($d['native_assets_js']) && is_array($d['native_assets_js']) ? $d['native_assets_js'] : [];

            $co = [
                'kind' => 'native',
                'presentation' => $presentation,
                'web' => ['path' => $fetchPath],
                'mobile' => ['screen_id' => $mobileScreenId],
            ];
            if ($css !== [] || $js !== []) {
                $co['assets'] = [
                    'css' => array_values(array_filter($css)),
                    'js' => array_values(array_filter($js)),
                ];
            }

            $out[] = [
                'route' => $path,
                'name' => $name,
                'description' => (string) ($d['description'] ?? ''),
                'action_id' => $actionId,
                'client_open' => $co,
                'client_interaction' => 'ui_asistente_native',
            ];
        }

        return $out;
    }

    /**
     * Prioriza acciones sin parámetros obligatorios (más aptas como atajo), luego el resto; orden estable por nombre.
     *
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    private static function prioritizeForCommonShortcuts(array $actions): array
    {
        $noRequired = [];
        $withRequired = [];

        foreach ($actions as $action) {
            $hasRequired = false;
            if (!empty($action['parameters']) && is_array($action['parameters'])) {
                foreach ($action['parameters'] as $p) {
                    if (!empty($p['required'])) {
                        $hasRequired = true;
                        break;
                    }
                }
            }
            if ($hasRequired) {
                $withRequired[] = $action;
            } else {
                $noRequired[] = $action;
            }
        }

        $sortFn = static function (array $a, array $b): int {
            $na = (string) ($a['display_name'] ?? $a['route'] ?? '');
            $nb = (string) ($b['display_name'] ?? $b['route'] ?? '');

            return strcasecmp($na, $nb);
        };

        usort($noRequired, $sortFn);
        usort($withRequired, $sortFn);

        return array_merge($noRequired, $withRequired);
    }
}
