<?php

namespace common\components\Platform\Core\Product;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata comercial: precio por volumen de atenciones × encounter_class (COGS + margen + add-ons).
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

    /** @deprecated Usar referenceEncountersPerMonth; se mantiene por compatibilidad. */
    public static function encountersPerMonth(string $encounterClass): float
    {
        return self::referenceEncountersPerMonth();
    }

    /** @deprecated El precio ya no escala por encounters_per_professional_month. */
    public static function volumeScale(string $encounterClass): float
    {
        return 1.0;
    }

    public static function classIncludesAudio(string $encounterClass): bool
    {
        $row = self::classRow($encounterClass);

        return $row !== null && !empty($row['audio_included']);
    }

    public static function classIncludesPatientChat(string $encounterClass): bool
    {
        $row = self::classRow($encounterClass);
        if ($row !== null && array_key_exists('includes_patient_chat', $row)) {
            return (bool) $row['includes_patient_chat'];
        }

        return $encounterClass === 'AMB';
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
     * COGS §2 motivos (blend audio/texto) por encounter_class.
     */
    public static function motivosAudioCogsPerEncounter(?string $encounterClass = null): float
    {
        $cogs = self::loadConfig()['cogs_usd_per_encounter'] ?? [];
        if (!is_array($cogs)) {
            return 0.0;
        }
        $byClass = $cogs['motivos_audio_by_class'] ?? null;
        if (is_array($byClass) && $encounterClass !== null && array_key_exists($encounterClass, $byClass)) {
            return (float) $byClass[$encounterClass];
        }

        return (float) ($cogs['motivos_audio'] ?? 0);
    }

    /**
     * COGS USD por atención según add-ons y clase.
     */
    public static function unitCogsPerEncounter(
        bool $audio = false,
        bool $videollamada = false,
        ?string $encounterClass = null
    ): float {
        if ($encounterClass !== null) {
            [$audio, $videollamada] = self::effectiveAddons($encounterClass, $audio, $videollamada);
        }
        $cogs = self::loadConfig()['cogs_usd_per_encounter'] ?? [];
        if (!is_array($cogs)) {
            return 0.0;
        }
        $total = self::motivosAudioCogsPerEncounter($encounterClass) + (float) ($cogs['captura_ia'] ?? 0);
        if ($encounterClass !== null && self::classIncludesPatientChat($encounterClass)) {
            $total += (float) ($cogs['patient_chat_amb'] ?? 0);
        }
        if ($audio || $videollamada) {
            $total += (float) ($cogs['dictado_stt'] ?? 0);
        }
        if ($videollamada) {
            $total += (float) ($cogs['videollamada'] ?? 0);
        }

        return round($total, 4);
    }

    /**
     * @deprecated Preferir unitCogsPerEncounter. Compat: COGS × 400 (ref. histórica).
     */
    public static function referenceUnitCogs(bool $audio = false, bool $videollamada = false): float
    {
        return round(self::unitCogsPerEncounter($audio, $videollamada, 'AMB') * self::referenceEncountersPerMonth(), 4);
    }

    /**
     * @deprecated Preferir unitCogsPerEncounter.
     */
    public static function unitCogs(
        bool $audio = false,
        bool $videollamada = false,
        ?string $encounterClass = null
    ): float {
        return self::unitCogsPerEncounter($audio, $videollamada, $encounterClass);
    }

    public static function marginOnCostPercent(): float
    {
        return (float) (self::loadConfig()['margin_on_cost_percent'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function volumeDiscountTiers(): array
    {
        $tiers = self::loadConfig()['volume_discount_tiers'] ?? [];
        if (!is_array($tiers)) {
            return [];
        }

        return array_values(array_filter($tiers, static fn ($t) => is_array($t)));
    }

    /**
     * @return list<int>
     */
    public static function attentionVolumeScale(): array
    {
        $scale = self::loadConfig()['attention_volume_scale'] ?? [];
        if (!is_array($scale)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($scale, static fn ($n) => (int) $n > 0)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function tierForTotalAttentions(int $totalAttentions): array
    {
        $n = max(0, $totalAttentions);
        $tiers = self::volumeDiscountTiers();
        if ($tiers === []) {
            return [
                'id' => 'lista',
                'label' => 'Precio base',
                'min_attentions' => 1,
                'max_attentions' => null,
                'margin_on_cost_percent' => self::marginOnCostPercent(),
                'discount_vs_list_percent' => 0,
            ];
        }

        $fallback = $tiers[0];
        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_attentions'] ?? $tier['min_pes'] ?? 0);
            $maxRaw = $tier['max_attentions'] ?? $tier['max_pes'] ?? null;
            $max = $maxRaw === null || $maxRaw === '' ? null : (int) $maxRaw;
            if ($n >= $min && ($max === null || $n <= $max)) {
                return $tier;
            }
            if ($n >= $min) {
                $fallback = $tier;
            }
        }

        return $fallback;
    }

    /** @deprecated Usar tierForTotalAttentions */
    public static function tierForTotalPes(int $totalPes): array
    {
        return self::tierForTotalAttentions($totalPes);
    }

    public static function marginOnCostPercentForTotalAttentions(int $totalAttentions): float
    {
        $tier = self::tierForTotalAttentions($totalAttentions);
        if (isset($tier['margin_on_cost_percent'])) {
            return (float) $tier['margin_on_cost_percent'];
        }

        return self::marginOnCostPercent();
    }

    /** @deprecated Usar marginOnCostPercentForTotalAttentions */
    public static function marginOnCostPercentForTotalPes(int $totalPes): float
    {
        return self::marginOnCostPercentForTotalAttentions($totalPes);
    }

    public static function deriveMaxPesFromAttentions(int $attentions): int
    {
        $qty = max(0, $attentions);
        if ($qty <= 0) {
            return 0;
        }

        return max(1, (int) ceil($qty / self::referenceEncountersPerMonth()));
    }

    /**
     * Precio USD / atención = COGS × (1 + margen%).
     *
     * @param int|null $totalAttentions atenciones totales del contrato; null = margen de lista.
     */
    public static function unitPrice(
        bool $audio = false,
        bool $videollamada = false,
        ?string $encounterClass = null,
        ?int $totalAttentions = null
    ): float {
        $cogs = self::unitCogsPerEncounter($audio, $videollamada, $encounterClass);
        $margin = $totalAttentions === null
            ? self::marginOnCostPercent()
            : self::marginOnCostPercentForTotalAttentions($totalAttentions);

        return round($cogs * (1 + $margin / 100), 4);
    }

    public static function pricePerPes(string $encounterClass, ?int $totalAttentions = null): ?float
    {
        if (!self::isSellableClass($encounterClass)) {
            return null;
        }

        return self::unitPrice(false, false, $encounterClass, $totalAttentions);
    }

    public static function defaultWhenEmptyAllowAll(): bool
    {
        $mode = (string) (self::loadConfig()['default_when_empty'] ?? 'allow_all');

        return $mode !== 'deny_unlisted';
    }

    /**
     * @param array<string, int> $attentionsByClass code => atenciones / mes
     */
    public static function estimateMonthlyTotal(
        array $attentionsByClass,
        bool $audio = false,
        bool $videollamada = false
    ): float {
        $totalAttentions = 0;
        foreach ($attentionsByClass as $qty) {
            $totalAttentions += max(0, (int) $qty);
        }

        $total = 0.0;
        foreach ($attentionsByClass as $code => $qty) {
            $code = (string) $code;
            if (!self::isSellableClass($code) || (int) $qty <= 0) {
                continue;
            }
            $total += self::unitPrice($audio, $videollamada, $code, $totalAttentions) * (int) $qty;
        }

        return round($total, 2);
    }
}
