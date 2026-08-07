<?php

namespace common\components\Domain\Clinical\Presentation;

/**
 * Fechas de episodio (guardia / internación) para UI: formato es-AR legible.
 */
final class EpisodioDateTimeFormatter
{
    /**
     * dd/MM/yyyy HH:mm (sin segundos). Si solo hay fecha → dd/MM/yyyy.
     */
    public static function display(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return '';
        }

        // Ya formateado (evita doble paso).
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}( \d{2}:\d{2})?$/', $raw)) {
            return $raw;
        }

        // Solo hora HH:MM(:SS)
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }

        // d/m/Y H:i:s u otras variantes parseables
        $normalized = str_replace('T', ' ', $raw);
        $normalized = preg_replace('/\.\d+/', '', $normalized) ?? $normalized;

        // Fecha ISO sin hora
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $normalized, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        // Fecha d/m/Y sin hora (legacy)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $normalized)) {
            $ts = strtotime(str_replace('/', '-', $normalized));
            if ($ts !== false) {
                return date('d/m/Y', $ts);
            }
        }

        $ts = strtotime($normalized);
        if ($ts === false) {
            // "Y-m-d H:i" o "d/m/Y H:i" parcial
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})/', $normalized, $m)) {
                return $m[3] . '/' . $m[2] . '/' . $m[1] . ' '
                    . str_pad($m[4], 2, '0', STR_PAD_LEFT) . ':' . $m[5];
            }

            return $raw;
        }

        $hasTime = (bool) preg_match('/\d{1,2}:\d{2}/', $normalized);

        return $hasTime ? date('d/m/Y H:i', $ts) : date('d/m/Y', $ts);
    }

    /**
     * Combina fecha + hora (campos separados típicos de internación/guardia).
     */
    public static function displayFromParts(?string $fecha, ?string $hora = null): string
    {
        $fecha = trim((string) $fecha);
        $hora = trim((string) $hora);
        if ($fecha === '') {
            return '';
        }
        if ($hora !== '') {
            $hora = substr($hora, 0, 5);
            if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
                return self::display($fecha . ' ' . $hora);
            }
        }

        return self::display($fecha);
    }
}
