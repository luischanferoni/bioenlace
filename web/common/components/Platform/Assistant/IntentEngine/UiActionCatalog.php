<?php

namespace common\components\Platform\Assistant\IntentEngine;

use Yii;
use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Assistant\UiActions\ActionDiscoveryService;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Assistant\UiActions\AllowedRoutesResolver;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry;
use common\components\Platform\Assistant\Catalog\IntentCatalogService;

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

    /**
     * @param UiActionCatalogItem[] $items
     * @param array<string, UiActionCatalogItem> $byActionId
     */
    public static function fromItems(array $items, array $byActionId): self
    {
        return new self($items, $byActionId);
    }

    public static function forUser(int $userId): self
    {
        $raw = IntentCatalogService::getAvailableUiForUser($userId, true);
        $items = [];
        $byId = [];

        foreach ($raw as $a) {
            $actionId = AssistantDraftNormalizer::scalarString($a['action_id'] ?? '');
            if ($actionId === '') {
                continue;
            }

            $display = AssistantDraftNormalizer::scalarString($a['action_name'] ?? $a['display_name'] ?? '');
            if ($display === '' || strncmp($display, 'RBAC:', 5) === 0) {
                $display = $actionId;
            }
            $desc = AssistantDraftNormalizer::scalarString($a['description'] ?? '');
            $entityRaw = AssistantDraftNormalizer::scalarString($a['entity'] ?? '');
            $entity = $entityRaw !== '' ? $entityRaw : null;
            $route = AssistantDraftNormalizer::scalarString($a['route'] ?? '');

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

            $sem = null;
            if (isset($a['intent_semantics']) && is_array($a['intent_semantics'])) {
                $sem = $a['intent_semantics'];
            }

            $spaPresentation = null;
            if (isset($a['spa_presentation']) && is_string($a['spa_presentation'])) {
                $sp = strtolower(trim($a['spa_presentation']));
                if ($sp === 'inline' || $sp === 'fullscreen') {
                    $spaPresentation = $sp;
                }
            }

            $item = new UiActionCatalogItem(
                $actionId,
                $display,
                $desc,
                $entity !== '' ? $entity : null,
                $route,
                $kw,
                $params,
                $sem,
                null,
                null,
                $spaPresentation
            );

            $items[] = $item;
            $byId[$actionId] = $item;
        }

        foreach (UiActionCatalogProviderRegistry::forUserFromProviders($userId) as $a) {
            $actionId = AssistantDraftNormalizer::scalarString($a['action_id'] ?? '');
            if ($actionId === '' || isset($byId[$actionId])) {
                continue;
            }
            $display = AssistantDraftNormalizer::scalarString($a['action_name'] ?? $a['display_name'] ?? '', $actionId);
            $clientOpen = isset($a['client_open']) && is_array($a['client_open']) ? $a['client_open'] : null;
            $clientInteraction = AssistantDraftNormalizer::scalarString($a['client_interaction'] ?? '');
            $clientInteraction = $clientInteraction !== '' ? $clientInteraction : null;
            $entityDefault = AssistantDraftNormalizer::scalarString($a['entity'] ?? '');
            $item = new UiActionCatalogItem(
                $actionId,
                $display,
                AssistantDraftNormalizer::scalarString($a['description'] ?? ''),
                $entityDefault !== '' ? $entityDefault : null,
                AssistantDraftNormalizer::scalarString($a['route'] ?? ''),
                is_array($a['keywords'] ?? null) ? array_values($a['keywords']) : [],
                is_array($a['parameters'] ?? null) ? $a['parameters'] : ['expected' => [], 'provided' => []],
                is_array($a['intent_semantics'] ?? null) ? $a['intent_semantics'] : null,
                $clientOpen,
                $clientInteraction,
                null
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

        self::enrichDataAccessIntentKeywords($items, $byId, $userId);

        return new self($items, $byId);
    }

    /**
     * Keywords NL de métricas autorizadas (catálogo DataAccess), sin valores de atributos.
     *
     * @param UiActionCatalogItem[] $items
     * @param array<string, UiActionCatalogItem> $byId
     */
    private static function enrichDataAccessIntentKeywords(array &$items, array &$byId, int $userId): void
    {
        $discovery = new \common\components\Platform\Core\DataAccess\DataAccessMetricDiscoveryService();
        $map = [
            'data-access.info' => \common\components\Platform\Core\DataAccess\DataAccessMetricDiscoveryService::CHANNEL_INFO,
            'data-access.listar' => \common\components\Platform\Core\DataAccess\DataAccessMetricDiscoveryService::CHANNEL_LISTAR,
        ];
        foreach ($map as $actionId => $channel) {
            if (!isset($byId[$actionId])) {
                continue;
            }
            $item = $byId[$actionId];
            $extra = $discovery->assistantKeywordsForUser($userId, $channel);
            if ($extra === []) {
                continue;
            }
            $merged = array_values(array_unique(array_merge($item->keywords, $extra)));
            $updated = new UiActionCatalogItem(
                $item->action_id,
                $item->display_name,
                $item->description,
                $item->entity,
                $item->route,
                $merged,
                $item->parameters,
                $item->intent_semantics,
                $item->client_open,
                $item->client_interaction,
                $item->spa_presentation
            );
            $byId[$actionId] = $updated;
            foreach ($items as $i => $it) {
                if ($it->action_id === $actionId) {
                    $items[$i] = $updated;
                    break;
                }
            }
        }

        if (isset($byId['data-access.editar'])) {
            $editDiscovery = new \common\components\Platform\Core\DataAccess\DataAccessEditDiscoveryService();
            $extraEdit = $editDiscovery->assistantKeywordsForUser($userId);
            if ($extraEdit !== []) {
                $item = $byId['data-access.editar'];
                $merged = array_values(array_unique(array_merge($item->keywords, $extraEdit)));
                $updated = new UiActionCatalogItem(
                    $item->action_id,
                    $item->display_name,
                    $item->description,
                    $item->entity,
                    $item->route,
                    $merged,
                    $item->parameters,
                    $item->intent_semantics,
                    $item->client_open,
                    $item->client_interaction,
                    $item->spa_presentation
                );
                $byId['data-access.editar'] = $updated;
                foreach ($items as $i => $it) {
                    if ($it->action_id === 'data-access.editar') {
                        $items[$i] = $updated;
                        break;
                    }
                }
            }
        }
    }

    /**
     * UIs nativas: en web se abren por URL (route), en móvil por screen_id (client_open).
     *
     * @return UiActionCatalogItem[]
     */
    private static function discoverNativeFrontendItems(int $userId): array
    {
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

            if (!self::userCanNativeFrontendWeb($userId, $controller, $action)) {
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

            // Web: `native` siempre con path canónico o `@native_ui_path` override.
            $clientOpen = self::buildNativeWebClientOpen($d, $controller, $action);

            $out[] = new UiActionCatalogItem(
                $actionId,
                $display,
                (string) ($d['description'] ?? ''),
                null,
                $webPath,
                $kw,
                ['expected' => $d['parameters'] ?? [], 'provided' => []],
                null,
                $clientOpen,
                'ui_asistente_native'
            );
        }

        return $out;
    }

    /**
     * Construye client_open para UIs web nativas consumidas por el shell SPA.
     * Path por defecto: ruta canónica Yii de la acción (HTML sin layout); override con `@native_ui_path`.
     *
     * @return array<string, mixed>
     */
    private static function buildNativeWebClientOpen(array $def, string $controller, string $action): array
    {
        $mobileScreenId = isset($def['mobile_screen_id']) && is_string($def['mobile_screen_id']) && $def['mobile_screen_id'] !== ''
            ? (string) $def['mobile_screen_id']
            : strtolower($controller . '.' . $action);

        $uiPath = ActionDiscoveryService::resolveNativeWebFetchPath($def, $controller, $action);

        $presentation = isset($def['spa_presentation']) && is_string($def['spa_presentation'])
            ? strtolower(trim($def['spa_presentation']))
            : 'inline';
        if ($presentation !== 'inline' && $presentation !== 'fullscreen') {
            $presentation = 'inline';
        }

        $css = isset($def['native_assets_css']) && is_array($def['native_assets_css']) ? $def['native_assets_css'] : [];
        $js = isset($def['native_assets_js']) && is_array($def['native_assets_js']) ? $def['native_assets_js'] : [];

        $out = [
            'kind' => 'native',
            'web' => [
                'path' => $uiPath,
            ],
            'mobile' => [
                'screen_id' => $mobileScreenId,
            ],
        ];
        if ($css !== [] || $js !== []) {
            $out['assets'] = [
                'css' => array_values(array_filter($css)),
                'js' => array_values(array_filter($js)),
            ];
        }

        return $out;
    }

    /**
     * RBAC para descubiertas de `frontend/controllers`: varias formas de ruta en webvimark ({@see AllowedRoutesResolver::nativeFrontendWebRbacRouteCandidates}).
     */
    private static function userCanNativeFrontendWeb(int $userId, string $controller, string $action): bool
    {
        $userId = (int) $userId;
        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        foreach (AllowedRoutesResolver::nativeFrontendWebRbacRouteCandidates($controller, $action) as $rbacRoute) {
            if (BioenlaceAccessChecker::userHasRoute($userId, $rbacRoute)) {
                return true;
            }
        }

        return false;
    }
}

