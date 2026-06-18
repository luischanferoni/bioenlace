<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de modalidades de reserva ({@see metadata/reserva_modalidad_atencion.yaml}).
 */
final class ReservaModalidadAtencionCatalogService
{
    public const CODE_PRESENCIAL = 'presencial';

    public const CODE_TELECONSULTA = 'teleconsulta';

    public const CODE_ASYNC = 'async';

    private const CATALOG_FILE = 'reserva_modalidad_atencion.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{code: string, label: string}|null
     */
    public function opcion(string $code): ?array
    {
        $code = trim($code);
        $defs = self::load()['opciones'] ?? [];
        if (!is_array($defs) || !isset($defs[$code]) || !is_array($defs[$code])) {
            return null;
        }
        $def = $defs[$code];

        return [
            'code' => $code,
            'label' => trim((string) ($def['label'] ?? $code)),
        ];
    }

    /**
     * @return list<string>
     */
    public function elegibilidadesParaAsync(): array
    {
        $def = self::load()['opciones'][self::CODE_ASYNC] ?? [];
        $raw = is_array($def) ? ($def['requires_elegibilidad'] ?? []) : [];

        return is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    }

    /**
     * @return array{summary: string, hint: string}
     */
    public function mensajeTeleconsultaHubSinCupos(): array
    {
        $block = self::load()['teleconsulta_hub_sin_cupos'] ?? [];

        return [
            'summary' => trim((string) (is_array($block) ? ($block['summary'] ?? '') : '')),
            'hint' => trim((string) (is_array($block) ? ($block['hint'] ?? '') : '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = __DIR__ . '/../metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }

        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    /** Solo tests. */
    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
