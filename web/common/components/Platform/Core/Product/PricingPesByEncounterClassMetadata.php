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
     * @return array<string, mixed>|null
     */
    private static function classRow(string $encounterClass): ?array
    {
        $row = self::loadConfig()['sellable_classes'][$encounterClass] ?? null;

        return is_array($row) ? $row : null;
    }

    public static function referenceEncountersPerMonth(): float
    {
        $ref = (float) (self::loadConfig()['reference_encounters_per_professional_month'] ?? 400);

        return $ref > 0 ? $ref : 400.0;
    }

    public static function encountersPerMonth(string $encounterClass): float
    {
        $row = self::classRow($encounterClass);
        if ($row !== null && isset($row['encounters_per_professional_month'])) {
            $n = (float) $row['encounters_per_professional_month'];
            if ($n > 0) {
                return $n;
            }
        }

        return self::referenceEncountersPerMonth();
    }

    public static function volumeScale(string $encounterClass): float
    {
        return self::encountersPerMonth($encounterClass) / self::referenceEncountersPerMonth();
    }

    public static function classIncludesAudio(string $encounterClass): bool
    {
        $row = self::classRow($encounterClass);

        return $row !== null && !empty($row['audio_included']);
    }

    public static function classAllowsVideollamada(string $encounterClass): bool
    {
        $row = self::classRow($encounterClass);
        if ($row === null) {
            return false;
        }
        if (array_key_exists('videollamada_allowed', $row)) {
            return (bool) $row['videollamada_allowed'];
        }

        return true;
    }

    /**
     * COGS de referencia (volumen reference) según add-ons, sin escalar por clase.
     */
    public static function referenceUnitCogs(bool $audio = false, bool $videollamada = false): float
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

    /**
     * Resuelve add-ons efectivos para una clase (audio incluido / video no permitido).
     *
     * @return array{0: bool, 1: bool} [audio, videollamada]
     */
    public static function effectiveAddons(
        string $encounterClass,
        bool $audio = false,
        bool $videollamada = false
    ): array {
        $audioEff = $audio || self::classIncludesAudio($encounterClass);
        $videoEff = $videollamada && self::classAllowsVideollamada($encounterClass);

        return [$audioEff, $videoEff];
    }

    /**
     * COGS unitario USD/profesional/mes según add-ons y volumen de la clase.
     */
    public static function unitCogs(
        bool $audio = false,
        bool $videollamada = false,
        ?string $encounterClass = null
    ): float {
        if ($encounterClass !== null) {
            [$audio, $videollamada] = self::effectiveAddons($encounterClass, $audio, $videollamada);
        }
        $base = self::referenceUnitCogs($audio, $videollamada);
        if ($encounterClass === null) {
            return $base;
        }

        return round($base * self::volumeScale($encounterClass), 4);
    }

    public static function marginOnCostPercent(): float
    {
        return (float) (self::loadConfig()['margin_on_cost_percent'] ?? 0);
    }

    /**
     * Precio de lista USD/profesional/mes = COGS × (1 + margen%).
     */
    public static function unitPrice(
        bool $audio = false,
        bool $videollamada = false,
        ?string $encounterClass = null
    ): float {
        $cogs = self::unitCogs($audio, $videollamada, $encounterClass);
        $margin = self::marginOnCostPercent();

        return round($cogs * (1 + $margin / 100), 2);
    }

    /**
     * Precio unitario de lista para la clase (respeta audio incluido / video permitido).
     */
    public static function pricePerPes(string $encounterClass): ?float
    {
        if (!self::isSellableClass($encounterClass)) {
            return null;
        }

        return self::unitPrice(false, false, $encounterClass);
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
        $total = 0.0;
        foreach ($professionalsByClass as $code => $qty) {
            $code = (string) $code;
            if (!self::isSellableClass($code) || (int) $qty <= 0) {
                continue;
            }
            $total += self::unitPrice($audio, $videollamada, $code) * (int) $qty;
        }

        return round($total, 2);
    }
}
