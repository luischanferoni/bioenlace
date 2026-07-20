<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Textos y SLA de la bandeja async ({@see metadata/consulta_async_bandeja.yaml}).
 */
final class ConsultaAsyncBandejaCatalogService
{
    private const CATALOG_FILE = 'consulta_async_bandeja.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function tituloSeccionStaff(): string
    {
        $block = self::load()['section'] ?? [];

        return trim((string) (is_array($block) ? ($block['title'] ?? '') : ''));
    }

    public function mensajeVacioStaff(): string
    {
        $block = self::load()['section'] ?? [];

        return trim((string) (is_array($block) ? ($block['empty_message'] ?? '') : ''));
    }

    public function tituloSeccionPaciente(): string
    {
        $block = self::load()['patient_section'] ?? [];

        return trim((string) (is_array($block) ? ($block['title'] ?? '') : ''));
    }

    public function mensajeVacioPaciente(): string
    {
        $block = self::load()['patient_section'] ?? [];

        return trim((string) (is_array($block) ? ($block['empty_message'] ?? '') : ''));
    }

    public function tituloHistorialPaciente(): string
    {
        $block = self::load()['patient_section'] ?? [];

        return trim((string) (is_array($block) ? ($block['history_title'] ?? '') : ''));
    }

    public function mensajeVacioHistorialPaciente(): string
    {
        $block = self::load()['patient_section'] ?? [];

        return trim((string) (is_array($block) ? ($block['history_empty_message'] ?? '') : ''));
    }

    public function limiteHistorialPaciente(): int
    {
        $block = self::load()['patient_section'] ?? [];
        $n = is_array($block) ? ($block['history_limit'] ?? 20) : 20;

        return max(1, (int) $n);
    }

    public function horasSlaRespuesta(?string $urgencyBand): int
    {
        $map = self::load()['sla_horas_respuesta'] ?? [];
        if (!is_array($map)) {
            return 48;
        }
        $band = strtoupper(trim((string) ($urgencyBand ?? '')));
        if ($band !== '' && isset($map[$band]) && is_numeric($map[$band])) {
            return max(1, (int) $map[$band]);
        }

        return max(1, (int) ($map['default'] ?? 48));
    }

    public function etiquetaEstado(string $status): string
    {
        $map = self::load()['status_labels'] ?? [];
        if (!is_array($map)) {
            return $status;
        }
        $key = strtolower(trim($status));

        return trim((string) ($map[$key] ?? $status));
    }

    public function mensajeExitoGenerico(): string
    {
        $block = self::load()['solicitud'] ?? [];

        return trim((string) (is_array($block) ? ($block['mensaje_exito_generico'] ?? '') : ''));
    }

    public function mensajeExitoRenovacion(): string
    {
        $block = self::load()['solicitud'] ?? [];

        return trim((string) (is_array($block) ? ($block['mensaje_exito_renovacion'] ?? '') : ''));
    }

    public function mensajeExitoRenovacionCierre(): string
    {
        $block = self::load()['solicitud'] ?? [];

        return trim((string) (is_array($block) ? ($block['mensaje_exito_renovacion_cierre'] ?? '') : ''));
    }

    public function mensajeRenovacionDuplicada(): string
    {
        $block = self::load()['solicitud'] ?? [];
        $msg = trim((string) (is_array($block) ? ($block['duplicate_renovacion_abierta'] ?? '') : ''));
        if ($msg !== '') {
            return $msg;
        }

        return 'Ya tenés una solicitud de renovación pendiente para este tratamiento.';
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
