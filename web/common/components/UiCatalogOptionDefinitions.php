<?php

namespace common\components;

use common\models\Condiciones_laborales;
use Yii;
use yii\db\ActiveRecord;

/**
 * Catálogos permitidos para {@see UiSelectOptionSourceResolver} cuando `option_config.source` es `catalog`.
 *
 * No se lee modelo/columnas desde el JSON: solo la clave `catalog`; el mapeo AR vive aquí (lista blanca).
 * Para proyectos: fusionar {@see Yii::$app->params['uiCatalogOptionDefinitions']} (misma forma).
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

        $core = [
            'condiciones_laborales' => [
                'class' => Condiciones_laborales::class,
                'value' => 'id_condicion_laboral',
                'label' => 'nombre',
                'orderBy' => ['nombre' => SORT_ASC],
            ],
        ];

        $extra = [];
        if (Yii::$app !== null && isset(Yii::$app->params['uiCatalogOptionDefinitions']) && is_array(Yii::$app->params['uiCatalogOptionDefinitions'])) {
            $extra = Yii::$app->params['uiCatalogOptionDefinitions'];
        }

        $merged = array_merge($core, $extra);

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

        return isset($all[$catalogKey]) ? self::normalizeDefinition($all[$catalogKey]) : null;
    }

    /**
     * @param mixed $def
     *
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
