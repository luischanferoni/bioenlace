<?php

namespace common\components\Platform\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Carga el manifiesto YAML de un intent conversacional (`schemas/intents/<intent_id>.yaml`).
 */
final class YamlIntentManifestLoader
{
    /**
     * @return array<string, mixed>|null
     */
    public static function load(string $intentId): ?array
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return null;
        }

        $path = IntentSchemaPaths::resolveFileForIntentId($intentId);
        if ($path === null || !is_file($path)) {
            return null;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::error('YAML inválido intent ' . $intentId . ': ' . $e->getMessage(), 'yaml_intent_manifest');

            return null;
        }

        return is_array($data) ? $data : null;
    }
}
