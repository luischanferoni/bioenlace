<?php

namespace common\components;

use common\components\Platform\Core\Product\ProductRegistryConfig;
use Yii;
use yii\db\ActiveRecord;

/**
 * Catálogos permitidos para {@see Platform\Ui\UiSelectOptionSourceResolver} cuando `source=catalog`.
 *
 * Definiciones de producto en {@see product-registries.php} (`uiCatalogOptionDefinitions`).
 *
 * @phpstan-type CatalogDef array{class: class-string<ActiveRecord>, value: string, label: string, orderBy?: array<string, int>}
 */
final class UiCatalogOptionDefinitions
{
    /**
     * @return array<string, CatalogDef>
     */
    public static function all(): array
    {
        static $merged = null;

        if ($merged !== null) {
            return $merged;
        }

        $fromRegistry = ProductRegistryConfig::section('uiCatalogOptionDefinitions');
        $core = is_array($fromRegistry) ? $fromRegistry : [];

        $extra = [];
        if (Yii::$app !== null && isset(Yii::$app->params['uiCatalogOptionDefinitions']) && is_array(Yii::$app->params['uiCatalogOptionDefinitions'])) {
            $extra = Yii::$app->params['uiCatalogOptionDefinitions'];
        }

        $normalized = [];
        foreach (array_merge($core, $extra) as $key => $def) {
            if (!is_string($key)) {
                continue;
            }
            $norm = self::normalizeDefinition($def);
            if ($norm !== null) {
                $normalized[trim($key)] = $norm;
            }
        }

        $merged = $normalized;

        return $merged;
    }

    /**
     * @return CatalogDef|null
     */
    public static function get(string $catalogKey): ?array
    {
        $catalogKey = trim($catalogKey);
        if ($catalogKey === '') {
            return null;
        }

        $all = self::all();

        return $all[$catalogKey] ?? null;
    }

    /**
     * @param mixed $def
     * @return CatalogDef|null
     */
    private static function normalizeDefinition($def): ?array
    {
        if (!is_array($def)) {
            return null;
        }

        $class = isset($def['class']) ? $def['class'] : null;
        $value = isset($def['value']) ? trim((string) $def['value']) : '';
        $label = isset($def['label']) ? trim((string) $def['label']) : '';

        if (!is_string($class) || $class === '' || !class_exists($class) || $value === '' || $label === '') {
            return null;
        }

        if (!is_subclass_of($class, ActiveRecord::class)) {
            return null;
        }

        /** @var CatalogDef $out */
        $out = [
            'class' => $class,
            'value' => $value,
            'label' => $label,
        ];

        if (isset($def['orderBy']) && is_array($def['orderBy']) && $def['orderBy'] !== []) {
            $out['orderBy'] = $def['orderBy'];
        }

        return $out;
    }
}
