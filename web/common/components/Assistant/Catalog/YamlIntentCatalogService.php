<?php

namespace common\components\Assistant\Catalog;

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
        $cacheKey = 'yaml_intents_catalog_v1';
        if ($useCache && $cache) {
            $hit = $cache->get($cacheKey);
            if (is_array($hit)) {
                return $hit;
            }
        }

        $base = dirname(__DIR__) . '/SubIntentEngine/schemas/intents';
        $files = glob($base . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        $out = [];

        foreach ($files as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }
            try {
                $data = Yaml::parseFile($path);
            } catch (\Throwable $e) {
                Yii::warning('YAML intent inválido ' . $path . ': ' . $e->getMessage(), 'intent-catalog');
                continue;
            }
            if (!is_array($data)) {
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

            $out[] = [
                'action_id' => $intentId,
                'action_name' => $actionName,
                'display_name' => $actionName,
                'description' => $desc,
                'route' => '', // flows se ejecutan vía /asistente/enviar con action_id
                'keywords' => $kw,
                'synonyms' => [],
                'tags' => [],
                'parameters' => [],
                // Hint para clientes: item conversacional.
                'kind' => 'intent_flow',
            ];
        }

        if ($useCache && $cache) {
            $cache->set($cacheKey, $out, 300);
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

