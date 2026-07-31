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

    /**
     * Nombres de `servicios` legacy modelados como acto (no oferta institucional).
     *
     * @return list<string> upper-case
     */
    public static function legacyActoAsServicioNames(): array
    {
        $raw = self::load()['legacy_acto_as_servicio_names'] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $name) {
            $n = mb_strtoupper(trim((string) $name), 'UTF-8');
            if ($n !== '') {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    public static function isLegacyActoServicioNombre(string $nombre): bool
    {
        $n = mb_strtoupper(trim($nombre), 'UTF-8');
        if ($n === '') {
            return false;
        }

        return in_array($n, self::legacyActoAsServicioNames(), true);
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
            'legacy_acto_as_servicio_names' => [],
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
            'legacy_acto_as_servicio_names',
            'acto_coding',
        ] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
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
