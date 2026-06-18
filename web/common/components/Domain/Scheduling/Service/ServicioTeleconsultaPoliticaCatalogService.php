<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Copy y opciones de política de teleconsulta ({@see metadata/servicio_teleconsulta_politica.yaml}).
 */
final class ServicioTeleconsultaPoliticaCatalogService
{
    private const CATALOG_FILE = 'servicio_teleconsulta_politica.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function tituloUi(): string
    {
        $block = self::load()['ui'] ?? [];

        return trim((string) (is_array($block) ? ($block['title'] ?? '') : ''));
    }

    public function mensajeInfoUi(): string
    {
        $block = self::load()['ui'] ?? [];

        return trim((string) (is_array($block) ? ($block['info_message'] ?? '') : ''));
    }

    public function hintCasoCodigos(): string
    {
        $block = self::load()['ui'] ?? [];

        return trim((string) (is_array($block) ? ($block['caso_codigos_hint'] ?? '') : ''));
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public function opcionesPolitica(): array
    {
        $map = self::load()['politica_opciones'] ?? [];
        if (!is_array($map)) {
            return [];
        }
        $out = [];
        foreach ($map as $code => $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'value' => (string) $code,
                'label' => trim((string) ($row['label'] ?? $code)),
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{title: string, label_presencial_remoto: string, label_servicios_con_video: string, label_pct: string}
     */
    public function kpiEfector(): array
    {
        $block = self::load()['kpi_efector'] ?? [];
        if (!is_array($block)) {
            return [
                'title' => '',
                'label_presencial_remoto' => '',
                'label_servicios_con_video' => '',
                'label_pct' => '',
            ];
        }

        return [
            'title' => trim((string) ($block['title'] ?? '')),
            'label_presencial_remoto' => trim((string) ($block['label_presencial_remoto'] ?? '')),
            'label_servicios_con_video' => trim((string) ($block['label_servicios_con_video'] ?? '')),
            'label_pct' => trim((string) ($block['label_pct'] ?? '')),
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

    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
