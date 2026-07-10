<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata comercial: precio por PES × encounter_class.
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

    public static function pricePerPes(string $encounterClass): ?float
    {
        $row = self::loadConfig()['sellable_classes'][$encounterClass] ?? null;
        if (!is_array($row) || !isset($row['price_per_pes'])) {
            return null;
        }

        return (float) $row['price_per_pes'];
    }

    public static function defaultWhenEmptyAllowAll(): bool
    {
        $mode = (string) (self::loadConfig()['default_when_empty'] ?? 'allow_all');

        return $mode !== 'deny_unlisted';
    }

    /**
     * @param array<string, int> $pesByClass code => cantidad PES
     */
    public static function estimateMonthlyTotal(array $pesByClass): float
    {
        $total = 0.0;
        foreach ($pesByClass as $code => $qty) {
            $price = self::pricePerPes((string) $code);
            if ($price === null || (int) $qty <= 0) {
                continue;
            }
            $total += $price * (int) $qty;
        }

        return round($total, 2);
    }
}
