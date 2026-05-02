<?php

namespace common\components;

use Yii;
use yii\db\ActiveRecord;

/**
 * Resuelve `option_config` de campos `select` con `options: "{{options}}"`.
 *
 * - **`source: catalog`** + **`catalog: "<clave>"`**: catálogo genérico declarado en
 *   {@see UiCatalogOptionDefinitions} (lista blanca: clase AR + atributos).
 * - **`efectores` / `servicios` / `rrhh`**: fuentes con filtros/joins propios (no reducibles a un find() simple).
 *
 * Extensión: {@see register()} o `Yii::$app->params['uiCatalogOptionDefinitions']`.
 */
final class UiSelectOptionSourceResolver
{
    public const LOG_CATEGORY = 'ui-definition-template';

    /** @var array<string, callable(mixed, array<string, mixed>, array<string, mixed>): array<int, array<string, mixed>>>|null */
    private static $sources;

    /**
     * Registra o reemplaza una fuente especializada en runtime (tests, módulos).
     *
     * Firma del callable: `function ($filter, array $params, array $optionConfig): array`
     *
     * @param callable(mixed, array<string, mixed>, array<string, mixed>): array<int, array<string, mixed>> $resolver
     */
    public static function register(string $sourceKey, callable $resolver): void
    {
        self::bootstrap();
        self::$sources[$sourceKey] = $resolver;
    }

    /**
     * @param array<string, mixed> $optionConfig
     * @param array<string, mixed> $params
     *
     * @return array<int, array<string, mixed>>|null null si la fuente no existe o el catálogo no es válido
     */
    public static function resolve(string $sourceKey, array $optionConfig, array $params): ?array
    {
        // Compat: descriptores antiguos usaban el nombre del catálogo como `source`.
        if ($sourceKey === 'condiciones_laborales') {
            $optionConfig = array_merge(['catalog' => 'condiciones_laborales'], $optionConfig);
            $sourceKey = 'catalog';
        }

        if ($sourceKey === 'catalog') {
            return self::buildCatalogSelectOptions($optionConfig);
        }

        self::bootstrap();

        if (!isset(self::$sources[$sourceKey])) {
            Yii::warning("Fuente de opciones no soportada: {$sourceKey}", self::LOG_CATEGORY);

            return null;
        }

        $filter = $optionConfig['filter'] ?? null;

        return call_user_func(self::$sources[$sourceKey], $filter, $params, $optionConfig);
    }

    /**
     * Catálogo genérico (lista blanca {@see UiCatalogOptionDefinitions}).
     *
     * @param array<string, mixed> $optionConfig
     *
     * @return array<int, array{value: string, label: string}>|null
     */
    private static function buildCatalogSelectOptions(array $optionConfig): ?array
    {
        $catalogKey = isset($optionConfig['catalog']) ? trim((string) $optionConfig['catalog']) : '';
        if ($catalogKey === '') {
            Yii::warning('option_config.catalog es obligatorio cuando source=catalog', self::LOG_CATEGORY);

            return null;
        }

        $def = UiCatalogOptionDefinitions::get($catalogKey);
        if ($def === null) {
            Yii::warning("Catálogo UI no registrado o inválido: {$catalogKey}", self::LOG_CATEGORY);

            return null;
        }

        /** @var class-string<ActiveRecord> $class */
        $class = $def['class'];
        $valueAttr = $def['value'];
        $labelAttr = $def['label'];

        $q = $class::find();
        if (isset($def['orderBy']) && is_array($def['orderBy']) && $def['orderBy'] !== []) {
            $q->orderBy($def['orderBy']);
        }

        $rows = $q->all();

        $options = [];
        foreach ($rows as $row) {
            if (!$row instanceof ActiveRecord) {
                continue;
            }
            $options[] = [
                'value' => (string) $row->getAttribute($valueAttr),
                'label' => (string) $row->getAttribute($labelAttr),
            ];
        }

        return $options;
    }

    private static function bootstrap(): void
    {
        if (self::$sources !== null) {
            return;
        }

        self::$sources = [
            'efectores' => [self::class, 'resolveEfectores'],
            'servicios' => [self::class, 'resolveServicios'],
            'rrhh' => [self::class, 'resolveRrhh'],
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     *
     * @return array<int, array<string, mixed>>
     */
    private static function resolveEfectores($filter, array $params, array $optionConfig): array
    {
        $userId = Yii::$app->user->id ?? null;

        $idServicio = null;
        if (isset($params['id_servicio']) && $params['id_servicio'] !== null && $params['id_servicio'] !== '') {
            $idServicio = (int) $params['id_servicio'];
        } elseif (isset($params['id_servicio_asignado']) && $params['id_servicio_asignado'] !== null && $params['id_servicio_asignado'] !== '') {
            $idServicio = (int) $params['id_servicio_asignado'];
        }

        if ($filter === 'user_efectores' && $userId) {
            $q = \common\models\UserEfector::find()
                ->joinWith('idEfector')
                ->where(['user_efector.id_user' => $userId])
                ->andWhere('efectores.deleted_at IS NULL');

            if ($idServicio) {
                $q->innerJoin('servicios_efector se', 'se.id_efector = efectores.id_efector')
                    ->andWhere(['se.id_servicio' => $idServicio])
                    ->distinct();
            }

            $efectores = $q->orderBy('efectores.nombre')->all();

            $options = [];
            foreach ($efectores as $efector) {
                $options[] = [
                    'id' => $efector->idEfector->id_efector,
                    'name' => $efector->idEfector->nombre,
                ];
            }

            return $options;
        }

        if ($idServicio) {
            $efectores = \common\models\Efector::find()
                ->innerJoin('servicios_efector se', 'se.id_efector = efectores.id_efector')
                ->where('efectores.deleted_at IS NULL')
                ->andWhere(['se.id_servicio' => $idServicio])
                ->distinct()
                ->orderBy('efectores.nombre')
                ->all();
        } else {
            $efectores = \common\models\Efector::find()
                ->where('deleted_at IS NULL')
                ->orderBy('nombre')
                ->all();
        }

        $options = [];
        foreach ($efectores as $efector) {
            $options[] = [
                'id' => $efector->id_efector,
                'name' => $efector->nombre,
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     *
     * @return array<int, array<string, mixed>>
     */
    private static function resolveServicios($filter, array $params, array $optionConfig): array
    {
        if ($filter === 'efector_servicios' && isset($params['id_efector']) && $params['id_efector'] !== null && $params['id_efector'] !== '') {
            $servicios = \common\models\Servicio::find()
                ->innerJoin('servicios_efector se', 'se.id_servicio = servicios.id_servicio')
                ->where(['se.id_efector' => $params['id_efector']])
                ->andWhere('servicios.deleted_at IS NULL')
                ->orderBy('servicios.nombre')
                ->all();

            $options = [];
            foreach ($servicios as $servicio) {
                $options[] = [
                    'id' => (string) $servicio->id_servicio,
                    'name' => $servicio->nombre,
                ];
            }

            return $options;
        }

        $servicios = \common\models\Servicio::find()
            ->orderBy('nombre')
            ->all();

        $options = [];
        foreach ($servicios as $servicio) {
            $options[] = [
                'id' => (string) $servicio->id_servicio,
                'name' => $servicio->nombre,
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     *
     * @return array<int, array<string, mixed>>
     */
    private static function resolveRrhh($filter, array $params, array $optionConfig): array
    {
        return [];
    }
}
