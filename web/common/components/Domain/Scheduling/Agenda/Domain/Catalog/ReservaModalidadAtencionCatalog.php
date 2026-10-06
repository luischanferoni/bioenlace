<?php

namespace common\components\Domain\Scheduling\Agenda\Domain\Catalog;

/**
 * Catálogo de dominio (ex Scheduling/metadata/reserva_modalidad_atencion.yaml).
 */
final class ReservaModalidadAtencionCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => '1',
        'opciones' => [
            'presencial' => [
                'code' => 'presencial',
                'label' => 'Presencial (voy al centro de salud)',
                'label_short' => 'Presencial',
                'always_if_not_halt' => true,
            ],
            'teleconsulta' => [
                'code' => 'teleconsulta',
                'label' => 'Remoto (videollamada con turno)',
                'label_short' => 'Videollamada',
                'requires_teleconsulta_visible' => true,
            ],
            'async' => [
                'code' => 'async',
                'label' => 'Consulta clínica por mensaje (sin turno ni videollamada)',
                'label_short' => 'Por mensaje',
                'requires_triage_raiz' => [
                    0 => 'seguimiento_cronico',
                ],
                'requires_elegibilidad' => [
                    0 => 'sugerido',
                    1 => 'permitido',
                ],
            ],
        ],
        'teleconsulta_hub_sin_cupos' => [
            'summary' => 'No hay horarios de videollamada disponibles en este momento.',
            'hint' => 'Podés elegir consulta clínica por mensaje en el paso anterior o intentar un turno presencial.',
        ],
    ];
    }

    /**
     * Etiquetas cortas para la guía (elige_entre).
     *
     * @return list<string>
     */
    public static function labelsShortForGuide(): array
    {
        $out = [];
        $opciones = self::config()['opciones'] ?? [];
        if (!is_array($opciones)) {
            return [];
        }
        foreach ($opciones as $def) {
            if (!is_array($def)) {
                continue;
            }
            $short = trim((string) ($def['label_short'] ?? ''));
            if ($short === '') {
                $short = trim((string) ($def['label'] ?? ''));
            }
            if ($short !== '') {
                $out[] = $short;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        $opciones = self::config()['opciones'] ?? [];
        if (!is_array($opciones)) {
            return [];
        }
        $codes = [];
        foreach ($opciones as $key => $def) {
            if (!is_array($def)) {
                continue;
            }
            $code = trim((string) ($def['code'] ?? $key));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    public static function labelShortForCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        $def = self::config()['opciones'][$code] ?? null;
        if (!is_array($def)) {
            return '';
        }
        $short = trim((string) ($def['label_short'] ?? ''));
        if ($short !== '') {
            return $short;
        }

        return trim((string) ($def['label'] ?? ''));
    }
}
