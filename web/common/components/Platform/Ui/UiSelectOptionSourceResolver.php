<?php

namespace common\components\Platform\Ui;

use common\components\Platform\Core\Product\UiSelectOptionSourceMetadata;
use common\components\UiCatalogOptionDefinitions;
use Yii;
use yii\db\ActiveRecord;

/**
 * Resuelve `option_config` de campos `select` con `options: "{{options}}"`.
 *
 * Fuentes de dominio: {@see UiSelectOptionSourceProviderRegistry} + metadata producto.
 * Catálogos AR genéricos: {@see UiCatalogOptionDefinitions}.
 */
final class UiSelectOptionSourceResolver
{
    public const LOG_CATEGORY = 'ui-definition-template';

    /**
     * @param array<string, mixed> $optionConfig
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>|null
     */
    public static function resolve(string $sourceKey, array $optionConfig, array $params): ?array
    {
        $normalized = UiSelectOptionSourceMetadata::normalizeSource($sourceKey, $optionConfig);
        if ($normalized === null) {
            return null;
        }

        $sourceKey = $normalized['source'];
        $optionConfig = $normalized['option_config'];

        if ($sourceKey === 'catalog') {
            return self::buildCatalogSelectOptions($optionConfig);
        }

        $options = UiSelectOptionSourceProviderRegistry::resolveNormalized($sourceKey, $optionConfig, $params);
        if ($options === null) {
            Yii::warning('Fuente de opciones no soportada: ' . $sourceKey, self::LOG_CATEGORY);

            return null;
        }

        return $options;
    }

    /**
     * Registra fuente en runtime (tests).
     *
     * @param callable(mixed, array<string, mixed>, array<string, mixed>): array<int, array<string, mixed>> $resolver
     */
    public static function register(string $sourceKey, callable $resolver): void
    {
        UiSelectOptionSourceProviderRegistry::registerRuntime($sourceKey, $resolver);
    }

    /**
     * @param array<string, mixed> $optionConfig
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
}
