<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

/**
 * Clasificación de campos de configuración de agenda vs impacto en turnos (grilla).
 */
final class AgendaConfigImpactProfile
{
    /** Campos que alteran slots / conflictos con turnos futuros. */
    private const GRID_FIELD_NAMES = [
        'vigente_desde',
        'intervalo_minutos',
        'lunes_2',
        'martes_2',
        'miercoles_2',
        'jueves_2',
        'viernes_2',
        'sabado_2',
        'domingo_2',
    ];

    /** Campos de modalidad sin preview de grilla (persistencia directa). */
    private const MODALITY_FIELD_NAMES = [
        'acepta_consultas_online',
        'formas_atencion',
    ];

    /**
     * @return list<string>
     */
    public static function gridFieldNames(): array
    {
        return self::GRID_FIELD_NAMES;
    }

    /**
     * @return list<string>
     */
    public static function dayFieldNames(): array
    {
        return [
            'lunes_2',
            'martes_2',
            'miercoles_2',
            'jueves_2',
            'viernes_2',
            'sabado_2',
            'domingo_2',
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    public static function postTouchesGridFields(array $post): bool
    {
        foreach (self::GRID_FIELD_NAMES as $name) {
            if (!array_key_exists($name, $post)) {
                continue;
            }
            // Día presente (aunque vacío) = intención de tocar la grilla / limpiar ese día.
            if (in_array($name, self::dayFieldNames(), true)) {
                return true;
            }
            if (trim((string) $post[$name]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Completa el POST con defaults de agenda sin reintroducir días omitidos.
     *
     * - Si el submit trae al menos un campo de día: los días ausentes se limpian ('').
     * - Si no trae días (modalidad / intervalo): se conservan los días de la agenda actual.
     * - Con `fields` parcial: solo se limpian los días listados y ausentes; el resto se conserva.
     *
     * @param array<string, mixed> $post
     * @param array<string, mixed> $defaults Valores actuales (GET) de la agenda
     * @param list<string>|null $onlyFields
     * @return array<string, mixed>
     */
    public static function mergePostWithAgendaDefaults(array $post, array $defaults, ?array $onlyFields = null): array
    {
        $dayFields = self::dayFieldNames();
        $merged = $post;

        foreach ($defaults as $key => $value) {
            if (in_array($key, $dayFields, true)) {
                continue;
            }
            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        $touchesAnyDay = false;
        foreach ($dayFields as $day) {
            if (array_key_exists($day, $post)) {
                $touchesAnyDay = true;
                break;
            }
        }

        if ($onlyFields !== null) {
            $editingDays = array_values(array_intersect($dayFields, $onlyFields)) !== [];
            foreach ($dayFields as $day) {
                if (array_key_exists($day, $post)) {
                    $merged[$day] = $post[$day];
                    continue;
                }
                if ($editingDays && in_array($day, $onlyFields, true)) {
                    $merged[$day] = '';
                    continue;
                }
                if (array_key_exists($day, $defaults)) {
                    $merged[$day] = $defaults[$day];
                }
            }

            return $merged;
        }

        if ($touchesAnyDay) {
            foreach ($dayFields as $day) {
                if (!array_key_exists($day, $post)) {
                    $merged[$day] = '';
                }
            }

            return $merged;
        }

        foreach ($dayFields as $day) {
            if (array_key_exists($day, $defaults)) {
                $merged[$day] = $defaults[$day];
            }
        }

        return $merged;
    }

    /**
     * Solo campos de modalidad (p. ej. teleconsulta) sin cambios de grilla en el POST.
     *
     * @param array<string, mixed> $post
     */
    public static function isModalityOnlySubmit(array $post): bool
    {
        if (self::postTouchesGridFields($post)) {
            return false;
        }
        foreach (self::MODALITY_FIELD_NAMES as $name) {
            if (array_key_exists($name, $post)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $preview Resultado de previewImpacto
     */
    public static function previewRequiresUserConfirmation(array $preview, array $post): bool
    {
        if (self::isModalityOnlySubmit($post)) {
            return false;
        }

        if (!self::postTouchesGridFields($post)) {
            return false;
        }

        if (!empty($preview['requiere_confirmacion'])) {
            return true;
        }

        return (int) ($preview['turnos_en_conflicto'] ?? 0) > 0;
    }

    /**
     * @param list<string>|null $onlyFields Si se indica, conserva solo esos campos (+ ids de contexto).
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function filterPostForFields(array $post, ?array $onlyFields = null): array
    {
        if ($onlyFields === null || $onlyFields === []) {
            return $post;
        }

        $allowed = array_flip(array_merge(
            ['id_efector', 'id_profesional_efector_servicio', 'id_servicio', 'confirmar_cambios', 'preview'],
            $onlyFields
        ));
        $out = [];
        foreach ($post as $key => $value) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }
            if (is_scalar($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
