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
     * @return array{code: string, label: string, label_short: string}|null
     */
    public function opcion(string $code): ?array
    {
        $code = trim($code);
        $defs = self::load()['opciones'] ?? [];
        if (!is_array($defs) || !isset($defs[$code]) || !is_array($defs[$code])) {
            return null;
        }
        $def = $defs[$code];
        $label = trim((string) ($def['label'] ?? $code));
        $short = trim((string) ($def['label_short'] ?? ''));

        return [
            'code' => $code,
            'label' => $label,
            'label_short' => $short !== '' ? $short : $label,
        ];
    }

    /**
     * Etiqueta corta para cards / listados.
     */
    public function labelShort(string $code): string
    {
        $opt = $this->opcion($code);

        return $opt !== null ? $opt['label_short'] : trim($code);
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
     * Raíces de triage en las que se ofrece async (vacío = sin restricción por raíz).
     *
     * @return list<string>
     */
    public function triageRaicesParaAsync(): array
    {
        $def = self::load()['opciones'][self::CODE_ASYNC] ?? [];
        $raw = is_array($def) ? ($def['requires_triage_raiz'] ?? []) : [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
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
