<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata comercial: precio por profesional × encounter_class (COGS + margen + add-ons).
 *
 * @see ProductMetadataPaths::pricingPesByEncounterClassFile()
 */
final class PricingPesByEncounterClassMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function reset(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ProductMetadataPaths::pricingPesByEncounterClassFile();
        if (!is_file($path)) {
            Yii::warning('Falta metadata pricing-pes-by-encounter-class: ' . $path, __METHOD__);
            self::$config = [];

            return self::$config;
        }

        $data = Yaml::parseFile($path);
        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }

    /**
     * @return list<string>
     */
    public static function sellableClassCodes(): array
    {
        $sellable = self::loadConfig()['sellable_classes'] ?? [];
        if (!is_array($sellable)) {
            return [];
        }

        return array_map('strval', array_keys($sellable));
    }

    public static function isSellableClass(string $encounterClass): bool
    {
        return in_array($encounterClass, self::sellableClassCodes(), true);
    }

    /**
     * COGS unitario USD/profesional/mes según add-ons.
     */
    public static function unitCogs(bool $audio = false, bool $videollamada = false): float
    {
        $cogs = self::loadConfig()['cogs_usd_per_professional_month'] ?? [];
        if (!is_array($cogs)) {
            return 0.0;
        }
        $total = (float) ($cogs['base'] ?? 0);
        if ($audio) {
            $total += (float) ($cogs['audio'] ?? 0);
        }
        if ($videollamada) {
            $total += (float) ($cogs['videollamada'] ?? 0);
        }

        return round($total, 4);
    }

    public static function marginOnCostPercent(): float
    {
        return (float) (self::loadConfig()['margin_on_cost_percent'] ?? 0);
    }

    /**
     * Precio de lista USD/profesional/mes = COGS × (1 + margen%).
     */
    public static function unitPrice(bool $audio = false, bool $videollamada = false): float
    {
        $cogs = self::unitCogs($audio, $videollamada);
        $margin = self::marginOnCostPercent();

        return round($cogs * (1 + $margin / 100), 2);
    }

    /**
     * Precio unitario sin add-ons (compatibilidad con callers que pedían price_per_pes).
     * El precio ya no varía por encounter_class: el COGS es el mismo.
     */
    public static function pricePerPes(string $encounterClass): ?float
    {
        if (!self::isSellableClass($encounterClass)) {
            return null;
        }

        return self::unitPrice(false, false);
    }

    public static function defaultWhenEmptyAllowAll(): bool
    {
        $mode = (string) (self::loadConfig()['default_when_empty'] ?? 'allow_all');

        return $mode !== 'deny_unlisted';
    }

    /**
     * @param array<string, int> $professionalsByClass code => cantidad de profesionales
     */
    public static function estimateMonthlyTotal(
        array $professionalsByClass,
        bool $audio = false,
        bool $videollamada = false
    ): float {
        $unit = self::unitPrice($audio, $videollamada);
        $total = 0.0;
        foreach ($professionalsByClass as $code => $qty) {
            if (!self::isSellableClass((string) $code) || (int) $qty <= 0) {
                continue;
            }
            $total += $unit * (int) $qty;
        }

        return round($total, 2);
    }
}
