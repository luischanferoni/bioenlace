<?php

namespace common\components\Platform\Core\Product;

use Yii;

/**
 * Rutas de metadata declarativa del producto (intents, reglas NL, permisos de dominio).
 *
 * Para otro rubro: apuntar {@see Yii::$app->params productMetadataDir} a otra carpeta bajo common/metadata/.
 */
final class ProductMetadataPaths
{
    public static function baseDir(): string
    {
        if (Yii::$app->has('params')) {
            $configured = Yii::$app->params['productMetadataDir'] ?? null;
            if (is_string($configured) && trim($configured) !== '') {
                $dir = realpath(trim($configured));

                return $dir !== false ? $dir : rtrim(trim($configured), '/\\');
            }
        }

        $default = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'metadata' . DIRECTORY_SEPARATOR . 'bioenlace';
        $resolved = realpath($default);

        return $resolved !== false ? $resolved : $default;
    }

    public static function assistantDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'assistant';
    }

    public static function permissionDir(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'permission';
    }

    public static function intentsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'intents';
    }

    public static function globalsDir(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'globals';
    }

    public static function intentClassificationRulesFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'intent-classification-rules.yaml';
    }

    public static function hintResolutionFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'hint-resolution.yaml';
    }

    public static function assistantShortcutsFile(): string
    {
        return self::assistantDir() . DIRECTORY_SEPARATOR . 'assistant-shortcuts.yaml';
    }

    public static function domainOperationPoliciesFile(): string
    {
        return self::permissionDir() . DIRECTORY_SEPARATOR . 'domain-operation-policies.yaml';
    }

    public static function homePanelManifestFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'home_panel_manifest.yaml';
    }

    public static function clientContextFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'client-context.yaml';
    }

    public static function uiJsonDomainsFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'json-domains.yaml';
    }

    public static function uiScreenParamsFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'screen-params.yaml';
    }

    public static function uiSelectOptionSourcesFile(): string
    {
        return self::baseDir() . DIRECTORY_SEPARATOR . 'ui' . DIRECTORY_SEPARATOR . 'select-option-sources.yaml';
    }
}
