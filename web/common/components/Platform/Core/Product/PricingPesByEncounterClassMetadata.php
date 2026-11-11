<?php

namespace common\components\Platform\Core\Product;

use common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog;

/**
 * Metadata comercial: precio por volumen de atenciones × encounter_class (COGS + margen + add-ons).
 *
 * @see \common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog
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

        self::$config = PricingPesByEncounterClassCatalog::config();

        return self::$config;
    }

    /**
     * Catálogo comercial público: excluye COGS, márgenes y tramos internos.
     *
     * Es lo único que se expone en `GET /licencia/planes` (web institucional y
     * apps móviles). `loadConfig()` sigue devolviendo el catálogo completo para
     * uso interno.
     *
     * @return array<string, mixed>
     */
    public static function publicConfig(): array
    {
        $config = self::loadConfig();

        $classes = [];
        foreach ((array) ($config['sellable_classes'] ?? []) as $code => $row) {
            if (!is_array($row)) {
                continue;
            }
            $class = (string) $code;
            $public = self::only($row, [
                'label',
                'short',
                'includes_patient_chat',
                'includes',
            ]);
            // Contrato público que ya consumen las apps: se deriva de la matriz de addons.
            $public['audio_included'] = self::classIncludesAudio($class);
            $public['videollamada_allowed'] = self::classAllowsVideollamada($class);
            $classes[$class] = $public;
        }

        $presets = [];
        foreach ((array) ($config['attention_volume_presets'] ?? []) as $code => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            $list = [];
            foreach ($rows as $preset) {
                if (!is_array($preset)) {
                    continue;
                }
                $list[] = self::only($preset, ['attentions', 'label', 'hint']);
            }
            $presets[(string) $code] = $list;
        }

        $reference = [];
        foreach ((array) ($config['reference_encounters_per_professional_month'] ?? []) as $code => $value) {
            $reference[(string) $code] = (int) $value;
        }

        $defaults = [];
        foreach ((array) ($config['defaults']['initial_attentions'] ?? []) as $code => $value) {
            $defaults[(string) $code] = (int) $value;
        }

        $addons = [];
        foreach ((array) ($config['addons'] ?? []) as $code => $addon) {
            if (!is_array($addon)) {
                continue;
            }
            $addon['classes'] = array_map(
                static fn ($state) => strtolower(trim((string) $state)),
                (array) ($addon['classes'] ?? [])
            );
            $addon['scope'] = self::addonScope((string) $code)
                ?? PricingPesByEncounterClassCatalog::ADDON_SCOPE_PER_CLASS;
            $addons[(string) $code] = self::only($addon, [
                'label',
                'included_label',
                'unavailable_label',
                'description',
                'plan_field',
                'scope',
                'classes',
            ]);
        }

        return [
            'version' => (int) ($config['version'] ?? 1),
            'currency' => (string) ($config['currency'] ?? 'USD'),
            'billing_period' => (string) ($config['billing_period'] ?? 'month'),
            'tax_note' => (string) ($config['tax_note'] ?? ''),
            'reference_encounters_per_professional_month' => $reference,
            'attention_volume_presets' => $presets,
            'defaults' => $defaults,
            'sellable_classes' => $classes,
            'non_sellable_default' => (string) ($config['non_sellable_default'] ?? 'disabled'),
            'addons' => $addons,
            'simulator' => self::only(
                (array) ($config['simulator'] ?? []),
                ['title', 'subtitle', 'cta_label', 'cta_href', 'footnotes']
            ),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private static function only(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
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

    public static function referenceEncountersPerMonth(?string $encounterClass = null): float
    {
        $map = self::loadConfig()['reference_encounters_per_professional_month'] ?? [];
        $class = $encounterClass !== null ? strtoupper($encounterClass) : 'AMB';
        $ref = is_array($map) ? (float) ($map[$class] ?? 0) : (float) $map;

        return $ref > 0 ? $ref : 500.0;
    }

    /** @deprecated Usar referenceEncountersPerMonth; se mantiene por compatibilidad. */
    public static function encountersPerMonth(string $encounterClass): float
    {
        return self::referenceEncountersPerMonth($encounterClass);
    }

    /** @deprecated El precio ya no escala por encounters_per_professional_month. */
    public static function volumeScale(string $encounterClass): float
    {
        return 1.0;
    }

    public static function classIncludesAudio(string $encounterClass): bool
    {
        return self::addonState('dictado', $encounterClass)
            === PricingPesByEncounterClassCatalog::ADDON_INCLUDED;
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
        if (!self::isSellableClass($encounterClass)) {
            return false;
        }

        return self::addonState('videollamada', $encounterClass)
            !== PricingPesByEncounterClassCatalog::ADDON_UNAVAILABLE;
    }

    /**
     * Estado declarado de un addon para una clase (`included` / `optional` / `unavailable`).
     */
    public static function addonState(string $addonId, string $encounterClass): ?string
    {
        $addon = self::loadConfig()['addons'][$addonId] ?? null;
        if (!is_array($addon)) {
            return null;
        }
        $states = $addon['classes'] ?? null;
        $class = strtoupper($encounterClass);
        if (!is_array($states) || !array_key_exists($class, $states)) {
            return null;
        }

        $state = strtolower(trim((string) $states[$class]));
        $known = [
            PricingPesByEncounterClassCatalog::ADDON_INCLUDED,
            PricingPesByEncounterClassCatalog::ADDON_OPTIONAL,
            PricingPesByEncounterClassCatalog::ADDON_UNAVAILABLE,
        ];

        return in_array($state, $known, true)
            ? $state
            : PricingPesByEncounterClassCatalog::ADDON_UNAVAILABLE;
    }

    /**
     * Ámbito del addon, derivado de la matriz de estados (sin declararlo dos veces):
     * `all` cuando todos los tipos vendibles comparten el mismo estado elegible
     * (`included` u `optional`); `per_class` cuando difiere o falta algún tipo.
     */
    public static function addonScope(string $addonId): ?string
    {
        $addon = self::loadConfig()['addons'][$addonId] ?? null;
        if (!is_array($addon)) {
            return null;
        }

        $states = [];
        foreach (self::sellableClassCodes() as $class) {
            $state = self::addonState($addonId, $class);
            if ($state === null
                || $state === PricingPesByEncounterClassCatalog::ADDON_UNAVAILABLE
            ) {
                return PricingPesByEncounterClassCatalog::ADDON_SCOPE_PER_CLASS;
            }
            $states[$state] = true;
        }
        if (count($states) !== 1) {
            return PricingPesByEncounterClassCatalog::ADDON_SCOPE_PER_CLASS;
        }

        $only = (string) array_key_first($states);

        return in_array(
            $only,
            [
                PricingPesByEncounterClassCatalog::ADDON_INCLUDED,
                PricingPesByEncounterClassCatalog::ADDON_OPTIONAL,
            ],
            true
        )
            ? PricingPesByEncounterClassCatalog::ADDON_SCOPE_ALL
            : PricingPesByEncounterClassCatalog::ADDON_SCOPE_PER_CLASS;
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
     * COGS §2 motivos por encounter_class (cuestionario pre-turno, sin audio ni STT).
     */
    public static function motivosCogsPerEncounter(?string $encounterClass = null): float
    {
        $cogs = self::loadConfig()['cogs_usd_per_encounter'] ?? [];
        if (!is_array($cogs)) {
            return 0.0;
        }
        // Compat: configuración previa con blend de audio por clase.
        $byClass = $cogs['motivos_by_class'] ?? $cogs['motivos_audio_by_class'] ?? null;
        if (is_array($byClass) && $encounterClass !== null && array_key_exists($encounterClass, $byClass)) {
            return (float) $byClass[$encounterClass];
        }

        return (float) ($cogs['motivos'] ?? $cogs['motivos_audio'] ?? 0);
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
        $total = self::motivosCogsPerEncounter($encounterClass) + (float) ($cogs['captura_ia'] ?? 0);
        if ($encounterClass !== null && self::classIncludesPatientChat($encounterClass)) {
            $total += (float) ($cogs['patient_chat_amb'] ?? 0);
        }
        if ($audio || $videollamada) {
            $total += self::dictadoCogsPerEncounter();
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

    public static function initialAttentionsForClass(string $encounterClass): ?int
    {
        $defaults = self::loadConfig()['defaults']['initial_attentions'] ?? null;
        if (!is_array($defaults)) {
            return null;
        }

        $n = (int) ($defaults[strtoupper($encounterClass)] ?? 0);

        return $n > 0 ? $n : null;
    }

    public static function dictadoMinutes(): float
    {
        $assumptions = self::loadConfig()['cogs_assumptions'] ?? [];
        $minutes = (float) ($assumptions['dictado_minutes'] ?? 5);

        return $minutes > 0 ? $minutes : 5.0;
    }

    public static function dictadoCogsPerEncounter(): float
    {
        $assumptions = self::loadConfig()['cogs_assumptions'] ?? [];
        $perMinute = (float) ($assumptions['dictado_usd_per_minute'] ?? 0.0005);

        return round(self::dictadoMinutes() * $perMinute, 4);
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

    public static function deriveMaxPesFromAttentions(int $attentions, string $encounterClass = 'AMB'): int
    {
        $qty = max(0, $attentions);
        if ($qty <= 0) {
            return 0;
        }

        return max(1, (int) ceil($qty / self::referenceEncountersPerMonth($encounterClass)));
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
        return self::estimateMonthlyQuote($attentionsByClass, $audio, $videollamada)['amount_usd'];
    }

    /**
     * @param array<string, int> $attentionsByClass code => atenciones / mes
     * @return array{
     *     amount_usd: float,
     *     list_amount_usd: float,
     *     discount_amount_usd: float,
     *     discount_percent: float,
     *     total_attentions: int,
     *     items: list<array{code: string, attentions_per_month: int, unit_amount_usd: float, amount_usd: float}>
     * }
     */
    public static function estimateMonthlyQuote(
        array $attentionsByClass,
        bool $audio = false,
        bool $videollamada = false
    ): array {
        $totalAttentions = 0;
        foreach ($attentionsByClass as $qty) {
            $totalAttentions += max(0, (int) $qty);
        }

        $total = 0.0;
        $listTotal = 0.0;
        $items = [];
        foreach ($attentionsByClass as $code => $qty) {
            $code = (string) $code;
            if (!self::isSellableClass($code) || (int) $qty <= 0) {
                continue;
            }
            $unitPrice = self::unitPrice($audio, $videollamada, $code, $totalAttentions);
            $listUnitPrice = self::unitPrice($audio, $videollamada, $code);
            $lineTotal = $unitPrice * (int) $qty;
            $lineListTotal = $listUnitPrice * (int) $qty;
            $total += $lineTotal;
            $listTotal += $lineListTotal;
            $items[] = [
                'code' => $code,
                'attentions_per_month' => (int) $qty,
                'unit_amount_usd' => $unitPrice,
                'amount_usd' => round($lineTotal, 2),
            ];
        }

        $total = round($total, 2);
        $listTotal = round($listTotal, 2);
        $discountAmount = round(max(0, $listTotal - $total), 2);
        $discountPercent = $listTotal > 0 && $discountAmount > 0
            ? round(($discountAmount / $listTotal) * 100, 1)
            : (float) (self::tierForTotalAttentions($totalAttentions)['discount_vs_list_percent'] ?? 0);

        return [
            'amount_usd' => $total,
            'list_amount_usd' => $listTotal,
            'discount_amount_usd' => $discountAmount,
            'discount_percent' => $discountPercent,
            'total_attentions' => $totalAttentions,
            'items' => $items,
        ];
    }
}
