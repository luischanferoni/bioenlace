<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\UiActions\ActionMappingService;
use common\components\Platform\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Core\Permission\IntentAccessService;
use common\components\Platform\Assistant\Catalog\IntentShortcutMetadata;
use common\components\Platform\Core\Permission\IntentManifestMetadata;
use common\components\Platform\Core\Permission\IntentPermissionResolver;
use common\components\Platform\Ui\ApiV1HttpRoute;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Descubre intents conversacionales desde YAML en metadata de producto ({@see IntentSchemaPaths}).
 *
 * Fuente de verdad de “qué puede sugerir” el asistente (IntentEngine / shortcuts) cuando trabajamos con flows.
 */
final class YamlIntentCatalogService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function discoverAll(bool $useCache = true): array
    {
        $cache = Yii::$app->cache;
        // Cache key debe cambiar cuando cambian los YAML (keywords/rules/etc.).
        $cacheKeyBase = 'yaml_intents_catalog_v8';

        $base = IntentSchemaPaths::baseDir();
        $files = IntentSchemaPaths::discoverYamlFiles();
        if ($files === []) {
            // Usar categoría ya visible en producción (evitar filtros por categoría).
            Yii::warning('YamlIntentCatalogService: no se encontraron YAML intents en ' . $base, 'asistente');
        }
        $globCount = is_array($files) ? count($files) : 0;
        $sigParts = [];
        foreach ($files as $p) {
            if (is_string($p) && $p !== '' && is_file($p)) {
                $sigParts[] = basename($p) . ':' . (string) @filemtime($p);
            }
        }
        $cacheKey = $cacheKeyBase . '_' . md5(implode('|', $sigParts));
        if ($useCache && $cache) {
            $hit = $cache->get($cacheKey);
            if (is_array($hit)) {
                return $hit;
            }
        }
        $out = [];
        $loadedIds = [];
        $parseErrors = 0;
        $parseErrorSamples = [];

        foreach ($files as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                $parseErrors++;
                // Usar categoría ya visible en producción.
                $msg = 'YamlIntentCatalogService: YAML intent inválido ' . $path . ': ' . $e->getMessage();
                // No esconder la excepción: log con trace por canales difíciles de filtrar.
                Yii::error($msg . "\n" . $e->getTraceAsString(), 'application');
                error_log($msg);
                error_log($e->getTraceAsString());
                if (count($parseErrorSamples) < 5) {
                    $parseErrorSamples[] = $msg;
                }
                continue;
            }
            if (!is_array($data)) {
                $parseErrors++;
                $msg = 'YamlIntentCatalogService: YAML intent no es mapa ' . $path;
                Yii::error($msg, 'application');
                error_log($msg);
                if (count($parseErrorSamples) < 5) {
                    $parseErrorSamples[] = $msg;
                }
                continue;
            }
            $intentId = AssistantDraftNormalizer::scalarString($data['intent_id'] ?? '');
            if ($intentId === '') {
                // fallback: nombre de archivo
                $intentId = basename($path, '.yaml');
            }
            if ($intentId === '') {
                continue;
            }

            if (DataAccessCatalogIntentSupport::isGenericIntentId($intentId)) {
                continue;
            }

            $category = IntentSchemaPaths::categoryFromPath($path);
            $permission = IntentPermissionResolver::resolve($intentId, $data);

            $actionNameBase = AssistantDraftNormalizer::scalarString($data['action_name'] ?? '');
            if ($actionNameBase === '') {
                $actionNameBase = $intentId;
            }
            $operation = IntentManifestMetadata::resolveOperation($category, $data);
            $actionName = IntentManifestMetadata::formatDisplayActionName($actionNameBase, $operation);
            $desc = AssistantDraftNormalizer::scalarString($data['description'] ?? '');
            $rbacRoute = AssistantDraftNormalizer::scalarString($data['rbac_route'] ?? '');
            if ($rbacRoute !== '') {
                $rbacRoute = '/' . ltrim($rbacRoute, '/');
            }

            $kw = [];
            foreach (['keywords', 'synonyms', 'tags'] as $k) {
                if (isset($data[$k]) && is_array($data[$k])) {
                    foreach ($data[$k] as $v) {
                        if (is_string($v) && trim($v) !== '') {
                            $kw[] = trim($v);
                        }
                    }
                }
            }
            $kw[] = $intentId;
            $kw = array_values(array_unique($kw));

            $sem = null;
            if (isset($data['intent_semantics']) && is_array($data['intent_semantics'])) {
                $sem = self::normalizeIntentSemantics($data['intent_semantics']);
                if ($sem === []) {
                    $sem = null;
                }
            }

            $out[] = [
                'action_id' => $intentId,
                'action_name' => $actionName,
                'action_name_base' => $actionNameBase,
                'display_name' => $actionName,
                'description' => $desc,
                'route' => '', // flows se ejecutan vía /asistente/enviar con action_id
                'rbac_route' => $rbacRoute,
                'permission' => $permission,
                'category' => $category,
                'keywords' => $kw,
                'synonyms' => [],
                'tags' => [],
                'parameters' => [],
                'intent_semantics' => $sem,
                'shortcut_placements' => IntentShortcutMetadata::explicitPlacements($data),
                'shortcut_hidden' => IntentShortcutMetadata::isHidden($data),
                'has_subintents' => self::manifestHasSubintents($data),
                // Hint interno: intent ejecutable como flow YAML (no es `kind` del sobre HTTP).
                'flow_capable' => true,
            ];
            $loadedIds[] = $intentId;
        }

        // Si hay archivos YAML en disco pero no se pudo cargar ninguno, es un estado inválido:
        // NO continuar con catálogo vacío (oculta errores y degrada el producto).
        if ($globCount > 0 && $loadedIds === []) {
            $sampleFiles = array_slice(array_map('basename', array_values($files)), 0, 10);
            $detail = [
                'base' => $base,
                'glob_count' => $globCount,
                'parse_errors' => $parseErrors,
                'sample_files' => $sampleFiles,
                'error_samples' => $parseErrorSamples,
            ];
            $msg = 'YamlIntentCatalogService: glob encontró YAML pero no se cargó ninguno. ' . json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Log por canales difíciles de filtrar.
            Yii::error($msg, 'application');
            error_log($msg);

            throw new \RuntimeException($msg);
        }

        // Diagnóstico visible: lista acotada de intents detectados en disco (no depende de YII_DEBUG).
        $sample = array_slice($loadedIds, 0, 25);
        Yii::info(
            'YamlIntentCatalogService: intents cargados count=' . count($loadedIds)
            . ' glob_count=' . $globCount
            . ' parse_errors=' . $parseErrors
            . ' sample=' . json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . ' base=' . $base,
            'asistente'
        );

        if ($useCache && $cache) {
            $cache->set($cacheKey, $out, 300);
        }

        return $out;
    }

    /**
     * Intents visibles para el usuario: misma regla que la ejecución ({@see IntentAccessService}).
     *
     * @param array<int, array<string, mixed>> $items salida de {@see discoverAll}
     * @return array<int, array<string, mixed>>
     */
    public static function filterByRbac(array $items, int $userId): array
    {
        $out = [];
        foreach ($items as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $intentId = isset($flow['action_id']) ? AssistantDraftNormalizer::scalarString($flow['action_id']) : '';
            if ($intentId === '') {
                continue;
            }
            if (IntentAccessService::userCanExecuteIntent($userId, $intentId)) {
                $out[] = $flow;
            }
        }

        return $out;
    }

    /**
     * Atajos: solo intents con grant explícito ({@see IntentAccessService::userHasIntentGrant}).
     *
     * @param array<int, array<string, mixed>> $items salida de {@see discoverAll}
     * @return array<int, array<string, mixed>>
     */
    public static function filterByIntentGrant(array $items, int $userId): array
    {
        $out = [];
        foreach ($items as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $intentId = isset($flow['action_id']) ? AssistantDraftNormalizer::scalarString($flow['action_id']) : '';
            if ($intentId === '' || !self::intentExists($intentId)) {
                continue;
            }
            if (IntentAccessService::userHasIntentGrant($userId, $intentId)) {
                $out[] = $flow;
            }
        }

        return $out;
    }

    public static function userIdCanPermissionKey(int $userId, string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);
        if ($permissionKey === '') {
            return false;
        }
        if (strncmp($permissionKey, '/api/', 5) === 0) {
            return ActionMappingService::userIdCanAccessRoute($userId, $permissionKey);
        }

        return IntentAccessService::userCanExecuteIntent($userId, $permissionKey);
    }

    /**
     * @param array<string, mixed> $sem
     * @return array{summary?: string, capabilities?: list<string>}
     */
    private static function normalizeIntentSemantics(array $sem): array
    {
        $out = [];
        $summary = trim((string) ($sem['summary'] ?? ''));
        if ($summary !== '') {
            $out['summary'] = $summary;
        }
        $capabilities = [];
        foreach ($sem['capabilities'] ?? [] as $cap) {
            if (is_string($cap) && trim($cap) !== '') {
                $capabilities[] = trim($cap);
            }
        }
        if ($capabilities !== []) {
            $out['capabilities'] = array_values(array_unique($capabilities));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function manifestHasSubintents(array $data): bool
    {
        $subintents = $data['subintents'] ?? null;
        if (!is_array($subintents) || $subintents === []) {
            return false;
        }
        foreach ($subintents as $sub) {
            if (is_array($sub) && trim((string) ($sub['id'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function intentExists(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }
        if (DataAccessCatalogIntentSupport::isGenericIntentId($intentId)) {
            return false;
        }

        return IntentSchemaPaths::resolveFileForIntentId($intentId) !== null;
    }

    /**
     * Rutas HTTP POST de cierre declarativo (`flow_submit` en intents YAML).
     *
     * @return list<string> paths normalizados `/api/v1/...`
     */
    public static function postOnlyFlowClosureRoutes(): array
    {
        $files = IntentSchemaPaths::discoverYamlFiles();
        $routes = [];
        foreach ($files as $path) {
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            if (!is_array($data)) {
                continue;
            }
            $flowSubmit = $data['flow_submit'] ?? null;
            if (!is_array($flowSubmit)) {
                continue;
            }
            $rbac = AssistantDraftNormalizer::scalarString($data['rbac_route'] ?? '');
            if ($rbac === '') {
                continue;
            }
            $routes[] = ApiV1HttpRoute::normalize($rbac);
        }

        return array_values(array_unique(array_filter($routes)));
    }
}

