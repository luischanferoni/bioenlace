<?php

namespace common\components\Platform\Core\Product;

/**
 * Fuentes de opciones para selects en UI JSON (providers y depends_on opcionales).
 */
final class UiSelectOptionSourceMetadata
{
    /** @var array<string, string> */
    private const SOURCE_PROVIDERS = [
        'efectores' => 'organization',
        'servicios' => 'organization',
        'profesionales' => 'scheduling',
        'profesional-efector-servicio' => 'scheduling',
        'slots_disponibles_paciente' => 'scheduling',
    ];

    /**
     * @var list<array{source: string, filter: string}>
     */
    private const DEPENDS_ON_OPTIONAL = [
        [
            'source' => 'servicios',
            'filter' => 'efector_servicios',
        ],
    ];

    /**
     * @param array<string, mixed> $optionConfig
     * @return array{source: string, option_config: array<string, mixed>}|null
     */
    public static function normalizeSource(string $sourceKey, array $optionConfig): ?array
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        return ['source' => $sourceKey, 'option_config' => $optionConfig];
    }

    public static function providerKeyForSource(string $sourceKey): ?string
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return null;
        }

        $key = self::SOURCE_PROVIDERS[$sourceKey] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Permite resolver opciones aunque falte el param referenciado por depends_on del campo.
     *
     * @param array<string, mixed> $optionConfig
     */
    public static function allowsMissingDependsOn(string $sourceKey, array $optionConfig): bool
    {
        $sourceKey = trim($sourceKey);
        if ($sourceKey === '') {
            return false;
        }

        $filter = isset($optionConfig['filter']) ? trim((string) $optionConfig['filter']) : '';

        foreach (self::DEPENDS_ON_OPTIONAL as $rule) {
            if (trim((string) ($rule['source'] ?? '')) !== $sourceKey) {
                continue;
            }
            $ruleFilter = trim((string) ($rule['filter'] ?? ''));
            if ($ruleFilter === '' || $ruleFilter === $filter) {
                return true;
            }
        }

        return false;
    }

    public static function resetCacheForTests(): void
    {
        // Sin cache de archivo; no-op para compatibilidad de tests.
    }
}
