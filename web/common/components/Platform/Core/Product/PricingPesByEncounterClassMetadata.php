<?php

namespace common\components\Platform\Core\Product;

use common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog;
use yii\base\InvalidConfigException;

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
            ]);
            // Contrato público que ya consumen las apps: se deriva de la matriz de addons.
            $public['audio_included'] = self::classIncludesAudio($class);
            $public['videollamada_allowed'] = self::classAllowsVideollamada($class);
            $classes[$class] = $public;
        }

        $presets = self::attentionVolumePresets();

        $reference = [];
        foreach ((array) ($config['reference_encounters_per_professional_month'] ?? []) as $code => $value) {
            $reference[(string) $code] = (int) $value;
        }

        $defaults = self::initialAttentionsByClass();

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

    /**
     * Capacidad de referencia por profesional y mes (atenciones atendidas).
     *
     * La fuente es el catálogo; si falta la capacidad de una clase es un error de
     * configuración, no un default silencioso.
     */
    public static function referenceEncountersPerMonth(?string $encounterClass = null): float
    {
        $map = self::loadConfig()['reference_encounters_per_professional_month'] ?? [];
        $class = $encounterClass !== null ? strtoupper($encounterClass) : 'AMB';
        $ref = is_array($map) ? (float) ($map[$class] ?? 0) : (float) $map;
        if ($ref <= 0) {
            throw new InvalidConfigException(
                'Falta la capacidad de referencia (reference_encounters_per_professional_month) para la clase '
                . $class . '.'
            );
        }

        return $ref;
    }

    /**
     * Escala de volumen por tipo de atención, calculada desde la capacidad de
     * referencia. El copy (label/hint) sale del catálogo; los volúmenes, no.
     *
     * @return array<string, list<array{attentions: int, label: ?string, hint: ?string}>>
     */
    public static function attentionVolumePresets(): array
    {
        $config = self::loadConfig();
        $multipliers = $config['attention_volume_multipliers'] ?? null;
        if (!is_array($multipliers) || $multipliers === []) {
            throw new InvalidConfigException(
                'El catálogo de precios no declara attention_volume_multipliers.'
            );
        }
        $copy = $config['attention_volume_copy'] ?? [];
        $copy = is_array($copy) ? $copy : [];

        $presets = [];
        foreach (self::sellableClassCodes() as $class) {
            $reference = self::referenceEncountersPerMonth($class);
            $classCopy = is_array($copy[$class] ?? null) ? $copy[$class] : [];
            $unit = trim((string) ($classCopy['unit'] ?? ''));
            $steps = is_array($classCopy['steps'] ?? null) ? $classCopy['steps'] : [];

            $list = [];
            foreach ($multipliers as $multiplier) {
                $factor = (float) $multiplier;
                if ($factor <= 0) {
                    continue;
                }
                $attentions = (int) round($reference * $factor);
                $step = $steps[self::multiplierKey($factor)] ?? null;
                $prefix = is_array($step) ? trim((string) ($step['prefix'] ?? '')) : '';
                $hint = is_array($step) ? trim((string) ($step['hint'] ?? '')) : '';
                $list[] = [
                    'attentions' => $attentions,
                    'label' => $prefix !== '' && $unit !== ''
                        ? $prefix . ' · ' . $attentions . ' ' . $unit . ' / mes'
                        : null,
                    'hint' => $hint !== '' ? $hint : null,
                ];
            }
            $presets[$class] = $list;
        }

        return $presets;
    }

    /**
     * Volumen inicial de la cotización por clase, derivado de la capacidad de
     * referencia y del multiplicador declarado en `defaults`.
     *
     * @return array<string, int>
     */
    public static function initialAttentionsByClass(): array
    {
        $multiplier = (float) (self::loadConfig()['defaults']['initial_attentions_multiplier'] ?? 0);
        if ($multiplier <= 0) {
            throw new InvalidConfigException(
                'El catálogo de precios no declara defaults.initial_attentions_multiplier.'
            );
        }

        $defaults = [];
        foreach (self::sellableClassCodes() as $class) {
            $defaults[$class] = (int) round(self::referenceEncountersPerMonth($class) * $multiplier);
        }

        return $defaults;
    }

    /** Clave estable para indexar el copy por multiplicador (0.5, 1, 2, ...). */
    private static function multiplierKey(float $multiplier): string
    {
        $formatted = rtrim(rtrim(number_format($multiplier, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
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
     * Ids de addons declarados en el catálogo (orden de declaración).
     *
     * @return list<string>
     */
    public static function addonIds(): array
    {
        $addons = self::loadConfig()['addons'] ?? [];
        if (!is_array($addons)) {
            return [];
        }

        return array_map('strval', array_keys($addons));
    }

    /**
     * Campo del contrato `POST /licencia/cotizar` que representa el addon.
     */
    public static function addonPlanField(string $addonId): string
    {
        $addon = self::loadConfig()['addons'][$addonId] ?? null;
        if (!is_array($addon)) {
            return '';
        }

        return trim((string) ($addon['plan_field'] ?? ''));
    }

    /**
     * @return array<string, string> addon_id => plan_field (solo addons persistidos)
     */
    public static function addonPlanFields(): array
    {
        $fields = [];
        foreach (self::addonIds() as $addonId) {
            $field = self::addonPlanField($addonId);
            if ($field !== '') {
                $fields[$addonId] = $field;
            }
        }

        return $fields;
    }

    /**
     * COGS USD por atención de cada addon con impacto declarado en el catálogo.
     *
     * El dictado se costea con sus supuestos (`cogs_assumptions`); el resto toma
     * `cogs_usd_per_encounter[<addon_id>]`. Sin entrada, el addon no mueve el precio.
     *
     * @return array<string, float>
     */
    public static function addonCogsPerEncounter(): array
    {
        $cogs = self::loadConfig()['cogs_usd_per_encounter'] ?? [];
        if (!is_array($cogs)) {
            $cogs = [];
        }

        $map = [];
        foreach (self::addonIds() as $addonId) {
            if ($addonId === 'dictado') {
                $map[$addonId] = self::dictadoCogsPerEncounter();
                continue;
            }
            if (isset($cogs[$addonId])) {
                $map[$addonId] = (float) $cogs[$addonId];
            }
        }

        return $map;
    }

    /**
     * ¿El addon se cobra en esa clase? Incluido → siempre; opcional → si se eligió.
     *
     * @param array<string, bool> $selectedAddons addon_id => elegido
     */
    public static function addonAppliesToClass(
        string $addonId,
        string $encounterClass,
        array $selectedAddons = []
    ): bool {
        $state = self::addonState($addonId, $encounterClass);
        if ($state === PricingPesByEncounterClassCatalog::ADDON_INCLUDED) {
            return true;
        }
        if ($state === PricingPesByEncounterClassCatalog::ADDON_OPTIONAL) {
            return !empty($selectedAddons[$addonId]);
        }

        return false;
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
     * COGS USD por atención según los addons vigentes en la clase.
     *
     * @param array<string, bool> $selectedAddons addon_id => elegido (solo opcionales)
     */
    public static function unitCogsPerEncounter(
        array $selectedAddons = [],
        ?string $encounterClass = null
    ): float {
        $cogs = self::loadConfig()['cogs_usd_per_encounter'] ?? [];
        if (!is_array($cogs)) {
            return 0.0;
        }
        $total = self::motivosCogsPerEncounter($encounterClass) + (float) ($cogs['captura_ia'] ?? 0);
        if ($encounterClass !== null && self::classIncludesPatientChat($encounterClass)) {
            $total += (float) ($cogs['patient_chat_amb'] ?? 0);
        }
        foreach (self::addonCogsPerEncounter() as $addonId => $addonCogs) {
            if ($encounterClass !== null
                && self::addonAppliesToClass($addonId, $encounterClass, $selectedAddons)
            ) {
                $total += $addonCogs;
            }
        }

        return round($total, 4);
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
        if (!self::isSellableClass(strtoupper($encounterClass))) {
            return null;
        }
        $defaults = self::initialAttentionsByClass();
        $n = (int) ($defaults[strtoupper($encounterClass)] ?? 0);

        return $n > 0 ? $n : null;
    }

    public static function dictadoMinutes(): float
    {
        $assumptions = self::loadConfig()['cogs_assumptions'] ?? [];
        $minutes = (float) ($assumptions['dictado_minutes'] ?? 0);
        if ($minutes <= 0) {
            throw new InvalidConfigException(
                'El catálogo de precios no declara cogs_assumptions.dictado_minutes.'
            );
        }

        return $minutes;
    }

    public static function dictadoCogsPerEncounter(): float
    {
        $assumptions = self::loadConfig()['cogs_assumptions'] ?? [];
        $perMinute = (float) ($assumptions['dictado_usd_per_minute'] ?? 0);
        if ($perMinute <= 0) {
            throw new InvalidConfigException(
                'El catálogo de precios no declara cogs_assumptions.dictado_usd_per_minute.'
            );
        }

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
     * @param array<string, bool> $selectedAddons addon_id => elegido (solo opcionales)
     * @param int|null $totalAttentions atenciones totales del contrato; null = margen de lista.
     */
    public static function unitPrice(
        array $selectedAddons = [],
        ?string $encounterClass = null,
        ?int $totalAttentions = null
    ): float {
        $cogs = self::unitCogsPerEncounter($selectedAddons, $encounterClass);
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

        return self::unitPrice([], $encounterClass, $totalAttentions);
    }

    public static function defaultWhenEmptyAllowAll(): bool
    {
        $mode = (string) (self::loadConfig()['default_when_empty'] ?? 'allow_all');

        return $mode !== 'deny_unlisted';
    }

    /**
     * @param array<string, int> $attentionsByClass code => atenciones / mes
     * @param array<string, bool> $selectedAddons addon_id => elegido (solo opcionales)
     */
    public static function estimateMonthlyTotal(
        array $attentionsByClass,
        array $selectedAddons = []
    ): float {
        return self::estimateMonthlyQuote($attentionsByClass, $selectedAddons)['amount_usd'];
    }

    /**
     * @param array<string, int> $attentionsByClass code => atenciones / mes
     * @param array<string, bool> $selectedAddons addon_id => elegido (solo opcionales)
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
        array $selectedAddons = []
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
            $unitPrice = self::unitPrice($selectedAddons, $code, $totalAttentions);
            $listUnitPrice = self::unitPrice($selectedAddons, $code);
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
