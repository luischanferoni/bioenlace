<?php

namespace common\components\Assistant\Catalog;

use common\components\Assistant\UiActions\ActionMappingService;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Descubre intents conversacionales desde YAML (`SubIntentEngine/schemas/intents/*.yaml`).
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
        $cacheKeyBase = 'yaml_intents_catalog_v3';
        if ($useCache && $cache) {
            $hit = $cache->get($cacheKeyBase);
            if (is_array($hit)) {
                return $hit;
            }
        }

        $base = dirname(__DIR__) . '/SubIntentEngine/schemas/intents';
        $files = glob($base . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
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
            $intentId = isset($data['intent_id']) ? trim((string) $data['intent_id']) : '';
            if ($intentId === '') {
                // fallback: nombre de archivo
                $intentId = basename($path, '.yaml');
            }
            if ($intentId === '') {
                continue;
            }

            $actionName = isset($data['action_name']) ? trim((string) $data['action_name']) : '';
            if ($actionName === '') {
                $actionName = $intentId;
            }
            $desc = isset($data['description']) ? trim((string) $data['description']) : '';
            $rbacRoute = isset($data['rbac_route']) ? trim((string) $data['rbac_route']) : '';
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
                $sem = $data['intent_semantics'];
                // Si hay keyphrases semánticas, sumarlas a keywords para mejorar el scoring por reglas.
                if (isset($sem['keyphrases']) && is_array($sem['keyphrases'])) {
                    foreach ($sem['keyphrases'] as $ph) {
                        if (is_string($ph) && trim($ph) !== '') {
                            $kw[] = trim($ph);
                        }
                    }
                    $kw = array_values(array_unique($kw));
                }
            }

            $out[] = [
                'action_id' => $intentId,
                'action_name' => $actionName,
                'display_name' => $actionName,
                'description' => $desc,
                'route' => '', // flows se ejecutan vía /asistente/enviar con action_id
                'rbac_route' => $rbacRoute,
                'keywords' => $kw,
                'synonyms' => [],
                'tags' => [],
                'parameters' => [],
                'intent_semantics' => $sem,
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
            // Guardar por firma actual y también el último “base” para warm hits.
            $cache->set($cacheKey, $out, 300);
            $cache->set($cacheKeyBase, $out, 60);
        }

        return $out;
    }

    /**
     * Intents YAML visibles para un usuario según `rbac_route` del manifiesto (misma regla que atajos / CommonActionsService).
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
            $aid = isset($flow['action_id']) ? trim((string) $flow['action_id']) : '';
            if ($aid === '') {
                continue;
            }
            $rbacRoute = isset($flow['rbac_route']) ? trim((string) $flow['rbac_route']) : '';
            if ($rbacRoute === '') {
                continue;
            }
            if (!ActionMappingService::userIdCanAccessRoute($userId, $rbacRoute)) {
                continue;
            }
            $out[] = $flow;
        }

        return $out;
    }

    public static function intentExists(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }
        $base = dirname(__DIR__) . '/SubIntentEngine/schemas/intents';
        $path = $base . '/' . $intentId . '.yaml';

        return is_file($path);
    }
}

