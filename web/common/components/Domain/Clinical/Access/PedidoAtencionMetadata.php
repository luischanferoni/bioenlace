<?php

namespace common\components\Domain\Clinical\Access;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Metadata de PedidoAtencion ({@see ProductMetadataPaths::pedidoAtencionFile()}).
 */
final class PedidoAtencionMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return self::load();
    }

    /**
     * @return list<string>
     */
    public static function allowedSystems(): array
    {
        $raw = self::load()['allowed_systems'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return CodingSystems::defaults();
        }
        $out = [];
        foreach ($raw as $uri) {
            $s = trim((string) $uri);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out !== [] ? $out : CodingSystems::defaults();
    }

    /**
     * @return array{code: string, code_system: string, display: string}|null
     */
    public static function defaultActoForModo(string $modo): ?array
    {
        $modo = strtolower(trim($modo));
        $defaults = self::load()['defaults_por_modo'] ?? null;
        if (!is_array($defaults) || !isset($defaults[$modo]) || !is_array($defaults[$modo])) {
            return null;
        }
        $row = $defaults[$modo];
        $code = trim((string) ($row['code'] ?? ''));
        $system = trim((string) ($row['code_system'] ?? ''));
        $display = trim((string) ($row['display'] ?? ''));
        if ($code === '' || $system === '' || !CodingSystems::isAllowed($system, self::allowedSystems())) {
            return null;
        }

        return [
            'code' => $code,
            'code_system' => $system,
            'display' => $display !== '' ? $display : $code,
        ];
    }

    /**
     * Reglas de capacidad ECL por tipología de oferta.
     *
     * @return list<array{
     *   specialty_system: string|null,
     *   specialty_code: string|null,
     *   match_tipo: string|null,
     *   act_ecl: string
     * }>
     */
    public static function capacityRules(): array
    {
        $raw = self::load()['capacity_rules'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ecl = trim((string) ($row['act_ecl'] ?? ''));
            if ($ecl === '') {
                continue;
            }
            $specialtyCode = trim((string) ($row['specialty_code'] ?? ''));
            $specialtySystem = trim((string) ($row['specialty_system'] ?? ''));
            $matchTipo = strtolower(trim((string) ($row['match_tipo'] ?? '')));
            if ($specialtyCode === '' && $matchTipo === '') {
                continue;
            }
            $out[] = [
                'specialty_system' => $specialtySystem !== '' ? $specialtySystem : null,
                'specialty_code' => $specialtyCode !== '' ? $specialtyCode : null,
                'match_tipo' => $matchTipo !== '' ? $matchTipo : null,
                'act_ecl' => $ecl,
            ];
        }

        return $out;
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'allowed_systems' => CodingSystems::defaults(),
            'defaults_por_modo' => [],
            'modos' => [],
            'capacity_rules' => [],
            'linea_nl_aliases' => [],
            'acto_nl_aliases' => [],
            'acto_coding' => [],
        ];

        $path = ProductMetadataPaths::pedidoAtencionFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('PedidoAtencionMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        if (!is_array($data)) {
            return self::$config;
        }

        foreach ([
            'allowed_systems',
            'defaults_por_modo',
            'modos',
            'capacity_rules',
            'linea_nl_aliases',
            'acto_nl_aliases',
            'acto_coding',
        ] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }

    /**
     * Resuelve texto NL (p. ej. "clínico") a tipología de oferta.
     *
     * @return array{specialty_code: string, specialty_system: string}|null
     */
    public static function resolveLineaSpecialtyFromNl(string $text): ?array
    {
        $needle = self::foldNlAlias($text);
        if ($needle === '') {
            return null;
        }
        $raw = self::load()['linea_nl_aliases'] ?? null;
        if (!is_array($raw)) {
            return null;
        }
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['specialty_code'] ?? ''));
            $system = trim((string) ($row['specialty_system'] ?? CodingSystems::SNOMED));
            if ($code === '' || $system === '') {
                continue;
            }
            $aliases = $row['aliases'] ?? null;
            if (!is_array($aliases)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (self::foldNlAlias((string) $alias) === $needle) {
                    return [
                        'specialty_code' => $code,
                        'specialty_system' => $system,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Etiqueta de paciente para un acto (YAML). El FSN SNOMED no se muestra en chips.
     */
    public static function patientLabelForActo(string $code, string $system): ?string
    {
        $row = self::actoNlRow($code, $system);
        if ($row === null) {
            return null;
        }
        $label = trim((string) ($row['label'] ?? ''));

        return $label !== '' ? $label : null;
    }

    /**
     * Claves plegadas para match NL de un acto (label + aliases + display de catálogo).
     *
     * @return list<string>
     */
    public static function matchKeysForActo(string $code, string $system, string $catalogDisplay = ''): array
    {
        $out = [];
        $row = self::actoNlRow($code, $system);
        if ($row !== null) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $out[] = $label;
            }
            $aliases = $row['aliases'] ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    $a = trim((string) $alias);
                    if ($a !== '') {
                        $out[] = $a;
                    }
                }
            }
        }
        $display = trim($catalogDisplay);
        if ($display !== '') {
            $out[] = $display;
        }

        $folded = [];
        foreach ($out as $raw) {
            $n = self::foldNlAlias($raw);
            if ($n !== '') {
                $folded[$n] = true;
            }
        }

        return array_keys($folded);
    }

    /**
     * ¿El texto (frase o token) contiene esta clave NL?
     */
    public static function nlTextHitsKey(string $text, string $key): bool
    {
        $haystack = self::foldNlAlias($text);
        $alias = self::foldNlAlias($key);
        if ($haystack === '' || $alias === '' || mb_strlen($alias, 'UTF-8') < 3) {
            return false;
        }
        if ($haystack === $alias) {
            return true;
        }
        if (str_contains($alias, ' ')) {
            return str_contains($haystack, $alias);
        }
        $quoted = preg_quote($alias, '/');
        if (preg_match('/(?:^|[^a-z0-9])' . $quoted . '(?:$|[^a-z0-9])/u', $haystack) === 1) {
            return true;
        }
        // Token suelto (p. ej. "ultrasonography") contra FSN más largo.
        if (!str_contains($haystack, ' ')
            && mb_strlen($haystack, 'UTF-8') >= 4
            && str_contains($alias, $haystack)
        ) {
            return true;
        }

        return false;
    }

    public static function foldNlText(string $text): string
    {
        return self::foldNlAlias($text);
    }

    /**
     * @return array{code: string, code_system: string, label: string, aliases: list<string>}|null
     */
    private static function actoNlRow(string $code, string $system): ?array
    {
        $code = trim($code);
        $system = trim($system);
        if ($code === '' || $system === '') {
            return null;
        }
        $raw = self::load()['acto_nl_aliases'] ?? null;
        if (!is_array($raw)) {
            return null;
        }
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowCode = trim((string) ($row['code'] ?? ''));
            $rowSystem = trim((string) ($row['code_system'] ?? CodingSystems::SNOMED));
            if ($rowCode !== $code || $rowSystem !== $system) {
                continue;
            }
            $aliases = [];
            $rawAliases = $row['aliases'] ?? [];
            if (is_array($rawAliases)) {
                foreach ($rawAliases as $alias) {
                    $a = trim((string) $alias);
                    if ($a !== '') {
                        $aliases[] = $a;
                    }
                }
            }

            return [
                'code' => $rowCode,
                'code_system' => $rowSystem,
                'label' => trim((string) ($row['label'] ?? '')),
                'aliases' => $aliases,
            ];
        }

        return null;
    }

    private static function foldNlAlias(string $text): string
    {
        $folded = mb_strtolower(trim($text), 'UTF-8');
        $folded = strtr($folded, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/\s+/u', ' ', $folded) ?? $folded;
    }

    /**
     * @return array{snomed_category: string, snowstorm_profile: string, candidate_limit: int}
     */
    public static function actoCodingConfig(): array
    {
        $raw = self::load()['acto_coding'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $category = trim((string) ($raw['snomed_category'] ?? 'procedimientos'));
        $profile = trim((string) ($raw['snowstorm_profile'] ?? $category));
        $limit = (int) ($raw['candidate_limit'] ?? 8);

        return [
            'snomed_category' => $category !== '' ? $category : 'procedimientos',
            'snowstorm_profile' => $profile !== '' ? $profile : 'procedimientos',
            'candidate_limit' => $limit > 0 ? min($limit, 20) : 8,
        ];
    }
}
